<?php

namespace App\Modules\Email\Services;

interface EmailGatewayInterface
{
    /**
     * Send an email and return a result array.
     *
     * @return array{ok: bool, provider_message_id?: string|null, status: string, error?: string|null}
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody, array $config): array;

    /**
     * Validate/test the provided gateway configuration.
     *
     * @return array{ok: bool, error?: string|null}
     */
    public function test(array $config): array;
}
