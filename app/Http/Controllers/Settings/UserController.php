<?php

namespace App\Http\Controllers\Settings;

use App\Domain\People\Actions\SaveUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Support\Plans;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Shop staff management. Needs the settings permission, so it is the owner's page. */
class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-settings');

        return view('settings.users.index', [
            'users' => User::query()->with('roles')->orderBy('name')->get(),
            'roles' => UserRequest::roleNames(),
            'limit' => Plans::current()->limit('users'),
        ]);
    }

    public function store(UserRequest $request, SaveUserAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $action->handle(null, $request->validated());

        return redirect()->route('users.index')->with('status', __('app.saved'));
    }

    public function update(UserRequest $request, User $user, SaveUserAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $action->handle($user, $request->validated());

        return redirect()->route('users.index')->with('status', __('app.saved'));
    }

    public function destroy(User $user, SaveUserAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        if ($user->is(auth()->user())) {
            return redirect()->route('users.index')->withErrors(['delete' => __('users.cannot_delete_self')]);
        }

        if ($action->isLastOwner($user)) {
            return redirect()->route('users.index')->withErrors(['delete' => __('users.last_owner')]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', __('app.deleted'));
    }
}
