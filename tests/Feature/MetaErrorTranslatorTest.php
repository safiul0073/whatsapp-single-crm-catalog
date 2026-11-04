<?php

use App\Modules\MarketingChannels\Services\MetaErrorTranslator;

it('explains Meta errors in plain language with a next step', function (array $error, string $title, bool $reconnect): void {
    $translated = app(MetaErrorTranslator::class)->translate(['error' => $error + ['fbtrace_id' => 'TRACE1']]);

    expect($translated['title'])->toBe($title)
        ->and($translated['action'])->not->toBeEmpty()
        ->and($translated['reconnect'])->toBe($reconnect)
        ->and($translated['trace_id'])->toBe('TRACE1');
})->with([
    'expired token' => [['code' => 190, 'error_subcode' => 463, 'message' => 'Session has expired'], 'Meta access expired', true],
    'missing permission' => [['code' => 200, 'message' => 'Requires pages_messaging'], 'A Meta permission is missing', true],
    '24h window' => [['code' => 131047, 'message' => 'Re-engagement message'], 'The 24-hour reply window has closed', false],
    'payment' => [['code' => 131042, 'message' => 'Business eligibility payment issue'], 'Payment problem', false],
    'template range' => [['code' => 132001, 'message' => 'Template does not exist'], 'Template problem', false],
    'registration range' => [['code' => 133010, 'message' => 'Account not registered'], 'Phone number not ready', true],
    'messenger window' => [['code' => 10, 'error_subcode' => 2018278, 'message' => 'Outside of allowed window'], 'Outside the messaging window', false],
]);

it('falls back to Meta\'s own message for unknown codes', function (): void {
    $translated = app(MetaErrorTranslator::class)->translate(['error' => ['code' => 999999, 'message' => 'Something odd happened']]);

    expect($translated['message'])->toBe('Something odd happened')
        ->and($translated['meta_code'])->toBe('999999')
        ->and($translated['action'])->toContain('support code');
});

it('marks rate limits as retryable', function (): void {
    expect(app(MetaErrorTranslator::class)->translate(['error' => ['code' => 130429]])['retryable'])->toBeTrue();
});
