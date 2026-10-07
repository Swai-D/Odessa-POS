<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the manage-settings gate in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user instanceof User ? $user->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in(self::roleNames())],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
        ];
    }

    /** @return list<string> */
    public static function roleNames(): array
    {
        return Role::query()
            ->where('tenant_id', app(TenantContext::class)->get()?->getKey())
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
