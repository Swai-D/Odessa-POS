<?php

namespace App\Policies;

use App\Models\User;

/** The audit trail is read-only, and only for people who hold `audit.view` (the Owner by default). */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin || $user->can('audit.view');
    }
}
