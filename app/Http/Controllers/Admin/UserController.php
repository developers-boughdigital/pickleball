<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $role = $request->string('role')->value();

        $users = User::query()
            ->with('roles')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role === 'none', fn ($query) => $query->doesntHave('roles'))
            ->when($role && $role !== 'none', fn ($query) => $query->role($role))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('id')->pluck('name');

        return view('admin.users.index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
            'roleOptions' => $roles->combine($roles)->put('none', 'No role')->all(),
            'stats' => [
                'total' => User::count(),
                'newThisMonth' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                'withRole' => DB::table(config('permission.table_names.model_has_roles'))->distinct()->count('model_id'),
                'roles' => $roles->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->form(new User);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only('name', 'email', 'password'));
        $user->syncRoles($request->validated('roles', []));

        ActivityLog::record('user.created', "Created user {$user->email}", $user, $user->name, [
            'roles' => ['from' => [], 'to' => $user->getRoleNames()->all()],
        ]);

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} created.");
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only('name', 'email'));
        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $changes = ActivityLog::diff($user, ['name', 'email']);
        if ($user->isDirty('password')) {
            $changes['password'] = ['from' => '••••', 'to' => 'changed'];
        }
        $user->save();

        $before = $user->getRoleNames()->sort()->values()->all();
        $user->syncRoles($request->validated('roles', []));
        $after = $user->fresh()->getRoleNames()->sort()->values()->all();
        if ($before !== $after) {
            $changes['roles'] = ['from' => $before, 'to' => $after];
        }

        if ($changes) {
            ActivityLog::record('user.updated', "Updated user {$user->email}", $user, $user->name, $changes);
        }

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} saved.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', 'You can’t delete your own account.');
        }

        ActivityLog::record('user.deleted', "Deleted user {$user->email}", $user, $user->name);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "User {$user->name} deleted.");
    }

    private function form(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::withCount('permissions')->orderBy('id')->get(),
            'assigned' => old('roles', $user->exists ? $user->getRoleNames()->all() : []),
        ]);
    }
}
