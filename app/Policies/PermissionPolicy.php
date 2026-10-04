<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy: access is decided purely by two permissions per area, e.g. `products.view`
 * and `products.manage`. Tenant isolation itself is enforced by the BelongsToTenant scope.
 */
abstract class PermissionPolicy
{
    /** Permission prefix, e.g. "products". */
    abstract protected function area(): string;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allows($user, 'manage');
    }

    protected function allows(User $user, string $ability): bool
    {
        return $user->is_super_admin || $user->can($this->area().'.'.$ability);
    }
}
