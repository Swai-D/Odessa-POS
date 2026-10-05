<?php

namespace App\Policies;

use App\Models\User;

/**
 * sales.view reads sales and receipts, sales.manage records later payments, and the separate
 * pos.access permission lets a user ring up sales at the till.
 */
class SalePolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'sales';
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin || $user->can('pos.access');
    }
}
