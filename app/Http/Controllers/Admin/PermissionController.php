<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $module = $request->string('module')->value();

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('module', 'like', "%{$search}%")))
            ->when($module, fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        $modules = Permission::modules();

        return view('admin.permissions.index', [
            'permissions' => $permissions,
            'modules' => $modules->combine($modules)->all(),
            'filters' => ['search' => $search, 'module' => $module],
            'total' => Permission::count(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Permission(['module' => $request->string('module')->value() ?: null]));
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::create([...$request->validated(), 'guard_name' => 'web']);

        // The super admin always holds every permission.
        Role::where('name', Role::SUPER_ADMIN)->first()?->givePermissionTo($permission);

        ActivityLog::record('permission.created', "Added permission {$permission->name}", $permission, $permission->name, [
            'module' => ['from' => null, 'to' => $permission->module],
            'purpose' => ['from' => null, 'to' => $permission->description],
        ]);

        return redirect()->route('admin.permissions.index')->with('status', "Permission {$permission->name} created.");
    }

    public function edit(Permission $permission): View
    {
        return $this->form($permission);
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->fill($request->validated());
        $changes = ActivityLog::diff($permission, ['module', 'name', 'description']);
        $permission->save();

        if ($changes) {
            ActivityLog::record('permission.updated', "Updated permission {$permission->name}", $permission, $permission->name, $changes);
        }

        return redirect()->route('admin.permissions.index')->with('status', "Permission {$permission->name} saved.");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $roles = $permission->roles()->count();
        ActivityLog::record('permission.deleted', "Deleted permission {$permission->name}", $permission, $permission->name, $roles ? [
            'roles with it' => ['from' => $permission->roles()->pluck('name')->all(), 'to' => null],
        ] : null);
        $permission->delete();

        $message = "Permission {$permission->name} deleted.";
        if ($roles > 0) {
            $message .= " It was removed from {$roles} ".str('role')->plural($roles).'.';
        }

        return redirect()->route('admin.permissions.index')->with('status', $message);
    }

    private function form(Permission $permission): View
    {
        return view('admin.permissions.form', [
            'permission' => $permission,
            'modules' => Permission::modules(),
        ]);
    }
}
