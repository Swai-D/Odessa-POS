<?php

namespace App\Models\Concerns;

use App\Domain\Settings\Services\AuditRecorder;

/**
 * Adds the record to the shop's audit trail when it is created, changed or deleted. Pair it with
 * BelongsToTenant. Override `auditLabel()` for a better description, `auditExcept()` for columns that must
 * never be logged and `shouldAudit()` to log only some events.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function (self $model) use ($event): void {
                if ($model->shouldAudit($event)) {
                    app(AuditRecorder::class)->model($model, $event, $model->auditLabel(), $model->auditExcept());
                }
            });
        }
    }

    /** What a person would call this record: its number, name or reference. */
    public function auditLabel(): string
    {
        foreach (['number', 'name', 'reference'] as $column) {
            $value = $this->getAttribute($column);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#'.$this->getKey();
    }

    /** @return list<string> */
    public function auditExcept(): array
    {
        return [];
    }

    public function shouldAudit(string $event): bool
    {
        return true;
    }
}
