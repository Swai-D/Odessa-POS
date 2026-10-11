<?php

namespace App\Http\Requests;

use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Domain\Settings\Actions\SaveRoleAction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the manage-settings gate in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');
        $id = $role instanceof Role ? $role->getKey() : (is_scalar($role) ? $role : null);

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::notIn(SaveRoleAction::systemRoleNames()),
                $this->unique($id),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(ProvisionTenantAction::PERMISSIONS)],
        ];
    }

    private function unique(int|string|null $ignore): Unique
    {
        return Rule::unique('roles', 'name')
            ->where('tenant_id', app(TenantContext::class)->get()?->getKey())
            ->ignore($ignore);
    }
}
