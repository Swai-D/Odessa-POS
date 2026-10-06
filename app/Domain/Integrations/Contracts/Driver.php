<?php

namespace App\Domain\Integrations\Contracts;

/**
 * A pluggable provider for one integration channel (printer, fiscal, payments).
 * The driver describes the settings it needs; Settings renders the form from that description.
 */
interface Driver
{
    /** Stable identifier stored with the tenant's configuration, e.g. "escpos". */
    public static function key(): string;

    /** Translation key of the name shown in the driver picker. */
    public static function label(): string;

    /**
     * Configuration fields. `secret` fields are stored encrypted and never shown again.
     *
     * @return list<array{name: string, label: string, type: 'text'|'password'|'number'|'select', required?: bool, secret?: bool, options?: array<string, string>, default?: string}>
     */
    public static function fields(): array;
}
