<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use App\Modules\NotificationTemplates\Services\TemplateRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestNotificationCommand extends Command
{
    protected $signature = 'notification:test
                            {slug : The template slug to test}
                            {--channel=email : Channel to test (email, sms)}
                            {--to= : Recipient email or phone number}';

    protected $description = 'Send a test notification using a template';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $channel = $this->option('channel');

        $template = NotificationTemplate::findBySlug($slug);

        if (! $template) {
            $this->error("Template not found: {$slug}");
            $this->line('Available templates:');
            NotificationTemplate::pluck('slug')->each(fn ($s) => $this->line("  - {$s}"));

            return self::FAILURE;
        }

        $renderer = app(TemplateRenderer::class);

        // Build sample variables
        $sampleVars = [];
        foreach ($template->variables ?? [] as $key => $description) {
            $sampleVars[$key] = "[{$key}]";
        }

        $this->info("Testing template: {$template->name} ({$slug})");
        $this->line("Channel: {$channel}");

        match ($channel) {
            'email' => $this->testEmail($template, $renderer, $sampleVars),
            'sms' => $this->testSms($template, $renderer, $sampleVars),
            default => $this->error("Unsupported test channel: {$channel}"),
        };

        return self::SUCCESS;
    }

    protected function testEmail(NotificationTemplate $template, TemplateRenderer $renderer, array $vars): void
    {
        $subject = $renderer->render($template->email_subject, $vars);
        $body = $renderer->render($template->email_body, $vars);

        $to = $this->option('to');

        if (! $to) {
            $admin = Admin::first();
            $to = $admin?->email;
        }

        if (! $to) {
            $this->error('No recipient found. Use --to=email@example.com');

            return;
        }

        $this->line("Sending to: {$to}");
        $this->line("Subject: {$subject}");

        Mail::send('emails.template-notification', [
            'body' => $body,
            'actionUrl' => null,
            'actionText' => null,
        ], function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });

        $this->info('Test email sent!');
    }

    protected function testSms(NotificationTemplate $template, TemplateRenderer $renderer, array $vars): void
    {
        $body = $renderer->render($template->sms_body, $vars);

        if (! $body) {
            $this->error('This template has no SMS body content.');

            return;
        }

        $this->line("SMS body ({$this->strlen($body)} chars):");
        $this->line($body);

        $provider = setting('sms_provider', 'log');
        $this->info("SMS would be sent via: {$provider}");

        if ($provider === 'log') {
            $this->line('(Log driver: check storage/logs/laravel.log)');
        }
    }

    protected function strlen(string $str): int
    {
        return mb_strlen($str);
    }
}
