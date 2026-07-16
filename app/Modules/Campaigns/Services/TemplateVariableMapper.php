<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Contacts\Models\Contact;

class TemplateVariableMapper
{
    /**
     * Map placeholders like {{1}}, {{full_name}}, {{city}}, {{custom.field}} in a template.
     */
    public function map(string $template, Contact $contact, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function (array $matches) use ($contact, $variables): string {
            $key = trim($matches[1]);

            if (is_numeric($key)) {
                return $this->resolveVariable($key, $contact, $variables);
            }

            if (str_starts_with($key, 'custom.')) {
                $field = substr($key, 7);

                return (string) data_get($contact->custom_fields, $field, '');
            }

            return match ($key) {
                'full_name' => (string) $contact->name,
                'name' => (string) $contact->name,
                'first_name' => $this->firstName((string) $contact->name),
                'last_name' => $this->lastName((string) $contact->name),
                'email' => (string) ($contact->email ?? ''),
                'phone' => (string) ($contact->phone ?? ''),
                'city' => (string) ($contact->city ?? ''),
                'country' => (string) ($contact->country ?? ''),
                'location' => trim(implode(', ', array_filter([(string) ($contact->city ?? ''), (string) ($contact->country ?? '')]))),
                'website' => (string) data_get($contact->custom_fields, 'website', ''),
                default => $this->resolveVariable($key, $contact, $variables),
            };
        }, $template);
    }

    /**
     * Extract simple variables from a template body.
     *
     * @return array<int, string>
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $template, $matches);

        return array_values(array_unique(array_map('trim', $matches[1])));
    }

    protected function resolveVariable(string $key, Contact $contact, array $variables): string
    {
        if (array_key_exists($key, $variables)) {
            $value = $variables[$key];

            return match ($value) {
                'full_name' => (string) $contact->name,
                'first_name' => $this->firstName((string) $contact->name),
                'last_name' => $this->lastName((string) $contact->name),
                'name' => (string) $contact->name,
                'email' => (string) ($contact->email ?? ''),
                'phone' => (string) ($contact->phone ?? ''),
                'city' => (string) ($contact->city ?? ''),
                'country' => (string) ($contact->country ?? ''),
                'location' => trim(implode(', ', array_filter([(string) ($contact->city ?? ''), (string) ($contact->country ?? '')]))),
                'website' => (string) data_get($contact->custom_fields, 'website', ''),
                default => (string) $value,
            };
        }

        if (array_key_exists('fixed', $variables) && array_key_exists($key, $variables['fixed'])) {
            return (string) $variables['fixed'][$key];
        }

        return '';
    }

    protected function firstName(string $name): string
    {
        $parts = explode(' ', trim($name));

        return $parts[0] ?? '';
    }

    protected function lastName(string $name): string
    {
        $parts = explode(' ', trim($name));

        return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    }
}
