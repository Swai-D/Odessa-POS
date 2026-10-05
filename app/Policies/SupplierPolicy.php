<?php

namespace App\Policies;

class SupplierPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'suppliers';
    }
}
