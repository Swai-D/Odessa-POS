<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the shop's audit trail. Nothing is recorded outside a shop (platform work, console tasks with no
 * shop), and values that are secrets or only bookkeeping never reach the log.
 */
class AuditRecorder
{
    private const ALWAYS_SKIPPED = [
        'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'idempotency_key', 'tenant_id', 'id',
    ];

    /**
     * Records a change to an Eloquent model that uses the Auditable trait.
     *
     * @param  list<string>  $except  columns of this model that must never be logged
     */
    public function model(Model $model, string $event, string $label, array $except = []): void
    {
        $changes = match ($event) {
            'created' => $this->fields($model, $model->getAttributes(), [], $except),
            'updated' => $this->fields($model, $model->getChanges(), $model->getRawOriginal(), $except),
            default => [],
        };

        // An update that only touched bookkeeping columns is not worth a line.
        if ($event === 'updated' && $changes === []) {
            return;
        }

        $this->write($event, class_basename($model), $model->getKey(), $label, $changes);
    }

    /**
     * Records something that is not a plain model change, for example a user's role or a custom role.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function note(string $event, string $subjectType, int|string|null $subjectId, ?string $label, array $changes = []): void
    {
        $this->write($event, $subjectType, $subjectId, $label, $changes);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $original
     * @param  list<string>  $except
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function fields(Model $model, array $values, array $original, array $except): array
    {
        $skipped = [...self::ALWAYS_SKIPPED, ...$model->getHidden(), ...$except];
        $out = [];

        foreach ($values as $key => $value) {
            if (in_array($key, $skipped, true) || ($original === [] && $value === null)) {
                continue;
            }

            $out[$key] = ['old' => $this->clean($original[$key] ?? null), 'new' => $this->clean($value)];
        }

        return $out;
    }

    private function clean(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        return is_string($value) ? Str::limit($value, 200, '...') : $value;
    }

    /** @param  array<string, array{old: mixed, new: mixed}>  $changes */
    private function write(string $event, string $type, int|string|null $id, ?string $label, array $changes): void
    {
        if (app(TenantContext::class)->get() === null) {
            return;
        }

        $user = auth()->user();

        AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'user_name' => $user === null ? null : Str::limit((string) $user->name, 120, ''),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'event' => $event,
            'subject_type' => $type,
            'subject_id' => $id === null ? null : (string) $id,
            'subject_label' => $label === null ? null : Str::limit($label, 190, ''),
            'changes' => $changes === [] ? null : $changes,
        ]);
    }
}
