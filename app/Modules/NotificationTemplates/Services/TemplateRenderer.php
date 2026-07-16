<?php

namespace App\Modules\NotificationTemplates\Services;

class TemplateRenderer
{
    /**
     * Render a template string by replacing {{variable}} placeholders.
     *
     * @param  array<string, string>  $variables
     */
    public function render(?string $template, array $variables = []): string
    {
        if (! $template) {
            return '';
        }

        $variables = array_merge($this->globalVariables(), $variables);

        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function (array $matches) use ($variables) {
            return $variables[$matches[1]] ?? $matches[0];
        }, $template);
    }

    /**
     * Global variables available in every template.
     *
     * @return array<string, string>
     */
    protected function globalVariables(): array
    {
        return [
            'site_name' => setting('site_name', config('app.name', 'Admin Panel')),
            'site_url' => config('app.url'),
            'current_year' => date('Y'),
        ];
    }
}
