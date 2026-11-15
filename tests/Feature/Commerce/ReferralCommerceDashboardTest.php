<?php

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\StoreFeatures;
use App\Modules\Commerce\Storefront\Models\CashinRequest;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\ReferralSetting;
use App\Modules\Commerce\Storefront\Models\RefundRequest;
use App\Modules\Commerce\Storefront\Services\RefundRequestService;
use App\Modules\Commerce\Storefront\Services\RewardWalletService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceResolver::class)->current($this->owner);
    StoreOrderSetting::forWorkspace($this->workspace->id);
});

function makeReferralWorkspace($test): void
{
    $test->workspace->update(['settings' => [...(array) $test->workspace->settings, 'business_type' => 'referral']]);
    StoreFeatures::flush();
}

it('treats every store as traditional until the owner picks another type', function (): void {
    expect(StoreFeatures::type($this->workspace->id))->toBe('traditional')
        ->and(StoreFeatures::typeHas($this->workspace->id, 'referral'))->toBeFalse();

    $this->actingAs($this->owner)->get(route('user.commerce.refunds.index'))->assertNotFound();
    $this->actingAs($this->owner)->get(route('user.commerce.withdrawals.index'))->assertNotFound();
});

it('lets the owner switch the store type from storefront settings without losing other settings', function (): void {
    $this->workspace->update(['settings' => ['commerce' => ['currency' => 'BDT']]]);
    $config = ['name' => 'Shop', 'primary' => '#0866FF', 'hover' => '#0055D4', 'footer_color' => '#0A2540'];

    $this->actingAs($this->owner)->put(route('user.commerce.storefront.settings'), ['config' => $config, 'business_type' => 'spaceship'])->assertSessionHasErrors('business_type');
    $this->actingAs($this->owner)->put(route('user.commerce.storefront.settings'), ['config' => $config, 'business_type' => 'referral'])->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->settings)->toMatchArray(['commerce' => ['currency' => 'BDT'], 'business_type' => 'referral'])
        ->and(StoreFeatures::typeHas($this->workspace->id, 'referral'))->toBeTrue();
    $this->actingAs($this->owner)->get(route('user.commerce.refunds.index'))->assertOk();
    $this->actingAs($this->owner)->get(route('user.commerce.withdrawals.index'))->assertOk()->assertSee('Asia/Dhaka');
});

it('saves the withdrawal schedule and rejects a window that closes before it opens', function (): void {
    makeReferralWorkspace($this);
    $schedule = ['cashin_enabled' => '1', 'withdrawal_frequency' => 'weekly', 'withdrawal_weekly_day' => 4, 'withdrawal_monthly_day' => 10, 'withdrawal_opens_at' => '10:00', 'withdrawal_closes_at' => '16:00', 'min_cashin_balance' => 100, 'max_cashin_amount' => 5000, 'cashin_methods' => ['bkash', 'bank']];

    $this->actingAs($this->owner)->put(route('user.commerce.withdrawals.schedule'), [...$schedule, 'withdrawal_closes_at' => '09:00'])->assertSessionHasErrors('withdrawal_closes_at');
    $this->actingAs($this->owner)->put(route('user.commerce.withdrawals.schedule'), [...$schedule, 'cashin_methods' => ['paypal']])->assertSessionHasErrors('cashin_methods.0');
    $this->actingAs($this->owner)->put(route('user.commerce.withdrawals.schedule'), $schedule)->assertSessionHasNoErrors();

    $settings = ReferralSetting::query()->where('workspace_id', $this->workspace->id)->firstOrFail();
    expect($settings->cashin_enabled)->toBeTrue()
        ->and($settings->withdrawal_frequency)->toBe('weekly')
        ->and($settings->withdrawal_weekly_day)->toBe(4)
        ->and($settings->withdrawal_closes_at)->toBe('16:00')
        ->and($settings->cashin_methods)->toBe(['bkash', 'bank']);
});

it('lets the owner settle a withdrawal and shows only this store\'s refund requests', function (): void {
    makeReferralWorkspace($this);
    ReferralSetting::query()->create(['workspace_id' => $this->workspace->id, 'enabled' => true, 'cashin_enabled' => true, 'refunds_enabled' => true]);
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Rahim', 'phone' => '+8801700000001', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $customer = Customer::query()->create(['workspace_id' => $this->workspace->id, 'contact_id' => $contact->id, 'verified_phone' => '+8801700000001', 'verified_at' => now(), 'registered_at' => now()]);
    $wallet = app(RewardWalletService::class);
    $wallet->post($this->workspace->id, $customer->id, 5000, 'adjustment');
    $request = CashinRequest::query()->create(['workspace_id' => $this->workspace->id, 'customer_id' => $customer->id, 'amount_minor' => 2000, 'currency' => 'USD', 'method' => 'bkash', 'account_details' => '01700000001', 'status' => 'pending']);
    $wallet->post($this->workspace->id, $customer->id, -2000, 'cashin_hold', 'cashin-hold:'.$request->id, $request);

    $this->actingAs($this->owner)->put(route('user.commerce.withdrawals.update', $request->id), ['status' => 'paid', 'payout_reference' => 'TX-1'])->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe('paid')
        ->and($request->fresh()->payout_reference)->toBe('TX-1')
        ->and($wallet->balance($this->workspace->id, $customer->id))->toBe(3000);
    $this->actingAs($this->owner)->put(route('user.commerce.withdrawals.update', $request->id), ['status' => 'rejected'])->assertStatus(422);
    expect($wallet->balance($this->workspace->id, $customer->id))->toBe(3000);
    $this->actingAs($this->owner)->get(route('user.commerce.withdrawals.export'))->assertOk();

    $stranger = User::factory()->create();
    app(WorkspaceResolver::class)->current($stranger)->update(['settings' => ['business_type' => 'referral']]);
    StoreFeatures::flush();
    $this->actingAs($stranger)->put(route('user.commerce.withdrawals.update', $request->id), ['status' => 'approved'])->assertNotFound();
    $this->actingAs($stranger)->get(route('user.commerce.refunds.show', 999))->assertNotFound();
    expect(RefundRequest::query()->count())->toBe(0);
});

it('shows a refund request to the owner and completes it by manual payout', function (): void {
    makeReferralWorkspace($this);
    ReferralSetting::query()->create(['workspace_id' => $this->workspace->id, 'enabled' => true, 'refunds_enabled' => true]);
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->workspace->id, 'name' => 'Rahim', 'phone' => '+8801700000001', 'source' => 'website', 'opt_in_status' => 'unknown']));
    $customer = Customer::query()->create(['workspace_id' => $this->workspace->id, 'contact_id' => $contact->id, 'verified_phone' => '+8801700000001', 'verified_at' => now(), 'registered_at' => now()]);
    $order = Order::query()->create(['workspace_id' => $this->workspace->id, 'contact_id' => $contact->id, 'number' => 'ORD-1', 'status' => 'completed', 'payment_state' => 'paid', 'currency' => 'USD', 'subtotal' => 100, 'total' => 100, 'submission_reference' => (string) Str::uuid()]);
    $refund = app(RefundRequestService::class)->submit($customer, $order, ['amount' => '40', 'reason' => 'damaged', 'details' => 'Broken zip'], (string) Str::uuid());
    expect($refund->status)->toBe('awaiting_admin');

    $this->actingAs($this->owner)->get(route('user.commerce.refunds.index'))->assertOk()->assertSee($refund->reference);
    $this->actingAs($this->owner)->get(route('user.commerce.refunds.show', $refund->id))->assertOk()->assertSee('Broken zip')->assertSee('Approve refund');
    $this->actingAs($this->owner)->put(route('user.commerce.refunds.transition', $refund->id), ['status' => 'completed', 'payout_method' => 'wallet'])->assertStatus(422);
    $this->actingAs($this->owner)->put(route('user.commerce.refunds.transition', $refund->id), ['status' => 'processing'])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->put(route('user.commerce.refunds.transition', $refund->id), ['status' => 'completed', 'payout_method' => 'manual'])->assertSessionHasErrors(['payout_channel', 'payout_reference']);
    $this->actingAs($this->owner)->put(route('user.commerce.refunds.transition', $refund->id), ['status' => 'completed', 'payout_method' => 'manual', 'payout_channel' => 'nagad', 'payout_reference' => 'NG-7'])->assertSessionHasNoErrors();

    expect($refund->fresh()->status)->toBe('completed')
        ->and($order->fresh()->payment_state)->toBe('partially_refunded')
        ->and($order->fresh()->refunded_amount)->toEqual('40.0000')
        ->and(app(RewardWalletService::class)->balance($this->workspace->id, $customer->id))->toBe(0);
    $this->actingAs($this->owner)->get(route('user.commerce.refunds.show', $refund->id))->assertOk()->assertSee('NG-7')->assertSee('This request is closed.');
    $this->actingAs($this->owner)->get(route('user.commerce.refunds.index', ['tab' => 'completed']))->assertOk()->assertSee($refund->reference);

    $member = User::factory()->create();
    $this->actingAs($member)->get(route('user.commerce.refunds.show', $refund->id))->assertNotFound();
});
