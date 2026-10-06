<?php

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\TenantIntegration;
use App\Domain\Integrations\Services\IntegrationRegistry;
use App\Models\Tenant;

/** Saves the chosen driver and its configuration for one channel and switches the channel on or off. */
class SaveIntegrationAction
{
    public function __construct(private readonly IntegrationRegistry $registry) {}

    /**
     * @param  array{enabled: bool|int|string, driver?: string|null, config?: array<string, mixed>|null}  $data  validated IntegrationRequest data
     */
    public function handle(Tenant $tenant, string $channel, array $data): void
    {
        $enabled = filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN);
        $class = $this->registry->driver($channel, (string) ($data['driver'] ?? ''));

        if ($class !== null) {
            $existing = TenantIntegration::query()->where('channel', $channel)->first();
            // Keep the saved secrets only when the driver is unchanged.
            $secrets = $existing !== null && $existing->driver === $class::key() ? ($existing->secrets ?? []) : [];
            $settings = [];

            foreach ($class::fields() as $field) {
                $value = $data['config'][$field['name']] ?? null;

                if ($field['secret'] ?? false) {
                    // A blank secret field means "keep what is saved".
                    if ($value !== null && $value !== '') {
                        $secrets[$field['name']] = $value;
                    }

                    continue;
                }

                $settings[$field['name']] = $value ?? ($field['default'] ?? null);
            }

            TenantIntegration::query()->updateOrCreate(
                ['channel' => $channel],
                ['driver' => $class::key(), 'settings' => $settings, 'secrets' => $secrets === [] ? null : $secrets],
            );
        }

        $settings = (array) ($tenant->settings ?? []);
        $features = (array) ($settings['features'] ?? []);
        $features[$this->registry->feature($channel)] = $enabled && $class !== null;
        $settings['features'] = $features;

        $tenant->update(['settings' => $settings]);
    }
}
