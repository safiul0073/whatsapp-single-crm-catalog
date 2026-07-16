<?php

use App\Modules\AiSettings\Services\AiSettingsService;

if (! function_exists('ai_setting')) {
    /**
     * Get an AI setting value.
     *
     * Usage: ai_setting('openai_api_key')
     *        ai_setting('ai_default_text_provider', 'openai')
     */
    function ai_setting(string $key, mixed $default = null): mixed
    {
        return app(AiSettingsService::class)->get($key, $default);
    }
}
