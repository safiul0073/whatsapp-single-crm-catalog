<?php

it('declares the aligned provider capabilities used by campaigns inbox and publishing', function (): void {
    $providers = collect(config('marketing-channels.providers'));

    expect($providers->where('campaign', true)->keys()->values()->all())
        ->toBe(['whatsapp', 'telegram', 'email', 'sms'])
        ->and($providers->where('inbox', true)->keys()->values()->all())
        ->toBe(['whatsapp', 'telegram', 'messenger', 'instagram'])
        ->and($providers->where('publishing', true)->keys()->values()->all())
        ->toBe(['threads']);
});

it('keeps webhook setup hidden for outbound only email and sms providers', function (): void {
    expect(config('marketing-channels.providers.email.webhook_required'))->toBeFalse()
        ->and(config('marketing-channels.providers.sms.webhook_required'))->toBeFalse()
        ->and(config('marketing-channels.providers.email.capabilities'))->not->toContain('Webhooks')
        ->and(config('marketing-channels.providers.sms.capabilities'))->not->toContain('Webhooks');
});

it('requires connection testing for each configured provider', function (): void {
    expect(collect(config('marketing-channels.providers'))
        ->filter(fn (array $provider): bool => ! ($provider['connection_test_required'] ?? false))
        ->keys()
        ->all())->toBe([]);
});
