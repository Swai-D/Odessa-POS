<?php

namespace App\Policies;

class ExpensePolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'expenses';
    }
}
