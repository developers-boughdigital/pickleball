<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::withCount('roles')->orderBy('id')->get();

        return view('admin.permissions.index', [
            'modules' => $permissions
                ->filter(fn (Permission $p) => str_contains($p->name, '.'))
                ->groupBy(fn (Permission $p) => Str::before($p->name, '.')),
            'standardActions' => PermissionMatrix::ACTIONS,
        ]);
    }

    /**
     * Adds a module with the ticked standard actions and/or one custom action.
     * Re-adding an existing permission is harmless.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9-]*$/'],
            'actions' => ['array'],
            'actions.*' => ['string', 'in:'.implode(',', PermissionMatrix::ACTIONS)],
            'custom_action' => ['nullable', 'string', 'max:30', 'regex:/^[a-z][a-z0-9-]*$/'],
        ], [
            'module.regex' => 'Use lowercase letters, numbers and dashes, starting with a letter (e.g. club-night).',
            'custom_action.regex' => 'Use lowercase letters, numbers and dashes, starting with a letter (e.g. export).',
        ]);

        $actions = collect($data['actions'] ?? [])->push($data['custom_action'] ?? null)->filter()->unique();

        if ($actions->isEmpty()) {
            return back()->withInput()->withErrors(['actions' => 'Tick at least one action or enter a custom one.']);
        }

        $created = $actions->reject(fn (string $action) => Permission::where('name', "{$data['module']}.{$action}")->exists())
            ->each(fn (string $action) => Permission::create(['name' => "{$data['module']}.{$action}", 'guard_name' => 'web']));

        // The super admin always holds every permission.
        Role::where('name', Role::SUPER_ADMIN)->first()?->givePermissionTo(
            $created->map(fn (string $action) => "{$data['module']}.{$action}")->all()
        );

        $label = PermissionMatrix::label($data['module']);

        return redirect()->route('admin.permissions.index')->with('status', $created->isEmpty()
            ? "{$label} already had those permissions."
            : "Added {$created->count()} ".str('permission')->plural($created->count())." to {$label}.");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return back()->with('status', "Permission {$permission->name} deleted.");
    }

    public function destroyModule(string $module): RedirectResponse
    {
        $deleted = Permission::where('name', 'like', addcslashes($module, '%_').'.%')->get()->each->delete()->count();

        return redirect()->route('admin.permissions.index')->with('status', 'Deleted the '.PermissionMatrix::label($module)." module ({$deleted} ".str('permission')->plural($deleted).').');
    }
}
