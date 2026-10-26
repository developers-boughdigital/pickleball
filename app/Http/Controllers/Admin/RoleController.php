<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        return view('admin.roles.index', [
            'roles' => $roles,
            'search' => $search,
            'stats' => [
                'roles' => Role::count(),
                'assignedUsers' => DB::table(config('permission.table_names.model_has_roles'))->distinct()->count('model_id'),
                'permissions' => Permission::count(),
                'modules' => Permission::modules()->count(),
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

        ActivityLog::record('role.created', "Created role {$role->name}", $role, $role->name, [
            'permissions' => ['from' => [], 'to' => $role->permissions->pluck('name')->sort()->values()->all()],
        ]);

        return redirect()->route('admin.roles.index')->with('status', "Role “{$role->name}” created.");
    }

    public function edit(Role $role): View
    {
        return $this->form($role);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->fill($request->safe()->only('name', 'description'));
        $changes = ActivityLog::diff($role, ['name', 'description']);
        $role->save();

        $before = $role->permissions->pluck('name');

        // The super admin always has everything, whatever was ticked.
        $role->syncPermissions($role->isProtected() ? Permission::all() : $request->validated('permissions', []));

        $after = $role->fresh()->permissions->pluck('name');
        if ($added = $after->diff($before)->sort()->values()->all()) {
            $changes['permissions added'] = ['from' => null, 'to' => $added];
        }
        if ($removed = $before->diff($after)->sort()->values()->all()) {
            $changes['permissions removed'] = ['from' => $removed, 'to' => null];
        }

        if ($changes) {
            ActivityLog::record('role.updated', "Updated role {$role->name}", $role, $role->name, $changes);
        }

        return redirect()->route('admin.roles.index')->with('status', "Role “{$role->name}” saved.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isProtected()) {
            ActivityLog::record('role.deleted', "Tried to delete role {$role->name}", $role, $role->name, succeeded: false);

            return back()->with('status', "The {$role->name} role can’t be deleted.");
        }

        $users = $role->users()->count();
        ActivityLog::record('role.deleted', "Deleted role {$role->name}", $role, $role->name, $users ? [
            'people with this role' => ['from' => $users, 'to' => 0],
        ] : null);
        $role->delete();

        $message = "Role “{$role->name}” deleted.";
        if ($users > 0) {
            $message .= " It was removed from {$users} ".str('user')->plural($users).'.';
        }

        return redirect()->route('admin.roles.index')->with('status', $message);
    }

    private function form(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'modules' => Permission::query()
                ->orderByRaw('module is null')
                ->orderBy('module')
                ->orderBy('id')
                ->get()
                ->groupBy(fn (Permission $permission) => $permission->module ?? 'Other'),
            'granted' => old('permissions', $role->exists ? $role->permissions->pluck('name')->all() : []),
        ]);
    }
}
