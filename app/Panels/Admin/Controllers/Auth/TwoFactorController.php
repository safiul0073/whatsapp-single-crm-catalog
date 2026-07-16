<?php

namespace App\Panels\Admin\Controllers\Auth;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        protected Google2FA $google2fa,
    ) {}

    public function setup(): View
    {
        $admin = Auth::guard('admin')->user();

        $secret = $this->google2fa->generateSecretKey();

        session(['2fa_setup_secret' => $secret]);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'Admin Panel'),
            $admin->email,
            $secret,
        );

        $qrCodeSvg = $this->generateQrCodeSvg($qrCodeUrl);

        return view('panels.admin.profile.two-factor-setup', [
            'qrCodeSvg' => $qrCodeSvg,
            'secret' => $secret,
            'user' => $admin,
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|digits:6',
        ]);

        $secret = session('2fa_setup_secret');

        if (! $secret) {
            return back()->withErrors(['code' => __('Two-factor setup session has expired. Please try again.')]);
        }

        $isValid = $this->google2fa->verifyKey($secret, $request->input('code'));

        if (! $isValid) {
            return back()->withErrors(['code' => __('The provided code is invalid. Please try again.')]);
        }

        $admin = Auth::guard('admin')->user();
        $recoveryCodes = $this->generateRecoveryCodes();

        $admin->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        session()->forget('2fa_setup_secret');
        session(['2fa_verified' => true]);

        return redirect()->route('admin.profile.edit')
            ->with('success', __('Two-factor authentication has been enabled.'))
            ->with('recovery_codes', $recoveryCodes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $admin = Auth::guard('admin')->user();

        if (! Hash::check($request->input('password'), $admin->password)) {
            return back()->withErrors(['password' => __('The provided password is incorrect.')]);
        }

        $admin->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        session()->forget('2fa_verified');

        return redirect()->route('admin.profile.edit')
            ->with('success', __('Two-factor authentication has been disabled.'));
    }

    public function challenge(): View|RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        if (! $admin->hasTwoFactorEnabled() || ! $admin->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.dashboard');
        }

        if (session('2fa_verified')) {
            return redirect()->route('admin.dashboard');
        }

        return view('panels.admin.auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|digits:6',
        ]);

        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $isValid = $this->google2fa->verifyKey(
            $admin->two_factor_secret,
            $request->input('code'),
        );

        if (! $isValid) {
            return back()->withErrors(['code' => __('The provided two-factor code is invalid.')]);
        }

        session(['2fa_verified' => true]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function verifyRecoveryCode(Request $request): RedirectResponse
    {
        $request->validate([
            'recovery_code' => 'required|string',
        ]);

        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        $recoveryCodes = $admin->two_factor_recovery_codes ?? [];
        $inputCode = $request->input('recovery_code');

        if (! in_array($inputCode, $recoveryCodes)) {
            return back()->withErrors(['recovery_code' => __('The provided recovery code is invalid.')]);
        }

        $remainingCodes = array_values(array_filter($recoveryCodes, fn ($code) => $code !== $inputCode));

        $admin->update([
            'two_factor_recovery_codes' => $remainingCodes,
        ]);

        session(['2fa_verified' => true]);

        return redirect()->intended(route('admin.dashboard'));
    }

    protected function generateQrCodeSvg(string $qrCodeUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($qrCodeUrl);
    }

    /**
     * @return array<int, string>
     */
    protected function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        }

        return $codes;
    }
}
