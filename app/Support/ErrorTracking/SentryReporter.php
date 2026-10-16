<?php

namespace App\Support\ErrorTracking;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends errors to Sentry over its HTTP "envelope" API. It never throws and never slows the response down
 * (it runs after the response is sent). Request bodies, cookies, headers, query strings, emails and
 * query bindings are not sent; the shop and the user are identified by id only.
 */
class SentryReporter
{
    public function enabled(): bool
    {
        return $this->endpoint() !== null;
    }

    public function capture(Throwable $e): void
    {
        try {
            if (! $this->enabled() || $this->sampledOut() || $this->duplicate($e->getFile().':'.$e->getLine().':'.$e::class)) {
                return;
            }

            $event = $this->exceptionEvent($e);
            $this->later($event);
        } catch (Throwable) {
            // Error tracking must never cause an error.
        }
    }

    /** An error caught in the browser, forwarded by ClientErrorController. @param array<string, mixed> $data */
    public function captureBrowser(array $data): void
    {
        try {
            $message = Str::limit((string) ($data['message'] ?? 'Script error'), 300, '');
            if (! $this->enabled() || $this->sampledOut() || $this->duplicate('js:'.$message.':'.($data['source'] ?? '').':'.($data['line'] ?? ''))) {
                return;
            }

            $event = $this->base('error') + [
                'platform' => 'javascript',
                'exception' => ['values' => [[
                    'type' => 'Error',
                    'value' => $this->redact($message),
                    'stacktrace' => ['frames' => [[
                        'filename' => $this->stripQuery((string) ($data['source'] ?? '')),
                        'lineno' => (int) ($data['line'] ?? 0),
                        'colno' => (int) ($data['column'] ?? 0),
                    ]]],
                ]]],
            ];
            $event['request'] = ['url' => $this->stripQuery((string) ($data['page'] ?? ''))];
            $event['extra'] = ['stack' => Str::limit($this->redact((string) ($data['stack'] ?? '')), 3000, '')];
            $this->later($event);
        } catch (Throwable) {
        }
    }

    /** Used by `php artisan errors:test`: sends at once and says what the server answered. */
    public function sendTest(): string
    {
        if (! $this->enabled()) {
            return 'No DSN set (SENTRY_DSN).';
        }

        $event = $this->base('info') + ['message' => 'Odessa POS test event: error tracking is connected.'];
        $response = $this->post($event);

        return $response === null ? 'Could not reach the server.' : 'HTTP '.$response;
    }

    /** @return array<string, mixed> */
    private function exceptionEvent(Throwable $e): array
    {
        $values = [];
        for ($current = $e, $depth = 0; $current !== null && $depth < 4; $current = $current->getPrevious(), $depth++) {
            $values[] = [
                'type' => $current::class,
                'value' => $this->exceptionMessage($current),
                'stacktrace' => ['frames' => $this->frames($current)],
            ];
        }

        return $this->base('error') + [
            'platform' => 'php',
            // Sentry wants the oldest cause first.
            'exception' => ['values' => array_reverse($values)],
        ];
    }

    /** @return array<string, mixed> */
    private function base(string $level): array
    {
        $tenant = app(TenantContext::class)->get();
        $request = app()->runningInConsole() ? null : request();

        $event = [
            'event_id' => str_replace('-', '', (string) Str::uuid()),
            'timestamp' => microtime(true),
            'level' => $level,
            'logger' => 'odessa-pos',
            'environment' => (string) config('errors.environment'),
            'server_name' => gethostname() ?: 'server',
            'tags' => array_filter([
                'tenant' => $tenant?->slug,
                'runtime' => app()->runningInConsole() ? 'console' : 'web',
                'php' => PHP_VERSION,
            ]),
            'contexts' => ['runtime' => ['name' => 'php', 'version' => PHP_VERSION]],
        ];

        if ($release = config('errors.release')) {
            $event['release'] = (string) $release;
        }

        if ($tenant) {
            $event['extra'] = ['tenant_id' => (string) $tenant->getKey()];
        }

        if ($request && $request->user()) {
            $event['user'] = ['id' => (string) $request->user()->getAuthIdentifier()];
        }

        if ($request) {
            $event['request'] = ['method' => $request->method(), 'url' => $request->url()];
        }

        return $event;
    }

    /** @return list<array<string, mixed>> oldest call first, as Sentry expects */
    private function frames(Throwable $e): array
    {
        $frames = [['filename' => $this->relative($e->getFile()), 'lineno' => $e->getLine(), 'in_app' => ! $this->vendor($e->getFile())]];

        foreach (array_slice($e->getTrace(), 0, 40) as $step) {
            if (! isset($step['file'])) {
                continue;
            }
            $frames[] = [
                'filename' => $this->relative($step['file']),
                'lineno' => $step['line'] ?? 0,
                'function' => ($step['class'] ?? '').($step['type'] ?? '').($step['function'] ?? ''),
                'in_app' => ! $this->vendor($step['file']),
            ];
        }

        return array_reverse($frames);
    }

    private function exceptionMessage(Throwable $e): string
    {
        // A query exception message contains the bound values (names, phone numbers): send the driver message only.
        $message = $e instanceof QueryException && $e->getPrevious() ? $e->getPrevious()->getMessage() : $e->getMessage();

        return Str::limit($this->redact($message), 500, '');
    }

    /** Hides values in "Key (email)=(a@b.c)" style database messages and anything that looks like an email. */
    private function redact(string $text): string
    {
        $text = (string) preg_replace('/\(([^()]*)\)=\(([^()]*)\)/', '($1)=(***)', $text);

        return (string) preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '***@***', $text);
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, base_path()) ? ltrim(substr($path, strlen(base_path())), '/') : $path;
    }

    private function vendor(string $path): bool
    {
        return str_contains($path, '/vendor/') || ! str_starts_with($path, base_path());
    }

    private function stripQuery(string $url): string
    {
        return Str::before(Str::before($url, '?'), '#');
    }

    private function sampledOut(): bool
    {
        $rate = (float) config('errors.sample_rate');

        return $rate < 1 && mt_rand() / mt_getrandmax() >= $rate;
    }

    private function duplicate(string $fingerprint): bool
    {
        $seconds = (int) config('errors.dedupe_seconds');

        return $seconds > 0 && ! Cache::add('errors:'.md5($fingerprint), 1, $seconds);
    }

    /** After the response has been sent; at once under test, where nothing ever terminates. @param array<string, mixed> $event */
    private function later(array $event): void
    {
        if (app()->runningUnitTests()) {
            $this->post($event);

            return;
        }

        defer(fn () => $this->post($event));
    }

    /** @param array<string, mixed> $event */
    private function post(array $event): ?int
    {
        $endpoint = $this->endpoint();
        if ($endpoint === null) {
            return null;
        }

        $body = json_encode(['event_id' => $event['event_id'], 'sent_at' => gmdate('c')])."\n"
            .json_encode(['type' => 'event'])."\n"
            .json_encode($event, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        try {
            return Http::timeout(3)->withHeaders(['X-Sentry-Auth' => $endpoint['auth']])
                ->withBody($body, 'application/x-sentry-envelope')->post($endpoint['url'])->status();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{url: string, auth: string}|null */
    private function endpoint(): ?array
    {
        $dsn = (string) config('errors.dsn');
        $parts = $dsn === '' ? false : parse_url($dsn);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || empty($parts['user']) || empty($parts['path'])) {
            return null;
        }

        $project = trim($parts['path'], '/');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return [
            'url' => $parts['scheme'].'://'.$parts['host'].$port.'/api/'.$project.'/envelope/',
            'auth' => 'Sentry sentry_version=7, sentry_client=odessa-pos/1.0, sentry_key='.$parts['user'],
        ];
    }
}
