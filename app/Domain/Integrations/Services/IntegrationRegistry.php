<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Contracts\Driver;

/** Knows which channels exist and which drivers each one offers (from config/integrations.php). */
class IntegrationRegistry
{
    /** @return list<string> */
    public function channels(): array
    {
        return array_keys(config('integrations.channels'));
    }

    /** The feature flag that switches a channel on for a tenant. */
    public function feature(string $channel): string
    {
        return (string) config("integrations.channels.{$channel}.feature");
    }

    /** The subscription feature a plan must include to use a channel. */
    public function planFeature(string $channel): string
    {
        return (string) config("integrations.channels.{$channel}.plan_feature");
    }

    /** @return array<string, class-string<Driver>> driver key => class */
    public function drivers(string $channel): array
    {
        $drivers = [];

        foreach (config("integrations.channels.{$channel}.drivers", []) as $class) {
            $drivers[$class::key()] = $class;
        }

        return $drivers;
    }

    /** @return class-string<Driver>|null */
    public function driver(string $channel, string $key): ?string
    {
        return $this->drivers($channel)[$key] ?? null;
    }

    /** @return list<string> feature flags that belong to a channel rather than the general settings form */
    public function ownedFeatures(): array
    {
        return array_map(fn (string $channel): string => $this->feature($channel), $this->channels());
    }
}
