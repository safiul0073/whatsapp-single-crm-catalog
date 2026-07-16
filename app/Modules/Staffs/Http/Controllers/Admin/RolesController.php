<?php

namespace App\Modules\Staffs\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Staffs\Http\Requests\StoreRoleRequest;
use App\Modules\Staffs\Http\Requests\UpdateRoleRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller implements HasMiddleware
{
    protected const HIDDEN_ROLE_NAMES = ['super-admin'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index']),
            new Middleware('permission:roles.create', only: ['create', 'store']),
            new Middleware('permission:roles.edit', only: ['edit', 'update']),
            new Middleware('permission:roles.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $query = $this->adminRolesQuery()->withCount('users');

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        if (! in_array($sortBy, ['name', 'created_at'], true)) {
            $sortBy = 'name';
        }
        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        $roles = $query->orderBy($sortBy, $sortOrder)->paginate(15);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('staffs::admin.roles._table-rows', compact('roles'))->render(),
                'pagination' => view('components.tables.pagination', ['paginator' => $roles])->render(),
                'total' => $roles->total(),
            ]);
        }

        return view('staffs::admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::where('guard_name', 'admin')->get()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return view('staffs::admin.roles.create', compact('permissions'));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'admin',
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $this->ensureManageableRole($role);

        $role->load('permissions');
        $permissions = Permission::where('guard_name', 'admin')->get()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return view('staffs::admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureManageableRole($role);

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureManageableRole($role);

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Cannot delete role with assigned users.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    protected function adminRolesQuery(): Builder
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->whereNotIn('name', self::HIDDEN_ROLE_NAMES);
    }

    protected function ensureManageableRole(Role $role): void
    {
        abort_unless(
            $role->guard_name === 'admin' && ! in_array($role->name, self::HIDDEN_ROLE_NAMES, true),
            404
        );
    }
}
