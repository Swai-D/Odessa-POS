<?php

namespace App\Policies;

class ProductPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'products';
    }
}
