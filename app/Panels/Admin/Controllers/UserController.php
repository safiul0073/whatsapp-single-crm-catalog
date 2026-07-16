<?php

namespace App\Panels\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Panels\Admin\Requests\StoreUserRequest;
use App\Panels\Admin\Requests\UpdateUserRequest;
use App\Panels\Admin\Tables\UsersTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index', 'show']),
            new Middleware('permission:users.create', only: ['create', 'store']),
            new Middleware('permission:users.edit', only: ['edit', 'update']),
            new Middleware('permission:users.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View|JsonResponse
    {
        $table = UsersTable::make();
        $query = User::query();

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
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate(15);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('components.tables.resource-rows', ['definition' => $table, 'items' => $users])->render(),
                'pagination' => view('components.tables.pagination', ['paginator' => $users])->render(),
                'total' => $users->total(),
            ]);
        }

        return view('panels.admin.users.index', compact('users', 'table'));
    }

    public function create(): View
    {
        return view('panels.admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->load(['workspaces', 'ownedWorkspaces', 'roles']);

        return view('panels.admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('panels.admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->filled('avatar')) {
            $data['avatar'] = $request->input('avatar');
        } elseif ($request->hasFile('avatar_file')) {
            $data['avatar'] = $request->file('avatar_file')->store('avatars', 'public');
        }

        $user->update($data);

        if ($request->boolean('email_verified_at') && ! $user->email_verified_at) {
            $user->markEmailAsVerified();
            Log::info('Admin manually verified user email', ['user_id' => $user->id, 'admin_id' => auth('admin')->id()]);
        } elseif (! $request->boolean('email_verified_at') && $user->email_verified_at) {
            $user->email_verified_at = null;
            $user->save();
            Log::info('Admin unverified user email', ['user_id' => $user->id, 'admin_id' => auth('admin')->id()]);
        }

        if ($request->boolean('phone_verified_at') && ! $user->phone_verified_at) {
            $user->phone_verified_at = now();
            $user->phone_verification_code = null;
            $user->save();
            Log::info('Admin manually verified user phone', ['user_id' => $user->id, 'admin_id' => auth('admin')->id()]);
        } elseif (! $request->boolean('phone_verified_at') && $user->phone_verified_at) {
            $user->phone_verified_at = null;
            $user->save();
            Log::info('Admin unverified user phone', ['user_id' => $user->id, 'admin_id' => auth('admin')->id()]);
        }

        if ($request->input('2fa_action') === 'disable') {
            $user->forceFill([
                'otp_two_factor_enabled' => false,
                'otp_two_factor_channel' => null,
            ])->save();
            Log::info('Admin disabled user 2FA', ['user_id' => $user->id, 'admin_id' => auth('admin')->id()]);
        } elseif ($request->input('2fa_action') === 'reset') {
            $user->forceFill([
                'otp_two_factor_enabled' => false,
                'otp_two_factor_channel' => null,
            ])->save();
            Log::info('Admin reset user 2FA', ['user_id' => $user->id, 'admin_id' => auth('admin')->id()]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "User {$status} successfully.");
    }
}
