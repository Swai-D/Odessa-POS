<?php

namespace App\Policies;

class ExpenseCategoryPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'expenses';
    }
}
