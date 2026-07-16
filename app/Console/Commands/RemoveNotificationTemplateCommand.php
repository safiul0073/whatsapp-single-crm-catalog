<?php

namespace App\Console\Commands;

use App\Modules\NotificationTemplates\Models\NotificationLog;
use App\Modules\NotificationTemplates\Models\NotificationTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RemoveNotificationTemplateCommand extends Command
{
    protected $signature = 'remove:notification-template {name : PascalCase name or slug (e.g. OrderShipped or order-shipped)}
                            {--force : Skip confirmation prompt}
                            {--keep-db : Keep the database record}';

    protected $description = 'Remove a notification template class, config entry, and database record';

    public function handle(): int
    {
        $input = $this->argument('name');
        $studly = Str::studly($input);
        $slug = Str::kebab($input);
        $className = "{$studly}Notification";

        $classPath = app_path("Modules/NotificationTemplates/Notifications/{$className}.php");

        // Discover what exists
        $hasClass = File::exists($classPath);
        $hasConfig = $this->configEntryExists($slug);
        $hasDbRecord = $this->dbRecordExists($slug);

        if (! $hasClass && ! $hasConfig && ! $hasDbRecord) {
            $this->error("Notification template '{$input}' not found.");
            $this->newLine();
            $this->line('Available templates:');
            $this->listAvailable();

            return self::FAILURE;
        }

        // Scan for references
        $references = $this->scanReferences($className, $slug);

        // Show summary
        if (! $this->option('force')) {
            $this->newLine();
            $this->info("=== Remove Notification Template: {$studly} ===");
            $this->newLine();

            $this->line('<fg=yellow>Will be removed:</>');
            if ($hasClass) {
                $this->line("  - Class: app/Modules/NotificationTemplates/Notifications/{$className}.php");
            }
            if ($hasConfig) {
                $this->line("  - Config entry: config/notification-templates.php → '{$slug}'");
            }
            if ($hasDbRecord && ! $this->option('keep-db')) {
                $this->line("  - Database record: notification_templates → slug='{$slug}'");
                $this->line('  - Associated log entries: notification_logs → template_slug=\''.$slug.'\'');
            }

            if (! empty($references)) {
                $this->newLine();
                $this->warn('External references found (you must fix these manually):');
                foreach ($references as $ref) {
                    $this->line("  <fg=red>[{$ref['type']}]</> {$ref['file']}:{$ref['line']}");
                }
            }

            $this->newLine();
            if (! $this->confirm('Proceed with removal?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // 1. Remove class file
        if ($hasClass) {
            File::delete($classPath);
            $this->info("Removed class: {$className}.php");
        }

        // 2. Remove config entry
        if ($hasConfig) {
            $this->removeConfigEntry($slug);
            $this->info("Removed config entry: '{$slug}'");
        }

        // 3. Remove database record
        if ($hasDbRecord && ! $this->option('keep-db')) {
            NotificationTemplate::withTrashed()->where('slug', $slug)->forceDelete();
            NotificationLog::where('template_slug', $slug)->delete();
            $this->info("Removed database record and logs for: '{$slug}'");
        }

        $this->newLine();
        $this->info("Notification template '{$studly}' has been removed.");

        if (! empty($references)) {
            $this->newLine();
            $this->warn('Fix the '.count($references).' external reference(s) listed above to avoid runtime errors.');
        }

        return self::SUCCESS;
    }

    protected function configEntryExists(string $slug): bool
    {
        return array_key_exists($slug, config('notification-templates', []));
    }

    protected function dbRecordExists(string $slug): bool
    {
        return NotificationTemplate::withTrashed()->where('slug', $slug)->exists();
    }

    protected function removeConfigEntry(string $slug): void
    {
        $configPath = config_path('notification-templates.php');
        $content = File::get($configPath);

        // Find the start: '{slug}' => [
        $key = "'{$slug}' => [";
        $startPos = strpos($content, $key);

        if ($startPos === false) {
            return;
        }

        // Walk backwards to capture leading whitespace/newline
        $searchStart = $startPos;
        while ($searchStart > 0 && in_array($content[$searchStart - 1], [' ', "\t"])) {
            $searchStart--;
        }
        if ($searchStart > 0 && $content[$searchStart - 1] === "\n") {
            $searchStart--;
        }

        // Find the matching closing ']' using bracket depth
        $bracketDepth = 0;
        $openBracketPos = strpos($content, '[', $startPos);
        $endPos = $openBracketPos;

        for ($i = $openBracketPos; $i < strlen($content); $i++) {
            if ($content[$i] === '[') {
                $bracketDepth++;
            }
            if ($content[$i] === ']') {
                $bracketDepth--;
            }
            if ($bracketDepth === 0) {
                $endPos = $i + 1;
                if ($endPos < strlen($content) && $content[$endPos] === ',') {
                    $endPos++;
                }
                if ($endPos < strlen($content) && $content[$endPos] === "\n") {
                    $endPos++;
                }
                break;
            }
        }

        $content = substr($content, 0, $searchStart).substr($content, $endPos);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($configPath, $content);
    }

    /**
     * @return array<array{type: string, file: string, line: int}>
     */
    protected function scanReferences(string $className, string $slug): array
    {
        $references = [];
        $classFile = app_path("Modules/NotificationTemplates/Notifications/{$className}.php");

        $patterns = [
            'Class usage' => $className,
            'Slug reference' => "'{$slug}'",
        ];

        $scanPaths = [
            app_path('Modules'),
            app_path('Panels'),
            app_path('Http'),
        ];

        foreach ($scanPaths as $scanPath) {
            if (! File::exists($scanPath)) {
                continue;
            }

            foreach (File::allFiles($scanPath) as $file) {
                $filePath = $file->getRealPath();

                // Skip the notification class itself
                if (str_replace('\\', '/', $filePath) === str_replace('\\', '/', $classFile)) {
                    continue;
                }

                // Skip the base notification and other template notification files
                if (Str::contains($filePath, 'BaseTemplateNotification.php')) {
                    continue;
                }

                $content = File::get($filePath);

                foreach ($patterns as $label => $pattern) {
                    if (Str::contains($content, $pattern)) {
                        $lineNumber = $this->findLineNumber($content, $pattern);
                        $relativePath = Str::after(
                            str_replace('\\', '/', $filePath),
                            str_replace('\\', '/', base_path()).'/'
                        );
                        $references[] = [
                            'type' => $label,
                            'file' => $relativePath,
                            'line' => $lineNumber,
                        ];
                    }
                }
            }
        }

        return $references;
    }

    protected function findLineNumber(string $content, string $needle): int
    {
        $lines = explode("\n", $content);
        foreach ($lines as $index => $line) {
            if (Str::contains($line, $needle)) {
                return $index + 1;
            }
        }

        return 0;
    }

    protected function listAvailable(): void
    {
        // From config
        $configSlugs = array_keys(config('notification-templates', []));

        // From class files
        $classDir = app_path('Modules/NotificationTemplates/Notifications');
        $classSlugs = [];
        if (File::exists($classDir)) {
            foreach (File::files($classDir) as $file) {
                $name = $file->getFilenameWithoutExtension();
                if ($name !== 'BaseTemplateNotification' && Str::endsWith($name, 'Notification')) {
                    $classSlugs[] = Str::kebab(Str::before($name, 'Notification'));
                }
            }
        }

        $all = collect(array_merge($configSlugs, $classSlugs))->unique()->sort();

        foreach ($all as $slug) {
            $inConfig = in_array($slug, $configSlugs) ? 'config' : '';
            $inClass = in_array($slug, $classSlugs) ? 'class' : '';
            $sources = implode('+', array_filter([$inConfig, $inClass]));
            $this->line("  - {$slug} ({$sources})");
        }
    }
}
