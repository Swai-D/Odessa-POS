<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Domain\Settings\Actions\SaveRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/** Custom roles (Enterprise). The default roles are listed for reference but cannot be changed. */
class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-settings');

        $roles = Role::query()
            ->where('tenant_id', app(TenantContext::class)->get()?->getKey())
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $groups = [];
        foreach (ProvisionTenantAction::PERMISSIONS as $permission) {
            [$area, $action] = explode('.', $permission, 2);
            $groups[$area][] = ['name' => $permission, 'action' => $action];
        }

        return view('settings.roles.index', [
            'roles' => $roles,
            'system' => SaveRoleAction::systemRoleNames(),
            'groups' => $groups,
        ]);
    }

    public function store(RoleRequest $request, SaveRoleAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $action->handle(null, $request->validated());

        return redirect()->route('roles.index')->with('status', __('app.saved'));
    }

    public function update(RoleRequest $request, string $role, SaveRoleAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $action->handle(SaveRoleAction::find($role) ?? abort(404), $request->validated());

        return redirect()->route('roles.index')->with('status', __('app.saved'));
    }

    public function destroy(string $role): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $record = SaveRoleAction::find($role) ?? abort(404);

        if ($record->users()->exists()) {
            return redirect()->route('roles.index')->withErrors(['delete' => __('roles.in_use')]);
        }

        $record->delete();

        return redirect()->route('roles.index')->with('status', __('app.deleted'));
    }
}
