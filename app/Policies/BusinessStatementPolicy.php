<?php

namespace App\Policies;

use App\Models\User;

/** Issuing and reading business statements needs the reports permission; there is no separate "manage". */
class BusinessStatementPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'reports';
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'view');
    }
}
