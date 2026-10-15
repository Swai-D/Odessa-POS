<?php

namespace App\Domain\Sales\Services;

use App\Domain\Integrations\Services\IntegrationManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to AzamPay with the current shop's own credentials: token, mobile money checkout, and the public key used to
 * verify callbacks. Tokens and the key are cached per shop. Nothing here ever logs or returns a secret.
 */
class AzamPayClient
{
    /** @param  array<string, mixed>  $config  environment, app_name, client_id, client_secret, api_key */
    public function __construct(private readonly string $tenantId, private readonly array $config) {}

    /** The client for the current shop, or null when AzamPay is not the shop's active mobile money provider. */
    public static function forCurrentTenant(): ?self
    {
        $tenant = app(TenantContext::class)->get();
        $active = app(IntegrationManager::class)->active('payments');

        if ($tenant === null || $active === null || $active['driver'] !== 'azampay') {
            return null;
        }

        return new self((string) $tenant->getKey(), $active['settings'] + $active['secrets']);
    }

    /**
     * Asks the customer's phone to approve a payment.
     *
     * @return array{success: bool, transactionId: string|null, message: string|null}
     */
    public function checkout(string $msisdn, int $amountShillings, string $currency, string $externalId, string $provider): array
    {
        $response = $this->authorised(fn (PendingRequest $http): Response => $http->post($this->url('api', '/azampay/mno/checkout'), [
            'accountNumber' => $msisdn,
            'amount' => $amountShillings,
            'currency' => $currency,
            'externalId' => $externalId,
            'provider' => $provider,
        ]));

        if ($response->status() === 400) {
            return ['success' => false, 'transactionId' => null, 'message' => 'Rejected by AzamPay: '.mb_substr((string) json_encode($response->json('errors')), 0, 200)];
        }

        if (! $response->successful()) {
            throw new AzamPayException('AzamPay checkout failed with HTTP '.$response->status());
        }

        return [
            'success' => (bool) $response->json('success'),
            'transactionId' => $response->json('transactionId') !== null ? (string) $response->json('transactionId') : null,
            'message' => $response->json('message') !== null ? (string) $response->json('message') : null,
        ];
    }

    /** The RSA public key AzamPay signs callbacks with (PEM). Cached for a day; pass $fresh to fetch it again. */
    public function publicKey(bool $fresh = false): string
    {
        $key = 'azampay:public-key:'.$this->cacheScope();

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addDay(), function (): string {
            $response = $this->authorised(fn (PendingRequest $http): Response => $http->get($this->url('api', '/azampay/v1/public-key'), ['format' => 'Pem']));
            $pem = $response->json('publicKey');

            if (! $response->successful() || ! is_string($pem) || $pem === '') {
                throw new AzamPayException('AzamPay public key could not be fetched (HTTP '.$response->status().')');
            }

            return $pem;
        });
    }

    /** Checks a callback signature: SHA-256 with RSA over utilityref + externalreference + transactionstatus + operator. */
    public function verifyCallback(string $utilityRef, string $externalReference, string $status, string $operator, string $signatureBase64): bool
    {
        $data = $utilityRef.$externalReference.$status.$operator;
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false || $signature === '') {
            return false;
        }

        // A key that has just been rotated fails once; fetch the current one and try again before refusing.
        foreach ([false, true] as $fresh) {
            $public = openssl_pkey_get_public($this->publicKey($fresh));

            if ($public !== false && openssl_verify($data, $signature, $public, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function authorised(callable $call): Response
    {
        try {
            $response = $call($this->http($this->token()));

            // The token may have been revoked or expired early: get a new one and try once more.
            if ($response->status() === 401) {
                Cache::forget($this->tokenKey());
                $response = $call($this->http($this->token()));
            }

            return $response;
        } catch (ConnectionException $e) {
            throw new AzamPayException('AzamPay could not be reached: '.$e->getMessage(), 0, $e);
        }
    }

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->withHeaders(['X-API-Key' => (string) ($this->config['api_key'] ?? '')])
            ->acceptJson()->asJson()
            ->timeout((int) config('azampay.timeout'));
    }

    private function token(): string
    {
        return Cache::remember($this->tokenKey(), now()->addMinutes(30), function (): string {
            try {
                $response = Http::acceptJson()->asJson()->timeout((int) config('azampay.timeout'))
                    ->post($this->url('auth', '/AppRegistration/GenerateToken'), [
                        'appName' => (string) ($this->config['app_name'] ?? ''),
                        'clientId' => (string) ($this->config['client_id'] ?? ''),
                        'clientSecret' => (string) ($this->config['client_secret'] ?? ''),
                    ]);
            } catch (ConnectionException $e) {
                throw new AzamPayException('AzamPay could not be reached: '.$e->getMessage(), 0, $e);
            }

            $token = $response->json('data.accessToken');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new AzamPayException('AzamPay refused the app credentials (HTTP '.$response->status().'). Check the client ID, secret and environment.');
            }

            return $token;
        });
    }

    private function url(string $host, string $path): string
    {
        $environment = (string) ($this->config['environment'] ?? 'sandbox');

        return rtrim((string) config("azampay.environments.{$environment}.{$host}"), '/').$path;
    }

    private function tokenKey(): string
    {
        return 'azampay:token:'.$this->cacheScope();
    }

    /** One cache entry per shop, environment and app, so changing credentials never reuses an old token or key. */
    private function cacheScope(): string
    {
        return $this->tenantId.':'.hash('sha256', ($this->config['environment'] ?? '').'|'.($this->config['client_id'] ?? '').'|'.($this->config['api_key'] ?? ''));
    }
}
