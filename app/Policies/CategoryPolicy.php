<?php

namespace App\Policies;

class CategoryPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'categories';
    }
}
