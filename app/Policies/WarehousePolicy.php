<?php

namespace App\Policies;

class WarehousePolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'warehouses';
    }
}
