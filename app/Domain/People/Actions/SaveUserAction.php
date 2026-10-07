<?php

namespace App\Domain\People\Actions;

use App\Models\User;
use App\Support\Plans;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Creates or updates a shop user and their role, enforcing the plan's user limit and keeping one Owner. */
class SaveUserAction
{
    /** @param  array{name: string, email: string, role: string, password?: string|null}  $data */
    public function handle(?User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if ($user === null) {
                $limit = Plans::current()->limit('users');

                if ($limit !== null && User::query()->count() >= $limit) {
                    throw ValidationException::withMessages(['email' => __('plans.users_limit_reached', ['limit' => $limit])]);
                }

                $user = new User;
            } elseif ($user->hasRole('Owner') && $data['role'] !== 'Owner' && $this->isLastOwner($user)) {
                throw ValidationException::withMessages(['role' => __('users.last_owner')]);
            }

            $user->fill(['name' => $data['name'], 'email' => $data['email']]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles([$data['role']]);

            return $user;
        });
    }

    public function isLastOwner(User $user): bool
    {
        return $user->hasRole('Owner') && User::query()->role('Owner')->whereKeyNot($user->getKey())->doesntExist();
    }
}
