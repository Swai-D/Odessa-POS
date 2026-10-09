<?php

namespace App\Policies;

use App\Domain\Finance\Models\TillClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * `till.close` lets a cashier count and close their own till and see their own closings; `till.view` lets a
 * manager or accountant see everyone's.
 */
class TillClosingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->has($user, 'till.close') || $this->has($user, 'till.view');
    }

    public function view(User $user, Model $closing): bool
    {
        return $this->has($user, 'till.view')
            || ($this->has($user, 'till.close') && $closing instanceof TillClosing && $closing->user_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $this->has($user, 'till.close');
    }

    /** True for a user who may see every closing, not just their own. */
    public static function seesAll(User $user): bool
    {
        return $user->is_super_admin || $user->can('till.view');
    }

    private function has(User $user, string $permission): bool
    {
        return $user->is_super_admin || $user->can($permission);
    }
}
