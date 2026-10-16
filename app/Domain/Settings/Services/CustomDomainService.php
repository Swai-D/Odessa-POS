<?php

namespace App\Domain\Settings\Services;

use App\Models\Tenant;
use App\Support\Dns\DnsResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A shop's own web address. The shop names it, proves it owns it with a TXT record, and points it at this app
 * with a CNAME. Until the proof succeeds the name is only a request, so nobody can reserve a name they do not own.
 */
class CustomDomainService
{
    public const TXT_LABEL = '_odessa-verify';

    public function __construct(private readonly DnsResolver $dns, private readonly ShopNotifier $notifier) {}

    /** Lower case host name without scheme, path, port or a trailing dot. */
    public function normalize(string $input): string
    {
        $host = strtolower(trim($input));
        $host = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);
        $host = (string) preg_replace('#[/?\#].*$#', '', $host);
        $host = (string) preg_replace('#:\d+$#', '', $host);

        return rtrim($host, '.');
    }

    /** Why a name cannot be used, or null when it can. Returns a lang key under "domain.errors". */
    public function problem(string $host, ?Tenant $for = null): ?string
    {
        if (! preg_match('/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $host)) {
            return 'format';
        }

        $app = $this->appDomain();
        if ($app !== '' && ($host === $app || str_ends_with($host, '.'.$app))) {
            return 'platform';
        }

        foreach (['railway.app', 'localhost', 'local', 'test', 'example.com'] as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                return 'platform';
            }
        }

        $taken = Tenant::query()->where('domain', $host)->when($for, fn ($q) => $q->whereKeyNot($for->getKey()))->exists();

        return $taken ? 'taken' : null;
    }

    /** Remember the requested name and give the shop its proof token. */
    public function start(Tenant $tenant, string $host): void
    {
        $tenant->update([
            'domain_pending' => $host,
            'domain_token' => Str::random(32),
        ]);
    }

    /** @return array{txt_host: string, txt_value: string, cname_host: string, target: string}|null */
    public function instructions(Tenant $tenant): ?array
    {
        if (! $tenant->domain_pending || ! $tenant->domain_token) {
            return null;
        }

        return [
            'txt_host' => self::TXT_LABEL.'.'.$tenant->domain_pending,
            'txt_value' => $this->txtValue($tenant),
            'cname_host' => $tenant->domain_pending,
            'target' => $this->target(),
        ];
    }

    /**
     * Looks for the proof record. On success the name becomes the shop's address and the platform team is told to
     * attach it to the hosting. @return 'verified'|'missing'|'taken'|'nothing'
     */
    public function verify(Tenant $tenant): string
    {
        $host = $tenant->domain_pending;
        if (! $host || ! $tenant->domain_token) {
            return 'nothing';
        }

        if (! in_array($this->txtValue($tenant), $this->dns->txt(self::TXT_LABEL.'.'.$host), true)) {
            return 'missing';
        }

        try {
            DB::transaction(function () use ($tenant, $host): void {
                if (Tenant::query()->where('domain', $host)->whereKeyNot($tenant->getKey())->lockForUpdate()->exists()) {
                    throw new \DomainException('taken');
                }

                $tenant->update([
                    'domain' => $host,
                    'domain_pending' => null,
                    'domain_token' => null,
                    'domain_verified_at' => now(),
                    'domain_routed_at' => $this->points($host) ? now() : null,
                ]);
            });
        } catch (\DomainException|QueryException) {
            return 'taken';
        }

        $this->notifier->domainVerified($tenant, $host);

        return 'verified';
    }

    /** Re-checks whether an active domain points at this app and records it. */
    public function refreshRouting(Tenant $tenant): bool
    {
        if (! $tenant->domain) {
            return false;
        }

        $routed = $this->points($tenant->domain);
        $tenant->update(['domain_routed_at' => $routed ? ($tenant->domain_routed_at ?? now()) : null]);

        return $routed;
    }

    public function remove(Tenant $tenant): void
    {
        $tenant->update(['domain' => null, 'domain_pending' => null, 'domain_token' => null, 'domain_verified_at' => null, 'domain_routed_at' => null]);
    }

    public function cancelPending(Tenant $tenant): void
    {
        $tenant->update(['domain_pending' => null, 'domain_token' => null]);
    }

    /** What the shop must point its name at. */
    public function target(): string
    {
        $target = (string) config('pos.custom_domain_target');

        return $target !== '' ? $target : $this->appDomain();
    }

    /** True when the name's CNAME is our target, or it resolves to the same addresses as the target. */
    private function points(string $host): bool
    {
        $target = strtolower($this->target());

        if ($target === '') {
            return false;
        }

        if (in_array($target, $this->dns->cname($host), true)) {
            return true;
        }

        $theirs = $this->dns->addresses($host);

        return $theirs !== [] && array_intersect($theirs, $this->dns->addresses($target)) !== [];
    }

    private function txtValue(Tenant $tenant): string
    {
        return 'odessa-verify='.$tenant->domain_token;
    }

    private function appDomain(): string
    {
        $configured = (string) config('app.domain');
        $url = str_contains($configured, '://') ? $configured : 'http://'.$configured;

        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
