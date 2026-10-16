<?php

namespace App\Console\Commands;

use App\Domain\Settings\Services\CustomDomainService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckCustomDomains extends Command
{
    protected $signature = 'domains:check';

    protected $description = 'Record whether each shop-owned custom domain points at this app';

    public function handle(CustomDomainService $domains): int
    {
        $working = 0;

        Tenant::query()->whereNotNull('domain')->whereNotNull('domain_verified_at')->each(function (Tenant $tenant) use ($domains, &$working): void {
            if ($domains->refreshRouting($tenant)) {
                $working++;
            }
        });

        $this->info("{$working} domain(s) point at this app.");

        return self::SUCCESS;
    }
}
