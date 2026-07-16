<?php

namespace App\Modules\Email\Services;

use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

class LaravelMailGateway implements EmailGatewayInterface
{
    public function send(string $to, string $subject, string $htmlBody, ?string $textBody, array $config): array
    {
        $fromAddress = $config['from_address'] ?? config('mail.from.address');
        $fromName = $config['from_name'] ?? config('mail.from.name');

        try {
            $mailer = $this->createMailer($config);

            $mailer->send([], [], function (Message $message) use ($to, $subject, $htmlBody, $textBody, $fromAddress, $fromName): void {
                $message->to($to)
                    ->from($fromAddress, $fromName)
                    ->subject($subject);

                if ($textBody) {
                    $message->text($textBody);
                }

                $message->html($htmlBody);
            });

            return [
                'ok' => true,
                'provider_message_id' => null,
                'status' => 'sent',
            ];
        } catch (TransportException $exception) {
            return [
                'ok' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function test(array $config): array
    {
        if (($config['mailer'] ?? 'log') === 'log') {
            return ['ok' => true];
        }

        if (blank($config['from_address'])) {
            return ['ok' => false, 'error' => 'From address is required.'];
        }

        if ($config['mailer'] === 'smtp') {
            if (blank($config['host']) || blank($config['port'])) {
                return ['ok' => false, 'error' => 'SMTP host and port are required.'];
            }

            if (app()->environment('testing')) {
                return ['ok' => true];
            }

            try {
                $mailer = $this->createMailer($config);
                $transport = $mailer->getSymfonyTransport();
                $transport->start();
                $transport->stop();
            } catch (\Throwable $exception) {
                return [
                    'ok' => false,
                    'error' => 'Connection failed: '.$exception->getMessage(),
                ];
            }
        }

        if ($config['mailer'] === 'mailgun') {
            if (blank($config['mailgun_domain']) || blank($config['mailgun_secret'])) {
                return ['ok' => false, 'error' => 'Mailgun domain and secret are required.'];
            }
        }

        return ['ok' => true];
    }

    protected function createMailer(array $config): Mailer
    {
        $name = $config['mailer'] ?? config('mail.default');

        $transport = match ($name) {
            'smtp' => $this->createSmtpTransport($config),
            'mailgun' => $this->createMailgunTransport($config),
            default => null,
        };

        if ($transport === null) {
            return Mail::mailer($name);
        }

        return new Mailer($name, app('view'), $transport, app('events'));
    }

    protected function createSmtpTransport(array $config): EsmtpTransport
    {
        $encryption = $config['encryption'] ?? null;
        $port = (int) ($config['port'] ?? 25);
        $scheme = match ($encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => $port === 465 ? 'smtps' : 'smtp',
        };

        $dsn = new Dsn(
            $scheme,
            $config['host'] ?? '127.0.0.1',
            $config['username'] ?? null,
            $config['password'] ?? null,
            $port,
            [],
        );

        return (new EsmtpTransportFactory)->create($dsn);
    }

    protected function createMailgunTransport(array $config): TransportInterface
    {
        $dsn = new Dsn(
            'mailgun+https',
            $config['mailgun_endpoint'] ?? 'default',
            $config['mailgun_secret'] ?? '',
            $config['mailgun_domain'] ?? '',
        );

        return (new MailgunTransportFactory)->create($dsn);
    }
}
