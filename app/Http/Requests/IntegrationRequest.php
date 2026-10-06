<?php

namespace App\Http\Requests;

use App\Domain\Integrations\Models\TenantIntegration;
use App\Domain\Integrations\Services\IntegrationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Saves one channel's driver choice and configuration; the rules come from the chosen driver's own field list. */
class IntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the manage-settings gate in the controller.
        return true;
    }

    public function channel(): string
    {
        $channel = (string) $this->route('channel');

        abort_unless(in_array($channel, app(IntegrationRegistry::class)->channels(), true), 404);

        return $channel;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $registry = app(IntegrationRegistry::class);
        $channel = $this->channel();
        $drivers = $registry->drivers($channel);

        $rules = [
            'enabled' => ['required', 'boolean'],
            'driver' => [Rule::requiredIf(fn (): bool => $this->boolean('enabled')), 'nullable', Rule::in(array_keys($drivers))],
        ];

        $class = $drivers[(string) $this->input('driver')] ?? null;

        if ($class === null) {
            return $rules;
        }

        $saved = TenantIntegration::query()->where('channel', $channel)->where('driver', $class::key())->first();

        foreach ($class::fields() as $field) {
            $name = $field['name'];
            $secret = (bool) ($field['secret'] ?? false);
            $hasSavedSecret = $secret && isset(($saved?->secrets ?? [])[$name]);
            $required = (bool) ($field['required'] ?? false) && $this->boolean('enabled') && ! $hasSavedSecret;

            $rules['config.'.$name] = [
                $required ? 'required' : 'nullable',
                ...match ($field['type']) {
                    'number' => ['integer', 'min:1', 'max:99'],
                    'select' => [Rule::in(array_keys($field['options'] ?? []))],
                    default => ['string', 'max:255'],
                },
            ];
        }

        return $rules;
    }
}
