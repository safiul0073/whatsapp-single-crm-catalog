<?php

namespace App\Modules\Staffs\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Modules\Staffs\Http\Requests\StoreStaffRequest;
use App\Modules\Staffs\Http\Requests\UpdateStaffRequest;
use App\Modules\Staffs\Tables\StaffsTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class StaffsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:staffs.view', only: ['index', 'show']),
            new Middleware('permission:staffs.create', only: ['create', 'store']),
            new Middleware('permission:staffs.edit', only: ['edit', 'update']),
            new Middleware('permission:staffs.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $table = StaffsTable::make();
        $query = Admin::query()
            ->with('roles');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        if (! in_array($sortBy, ['name', 'created_at'], true)) {
            $sortBy = 'created_at';
        }
        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }
        $query->orderBy($sortBy, $sortOrder);

        $staffs = $query->paginate(15);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('staffs::admin.staffs._table-rows', compact('staffs'))->render(),
                'pagination' => view('components.tables.pagination', ['paginator' => $staffs])->render(),
                'total' => $staffs->total(),
            ]);
        }

        return view('staffs::admin.staffs.index', compact('staffs', 'table'));
    }

    public function create(): View
    {
        $roles = $this->assignableRoles();

        return view('staffs::admin.staffs.create', compact('roles'));
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $staff = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $staff->syncRoles($request->input('roles', []));

        return redirect()
            ->route('admin.staffs.index')
            ->with('success', 'Staff created successfully.');
    }

    public function show(Admin $staff): View
    {
        $staff->load('roles');

        return view('staffs::admin.staffs.show', compact('staff'));
    }

    public function edit(Admin $staff): View
    {
        $staff->load('roles');
        $roles = $this->assignableRoles();

        return view('staffs::admin.staffs.edit', compact('staff', 'roles'));
    }

    public function update(UpdateStaffRequest $request, Admin $staff): RedirectResponse
    {
        $losesSuperAdmin = ! in_array('super-admin', $request->input('roles', []), true)
            || ! $request->boolean('is_active', true);
        if ($losesSuperAdmin && $this->wouldRemoveLastSuperAdmin([$staff->id])) {
            return back()->withInput()->with('error', 'At least one active super-admin is required.');
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $staff->update($data);
        $staff->syncRoles($request->input('roles', []));

        return redirect()
            ->route('admin.staffs.index')
            ->with('success', 'Staff updated successfully.');
    }

    public function destroy(Admin $staff): RedirectResponse
    {
        if ($staff->id === Auth::guard('admin')->id()) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($this->wouldRemoveLastSuperAdmin([$staff->id])) {
            return back()->with('error', 'At least one active super-admin is required.');
        }

        $staff->delete();

        return redirect()
            ->route('admin.staffs.index')
            ->with('success', 'Staff deleted successfully.');
    }

    public function toggleStatus(Admin $staff): RedirectResponse
    {
        if ($staff->is_active && $this->wouldRemoveLastSuperAdmin([$staff->id])) {
            return back()->with('error', 'At least one active super-admin is required.');
        }

        $staff->update([
            'is_active' => ! $staff->is_active,
        ]);

        $status = $staff->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Staff {$status} successfully.");
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        if ($this->wouldRemoveLastSuperAdmin($request->input('ids'))) {
            return response()->json(['message' => __('At least one active super-admin is required.')], 422);
        }

        $count = Admin::whereIn('id', $request->input('ids'))->delete();

        return response()->json([
            'message' => __(':count records deleted.', ['count' => $count]),
        ]);
    }

    public function bulkToggleStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $deactivating = Admin::query()->whereIn('id', $request->input('ids'))->where('is_active', true)->pluck('id')->all();
        if ($this->wouldRemoveLastSuperAdmin($deactivating)) {
            return response()->json(['message' => __('At least one active super-admin is required.')], 422);
        }

        $records = Admin::whereIn('id', $request->input('ids'))->get();
        foreach ($records as $record) {
            $record->update(['is_active' => ! $record->is_active]);
        }

        return response()->json([
            'message' => __(':count records updated.', ['count' => $records->count()]),
        ]);
    }

    protected function assignableRoles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether removing the given admins' super-admin access would leave no active super-admin.
     *
     * @param  array<int, int>  $adminIds
     */
    protected function wouldRemoveLastSuperAdmin(array $adminIds): bool
    {
        $remaining = Admin::query()
            ->where('is_active', true)
            ->whereNotIn('id', $adminIds)
            ->whereHas('roles', fn ($builder) => $builder->where('guard_name', 'admin')->where('name', 'super-admin'))
            ->exists();

        $affectsSuperAdmin = Admin::query()
            ->whereIn('id', $adminIds)
            ->whereHas('roles', fn ($builder) => $builder->where('guard_name', 'admin')->where('name', 'super-admin'))
            ->exists();

        return $affectsSuperAdmin && ! $remaining;
    }
}
