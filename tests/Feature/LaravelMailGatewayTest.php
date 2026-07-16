<?php

use App\Modules\Email\Services\LaravelMailGateway;

it('dynamically configures smtp mailer and purges resolver on send', function (): void {
    $gateway = new LaravelMailGateway;

    $config = [
        'mailer' => 'smtp',
        'host' => 'dynamic-test-host.example.com',
        'port' => 456,
        'encryption' => 'tls',
        'username' => 'test-user',
        'password' => 'test-pass',
        'from_address' => 'noreply@dynamic.com',
        'from_name' => 'Dynamic Sender',
    ];

    $result = $gateway->send(
        'recipient@example.com',
        'Test Subject',
        '<p>Body</p>',
        'Body',
        $config
    );

    expect($result['ok'])->toBeFalse();
    expect($result['error'])->toContain('dynamic-test-host.example.com');
    expect($result['error'])->toContain('456');
});
