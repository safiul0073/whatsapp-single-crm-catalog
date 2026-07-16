<?php

namespace App\Http\Controllers\Auth;

use App\Enums\NotificationTemplateSlug;
use App\Events\UserAutoNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Admin;
use App\Models\User;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Services\Onboarding\OnboardingProgress;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected SystemNotificationService $notifications,
        protected OnboardingProgress $onboarding,
    ) {}

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        Role::findOrCreate('user', 'web');

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        // Assign the hidden default web role
        $user->syncRoles(['user']);

        // Log the registration
        $this->auditLogService->logCustom('register', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        event(new Registered($user));
        event(new UserAutoNotification($user, NotificationTemplateSlug::WELCOME));

        $admins = Admin::where('is_active', true)->get();
        $this->notifications->sendToMany($admins, [
            'title' => __('New user registered'),
            'body' => $user->name.' ('.$user->email.')',
            'icon' => 'ph-user-circle-plus',
            'url' => route('admin.users.show', $user),
            'type' => 'info',
        ], 'new_user');

        Auth::login($user);

        return $this->onboarding->redirect($user)
            ->with('success', __('Registration successful! Please check your email to verify your account.'));
    }
}
