<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\BusinessStatement;
use App\Domain\Finance\Services\BusinessStatementBuilder;
use App\Domain\Sales\Services\DocumentNumber;
use App\Domain\Settings\Services\AuditRecorder;
use App\Models\User;
use App\Support\TenantSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Issues a business statement: figures taken now, a number and a verification code, sealed with a checksum. */
class GenerateBusinessStatementAction
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly BusinessStatementBuilder $builder,
        private readonly DocumentNumber $numbers,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(int $months, string $locale, ?User $user): BusinessStatement
    {
        $built = $this->builder->handle($months, Carbon::now());
        $settings = new TenantSettings;
        $tenant = app(TenantContext::class)->get();

        return DB::transaction(function () use ($built, $months, $locale, $user, $settings, $tenant): BusinessStatement {
            $statement = new BusinessStatement([
                'user_id' => $user?->getKey(),
                'number' => $this->numbers->next('business_statement', 'BS-'),
                'code' => $this->newCode(),
                'shop_name' => (string) $settings->get('business_name', $tenant->name ?? config('app.name')),
                'currency' => (string) $settings->get('currency', config('pos.default_currency')),
                'locale' => $locale,
                'months' => $months,
                'period_from' => $built['from']->format('Y-m-d'),
                'period_to' => $built['to']->format('Y-m-d'),
                'data' => $built['data'],
                'generated_at' => Carbon::now()->startOfSecond(),
            ]);
            $statement->tenant_id = $tenant?->getKey();
            $statement->checksum = $statement->computeChecksum();
            $statement->save();

            $this->audit->note('generated', 'BusinessStatement', $statement->getKey(), $statement->number);

            return $statement;
        });
    }

    private function newCode(): string
    {
        do {
            $raw = '';
            for ($i = 0; $i < 12; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = BusinessStatement::normalizeCode($raw);
        } while (BusinessStatement::query()->withoutGlobalScope('tenant')->where('code', $code)->exists());

        return $code;
    }
}
