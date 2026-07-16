<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AuthApi\Services\OtpDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(protected OtpDeliveryService $otpDeliveryService) {}

    public function setup(Request $request): View|RedirectResponse
    {
        $panel = $this->getPanel();
        $user = $this->getUser($panel);

        if (! $this->is2faEnabled($panel)) {
            return redirect()->route("{$panel['key']}.profile.edit");
        }

        return view("panels.{$panel['key']}.profile.two-factor-setup", [
            'user' => $user,
            'channels' => $this->availableChannels($user),
            'pendingDelivery' => session('2fa_setup_delivery'),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $panel = $this->getPanel();

        if (! $this->is2faEnabled($panel)) {
            return redirect()->route("{$panel['key']}.profile.edit");
        }

        $user = $this->getUser($panel);

        if ($request->filled('code')) {
            $request->validate(['code' => ['required', 'string', 'digits:6']]);

            $pendingDelivery = session('2fa_setup_delivery');

            if (! is_array($pendingDelivery)) {
                return back()->withErrors(['code' => __('Two-factor setup session has expired. Please try again.')]);
            }

            $this->otpDeliveryService->verify(
                $pendingDelivery['channel'],
                $pendingDelivery['destination'],
                $request->string('code')->toString(),
            );

            $user->forceFill([
                'otp_two_factor_enabled' => true,
                'otp_two_factor_channel' => $pendingDelivery['channel'],
            ])->save();

            session()->forget('2fa_setup_delivery');
            session(['2fa_verified' => true]);

            return redirect()->route("{$panel['key']}.profile.edit")
                ->with('success', __('Two-factor authentication has been enabled.'));
        }

        $request->validate([
            'channel' => ['required', 'string', 'in:email,sms,phone'],
        ]);

        $delivery = $this->otpDeliveryService->destinationFor($request, $user, $request->string('channel')->toString());

        $this->otpDeliveryService->send(
            channel: $delivery['channel'],
            destination: $delivery['destination'],
            purpose: 'two-factor setup',
        );

        session([
            '2fa_setup_delivery' => [
                'channel' => $delivery['channel'],
                'destination' => $delivery['destination'],
                'masked_destination' => $this->otpDeliveryService->mask($delivery['channel'], $delivery['destination']),
            ],
        ]);

        return redirect()->route("{$panel['key']}.two-factor.setup")
            ->with('success', __('We sent a verification code to :destination.', [
                'destination' => $this->otpDeliveryService->mask($delivery['channel'], $delivery['destination']),
            ]));
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $panel = $this->getPanel();
        $user = $this->getUser($panel);

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => __('The provided password is incorrect.')]);
        }

        $user->update([
            'otp_two_factor_enabled' => false,
            'otp_two_factor_channel' => null,
        ]);

        session()->forget('2fa_verified');

        return redirect()->route("{$panel['key']}.profile.edit")
            ->with('success', __('Two-factor authentication has been disabled.'));
    }

    public function challenge(): View|RedirectResponse
    {
        $panel = $this->getPanel();
        $user = $this->getUser($panel);

        if (! $user) {
            return redirect()->route("{$panel['key']}.login");
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route("{$panel['key']}.dashboard");
        }

        if (session('2fa_verified')) {
            return redirect()->route("{$panel['key']}.dashboard");
        }

        $panelKey = $panel['key'];
        $delivery = $this->otpDeliveryService->destinationFor(request(), $user, $user->otp_two_factor_channel);

        if (session('2fa_challenge_user') !== $user->getKey() || request()->boolean('resend')) {
            $this->otpDeliveryService->send(
                channel: $delivery['channel'],
                destination: $delivery['destination'],
            );

            session([
                '2fa_challenge_user' => $user->getKey(),
                '2fa_challenge_delivery' => $delivery,
            ]);
        }

        $maskedDestination = $this->otpDeliveryService->mask($delivery['channel'], $delivery['destination']);

        return view('auth.two-factor-challenge', compact('panelKey', 'maskedDestination'));
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|digits:6',
        ]);

        $panel = $this->getPanel();
        $user = $this->getUser($panel);

        if (! $user) {
            return redirect()->route("{$panel['key']}.login");
        }

        $delivery = session('2fa_challenge_delivery')
            ?: $this->otpDeliveryService->destinationFor($request, $user, $user->otp_two_factor_channel);

        $this->otpDeliveryService->verify(
            $delivery['channel'],
            $delivery['destination'],
            $request->string('code')->toString(),
        );

        session(['2fa_verified' => true]);
        session()->forget(['2fa_challenge_user', '2fa_challenge_delivery']);

        return redirect()->intended(route("{$panel['key']}.dashboard"));
    }

    public function verifyRecoveryCode(Request $request): RedirectResponse
    {
        $panel = $this->getPanel();

        return redirect()->route("{$panel['key']}.two-factor.challenge")
            ->withErrors(['code' => __('Recovery codes are not available for email or phone two-factor authentication.')]);
    }

    /**
     * Get current panel configuration.
     *
     * @return array{key: string, guard: string}
     */
    protected function getPanel(): array
    {
        $panel = app('current.panel');

        if (! $panel) {
            return ['key' => 'admin', 'guard' => 'admin'];
        }

        return $panel;
    }

    /**
     * Get authenticated user for the current panel guard.
     */
    protected function getUser(array $panel): mixed
    {
        return Auth::guard($panel['guard'] ?? 'web')->user();
    }

    /**
     * Check if 2FA is enabled for the given panel.
     */
    protected function is2faEnabled(array $panel): bool
    {
        if (($panel['guard'] ?? 'web') === 'admin') {
            return true; // Always available for admins
        }

        return (bool) setting('enable_2fa_for_users', true);
    }

    /**
     * @return array<string, array{label: string, destination: string, verified: bool}>
     */
    protected function availableChannels(User $user): array
    {
        return [
            'email' => [
                'label' => __('Email'),
                'destination' => $user->email ? $this->otpDeliveryService->mask('email', $user->email) : __('Not added'),
                'verified' => filled($user->email_verified_at),
            ],
            'sms' => [
                'label' => __('Phone'),
                'destination' => $user->phone ? $this->otpDeliveryService->mask('sms', $user->phone) : __('Not added'),
                'verified' => filled($user->phone_verified_at),
            ],
        ];
    }
}
