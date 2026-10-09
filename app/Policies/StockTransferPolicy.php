<?php

namespace App\Policies;

class StockTransferPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'inventory';
    }
}
