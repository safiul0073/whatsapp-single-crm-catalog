<?php

use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Models\Plan;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\WithoutDefaultTestPlan;

uses(RefreshDatabase::class, WithoutDefaultTestPlan::class);

/**
 * @return array{0: User, 1: Payment}
 */
function manualCheckoutPayment(array $fields): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $workspace->update(['settings' => ['onboarding_completed_at' => now()->toIso8601String()]]);

    $plan = Plan::query()->create([
        'name' => 'Lifetime',
        'slug' => 'lifetime-'.Str::lower(Str::random(6)),
        'price' => 490,
        'interval' => Plan::INTERVAL_MONTH,
        'is_free' => false,
        'limits' => [],
        'features' => [],
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'user_type' => $user->getMorphClass(),
        'gateway' => 'bank-transfer',
        'amount' => 490,
        'currency' => 'USD',
        'status' => 'pending',
        'description' => 'Lifetime subscription',
        'metadata' => [
            'plan_id' => $plan->id,
            'workspace_id' => $workspace->id,
            'intent' => 'new',
            'driver' => 'manual',
            'method' => 'bank-transfer',
            'selected_gateway' => 'bank-transfer',
            'instructions' => '<p>Bank: City Bank</p><p>Account: 123456</p>',
            'user_fields_definition' => $fields,
        ],
    ]);

    return [$user, $payment];
}

function manualCheckoutClient(): mixed
{
    return test()->withoutMiddleware([Authorize::class, EnsureTwoFactorAuthenticated::class]);
}

it('shows the manual payment instructions and input fields on checkout', function (): void {
    [$user, $payment] = manualCheckoutPayment([['label' => 'Transaction ID', 'type' => 'text', 'required' => true]]);

    manualCheckoutClient()->actingAs($user)
        ->get(route('user.subscription.checkout.page', $payment))
        ->assertOk()
        ->assertSee('Bank Transfer')
        ->assertSee('Bank: City Bank')
        ->assertSee('Account: 123456')
        ->assertDontSee('<p>Bank: City Bank</p>', false)
        ->assertSee('Transaction ID')
        ->assertSee(route('user.subscription.checkout.manual', $payment));
});

it('stores submitted details and proof for admin review', function (): void {
    Storage::fake('public');
    [$user, $payment] = manualCheckoutPayment([
        ['label' => 'Transaction ID', 'type' => 'text', 'required' => true],
        ['label' => 'Receipt', 'type' => 'file', 'required' => false],
    ]);

    manualCheckoutClient()->actingAs($user)
        ->post(route('user.subscription.checkout.manual', $payment), [
            'fields' => [0 => 'TX-998', 1 => UploadedFile::fake()->image('receipt.png')],
        ])
        ->assertRedirect(route('user.subscription.checkout.page', $payment));

    $metadata = $payment->fresh()->metadata;
    expect($metadata['user_submitted_data'])->toMatchArray(['Transaction ID' => 'TX-998', 'Receipt' => 'receipt.png'])
        ->and($metadata['proof_media_ids'])->toHaveCount(1)
        ->and($metadata['submitted_at'])->not->toBeNull()
        ->and($payment->fresh()->status)->toBe('pending');

    manualCheckoutClient()->actingAs($user)
        ->get(route('user.subscription.checkout.page', $payment))
        ->assertSee('Payment details submitted')
        ->assertSee('TX-998')
        ->assertDontSee(route('user.subscription.checkout.manual', $payment));
});

it('validates required manual payment fields', function (): void {
    [$user, $payment] = manualCheckoutPayment([['label' => 'Transaction ID', 'type' => 'text', 'required' => true]]);

    manualCheckoutClient()->actingAs($user)
        ->post(route('user.subscription.checkout.manual', $payment), ['fields' => [0 => '']])
        ->assertSessionHasErrors('fields.0');

    expect(data_get($payment->fresh()->metadata, 'submitted_at'))->toBeNull();
});

it('hides another workspace manual payment', function (): void {
    [, $payment] = manualCheckoutPayment([]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);

    manualCheckoutClient()->actingAs($otherUser)
        ->post(route('user.subscription.checkout.manual', $payment), [])
        ->assertNotFound();
});
