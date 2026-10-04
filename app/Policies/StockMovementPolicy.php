<?php

namespace App\Policies;

class StockMovementPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'inventory';
    }
}
