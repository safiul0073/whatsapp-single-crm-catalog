<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeNotificationTemplateCommand extends Command
{
    protected $signature = 'make:notification-template {name : PascalCase name (e.g. OrderConfirmed)}';

    protected $description = 'Scaffold a new notification template class and config entry';

    public function handle(): int
    {
        $name = $this->argument('name');
        $studly = Str::studly($name);
        $slug = Str::kebab($name);
        $className = "{$studly}Notification";

        $classPath = app_path("Modules/NotificationTemplates/Notifications/{$className}.php");

        if (file_exists($classPath)) {
            $this->error("Notification class already exists: {$classPath}");

            return self::FAILURE;
        }

        // Generate the class from stub
        $stub = file_get_contents(base_path('stubs/notification/TemplateNotification.stub'));
        $stub = str_replace(['{{ name }}', '{{ slug }}'], [$studly, $slug], $stub);

        $directory = dirname($classPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($classPath, $stub);
        $this->info("Created notification class: {$classPath}");

        // Append config entry
        $this->appendConfigEntry($slug, $studly);

        $this->newLine();
        $this->info('Next steps:');
        $this->line("  1. Edit <comment>{$classPath}</comment> to add constructor params and variables");
        $this->line('  2. Run <comment>php artisan notification:sync</comment> to create the DB record');
        $this->line("  3. Dispatch: <comment>\$user->notify(new {$className}(\$data));</comment>");

        return self::SUCCESS;
    }

    /**
     * Append a new template entry to config/notification-templates.php.
     */
    protected function appendConfigEntry(string $slug, string $studly): void
    {
        $configPath = config_path('notification-templates.php');
        $content = file_get_contents($configPath);

        $humanName = Str::headline($studly);

        $entry = <<<PHP

    '{$slug}' => [
        'name' => '{$humanName}',
        'description' => '',
        'channels' => ['email', 'in_app'],
        'variables' => [
            // 'variable_name' => 'Description for admin UI',
        ],
        'defaults' => [
            'email_subject' => '{$humanName}',
            'email_body' => '<p>Email body for {$humanName}.</p>',
            'in_app_title' => '{$humanName}',
            'in_app_body' => '{$humanName} notification.',
        ],
    ],
PHP;

        // Insert before the closing "];"
        $content = preg_replace('/\n\];\s*\z/', $entry."\n\n];\n", $content);
        file_put_contents($configPath, $content);

        $this->info("Added config entry: config/notification-templates.php → '{$slug}'");
    }
}
