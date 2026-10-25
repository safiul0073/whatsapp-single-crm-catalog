<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use App\Modules\Commerce\Storefront\Http\Requests\AuthRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\ReferralService;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private WhatsAppCustomerAuthService $whatsapp, private CustomerService $customers, private ReferralService $referrals) {}

    public function status(Request $r): JsonResponse
    {
        $id = $r->attributes->get('store_workspace')->id;

        return response()->json(['data' => ['whatsapp' => $this->whatsapp->available($id), 'password' => $this->passwordEnabled($id)]]);
    }

    public function challenge(AuthRequest $r): JsonResponse
    {
        $data = $r->validated();
        $data['client_ip'] = $r->header('X-Storefront-Client-IP') ?: $r->ip();

        return response()->json(['data' => $this->whatsapp->challenge($r->attributes->get('store_workspace')->id, $data)]);
    }

    public function resend(AuthRequest $r, string $id): JsonResponse
    {
        WhatsAppAuthChallenge::query()->where('workspace_id', $r->attributes->get('store_workspace')->id)->where('session_hash', hash('sha256', $r->input('session_binding')))->where('phone', $r->input('phone'))->findOrFail($id);

        return $this->challenge($r);
    }

    public function verify(AuthRequest $r, string $id): JsonResponse
    {
        $workspace = $r->attributes->get('store_workspace')->id;
        $result = $this->whatsapp->verify($workspace, $id, $r->validated());
        $contact = Contact::query()->where('workspace_id', $workspace)->where('phone', $result['phone'])->first();
        if (! $contact) {
            return response()->json(['data' => ['registration_grant' => $result['grant'], 'challenge_id' => $id]]);
        }
        abort_if($contact->blocked_at, 403);

        return response()->json(['data' => DB::transaction(function () use ($workspace, $id, $r, $result, $contact): array {
            $this->consume($workspace, $id, $r->input('session_binding'), $result['grant']);

            return $this->customers->session($this->enroll($contact, $r->input('referral_code')), true);
        })]);
    }

    public function register(AuthRequest $r): JsonResponse
    {
        $workspace = $r->attributes->get('store_workspace')->id;
        $data = $r->validated();
        $result = $this->whatsapp->register($workspace, [...$data, 'grant' => $data['registration_grant'], 'registration_reference' => (string) Str::uuid(), 'customer_reference' => (string) Str::uuid(), 'new_customer' => true]);
        $contact = Contact::query()->where('workspace_id', $workspace)->findOrFail($result['contact_id']);
        abort_if($contact->blocked_at, 403);

        return response()->json(['data' => $this->customers->session($this->enroll($contact, $data['referral_code'] ?? null), true)], 201);
    }

    public function passwordLogin(AuthRequest $r): JsonResponse
    {
        $workspace = $r->attributes->get('store_workspace')->id;
        abort_unless($this->passwordEnabled($workspace), 403, 'Password login is disabled.');
        $key = 'storefront:password:'.$workspace.':'.hash('sha256', $r->input('phone'));
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429);
        RateLimiter::hit($key, 300);
        $customer = Customer::query()->where('workspace_id', $workspace)->where('verified_phone', $r->input('phone'))->whereNotNull('verified_at')->first();
        $hash = $customer?->password ?: '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = Hash::check($r->input('password'), $hash);
        abort_unless($valid && $customer && $customer->password && $customer->contact->phone === $customer->verified_phone && ! $customer->contact->blocked_at, 422, 'Invalid login details.');
        RateLimiter::clear($key);

        return response()->json(['data' => $this->customers->session($customer)]);
    }

    public function password(AuthRequest $r): JsonResponse
    {
        $customer = $r->attributes->get('store_customer');
        $token = $customer->currentAccessToken();
        abort_unless($this->passwordEnabled($customer->workspace_id) && $token->can('password:verified') && $token->created_at->gt(now()->subMinutes(10)), 403, 'Verify your WhatsApp number again before setting a password.');
        $customer->update(['password' => $r->validated('password')]);
        $customer->tokens()->delete();

        return response()->json(['data' => $this->customers->session($customer)]);
    }

    public function logout(Request $r): JsonResponse
    {
        $r->attributes->get('store_customer')->currentAccessToken()->delete();

        return response()->json(['data' => ['success' => true]]);
    }

    /**
     * Enroll the contact and attach a referral only when this is the customer's first registration in the store.
     */
    private function enroll(Contact $contact, ?string $referralCode): Customer
    {
        $isNew = ! Customer::query()->where('workspace_id', $contact->workspace_id)->where('contact_id', $contact->id)->whereNotNull('registered_at')->exists();
        $customer = $this->customers->enroll($contact, true);
        if ($isNew && $referralCode) {
            $this->referrals->attach($customer, $referralCode);
        }

        return $customer;
    }

    private function passwordEnabled(int $workspace): bool
    {
        return (bool) (StorefrontSetting::query()->where('workspace_id', $workspace)->first()?->config['password_login'] ?? false);
    }

    private function consume(int $workspace, string $id, string $binding, string $grant): void
    {
        $c = WhatsAppAuthChallenge::query()->where('workspace_id', $workspace)->lockForUpdate()->findOrFail($id);
        abort_unless(! $c->consumed_at && ! $c->invalidated_at && $c->grant_expires_at?->isFuture() && hash_equals($c->session_hash, hash('sha256', $binding)) && hash_equals($c->grant_hash, hash('sha256', $grant)), 422, 'Verification expired.');
        $c->update(['consumed_at' => now()]);
    }
}
