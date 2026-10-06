<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Models\TenantIntegration;
use App\Support\TenantSettings;

/**
 * Answers "is this channel active for the current shop, and with which driver and settings?".
 * Active means: the feature flag is on, a driver is saved, and that driver is still offered.
 */
class IntegrationManager
{
    public function __construct(private readonly IntegrationRegistry $registry) {}

    /** @return array{driver: string, settings: array<string, mixed>, secrets: array<string, mixed>}|null */
    public function active(string $channel): ?array
    {
        if (! (new TenantSettings)->feature($this->registry->feature($channel))) {
            return null;
        }

        $row = TenantIntegration::query()->where('channel', $channel)->first();

        if ($row === null || $this->registry->driver($channel, $row->driver) === null) {
            return null;
        }

        return [
            'driver' => $row->driver,
            'settings' => $row->settings ?? [],
            'secrets' => $row->secrets ?? [],
        ];
    }

    public function isActive(string $channel, ?string $driver = null): bool
    {
        $active = $this->active($channel);

        return $active !== null && ($driver === null || $active['driver'] === $driver);
    }
}
