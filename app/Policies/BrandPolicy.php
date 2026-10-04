<?php

namespace App\Policies;

class BrandPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'brands';
    }
}
