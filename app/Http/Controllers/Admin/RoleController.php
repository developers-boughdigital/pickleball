<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($search, fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->orderBy('id')
            ->get();

        $selected = Role::with('permissions')->find($request->integer('role'))
            ?? Role::with('permissions')->where('name', '!=', Role::SUPER_ADMIN)->orderBy('id')->first()
            ?? Role::with('permissions')->first();

        $grouped = PermissionMatrix::group(Permission::orderBy('id')->pluck('name'));

        return view('admin.roles.index', [
            'roles' => $roles,
            'selected' => $selected,
            'search' => $search,
            'modules' => $grouped,
            'actions' => PermissionMatrix::actions($grouped),
            'granted' => $selected?->permissions->pluck('name')->all() ?? [],
            'stats' => [
                'roles' => Role::count(),
                'assignedUsers' => DB::table(config('permission.table_names.model_has_roles'))->distinct()->count('model_id'),
                'permissions' => $grouped->flatten()->count(),
                'modules' => $grouped->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->form(new Role);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create([...$request->safe()->only('name', 'description'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()->route('admin.roles.index', ['role' => $role])->with('status', "Role “{$role->name}” created.");
    }

    public function edit(Role $role): View
    {
        return $this->form($role);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->safe()->only('name', 'description'));

        // The super admin always has everything, whatever was ticked.
        $role->syncPermissions($role->isProtected() ? Permission::all() : $request->validated('permissions', []));

        return redirect()->route('admin.roles.index', ['role' => $role])->with('status', "Role “{$role->name}” saved.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isProtected()) {
            return back()->with('status', "The {$role->name} role can’t be deleted.");
        }

        $users = $role->users()->count();
        $role->delete();

        $message = "Role “{$role->name}” deleted.";
        if ($users > 0) {
            $message .= " It was removed from {$users} ".str('user')->plural($users).'.';
        }

        return redirect()->route('admin.roles.index')->with('status', $message);
    }

    private function form(Role $role): View
    {
        $grouped = PermissionMatrix::group(Permission::orderBy('id')->pluck('name'));

        return view('admin.roles.form', [
            'role' => $role,
            'modules' => $grouped,
            'actions' => PermissionMatrix::actions($grouped),
            'granted' => old('permissions', $role->exists ? $role->permissions->pluck('name')->all() : []),
        ]);
    }
}
