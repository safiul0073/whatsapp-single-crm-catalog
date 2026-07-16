<?php

namespace App\Modules\AiSettings\Providers;

use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\Shared\Support\BasePanelModuleProvider;

class AiSettingsServiceProvider extends BasePanelModuleProvider
{
    public function register(): void
    {
        $this->app->singleton(AiSettingsService::class);
    }

    protected function bootModule(array $module): void
    {
        try {
            $service = $this->app->make(AiSettingsService::class);
            $providers = $service->getProviderConfig();

            if (! empty($providers)) {
                config(['ai.providers' => $providers]);
            }

            $defaultProviderMap = [
                'ai_default_text_provider' => 'ai.default',
                'ai_default_image_provider' => 'ai.default_for_images',
                'ai_default_tts_provider' => 'ai.default_for_audio',
                'ai_default_stt_provider' => 'ai.default_for_transcription',
                'ai_default_embeddings_provider' => 'ai.default_for_embeddings',
            ];

            $providerKeyMap = [
                'azure-openai' => 'azure',
                'elevenlabs' => 'eleven',
            ];

            foreach ($defaultProviderMap as $settingKey => $configKey) {
                $value = $service->get($settingKey);
                if ($value) {
                    config([$configKey => $providerKeyMap[$value] ?? $value]);
                }
            }
        } catch (\Throwable) {
            //
        }
    }
}
