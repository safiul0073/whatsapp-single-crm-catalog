# AI Settings & Laravel AI SDK

Manage AI providers through the admin panel and use the Laravel AI SDK anywhere in your application with zero configuration files. Admins configure credentials in the UI, developers call one-liner methods.

---

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Quick Start: Text Generation](#quick-start-text-generation)
- [Quick Start: Creating an Agent](#quick-start-creating-an-agent)
- [All SDK Capabilities](#all-sdk-capabilities)
  - [Text Generation (Agents)](#text-generation-agents)
  - [Structured Output](#structured-output)
  - [Agents with Tools](#agents-with-tools)
  - [Conversation Memory](#conversation-memory)
  - [Streaming Responses](#streaming-responses)
  - [Image Generation](#image-generation)
  - [Text-to-Speech (TTS)](#text-to-speech-tts)
  - [Speech-to-Text (STT)](#speech-to-text-stt)
  - [Embeddings](#embeddings)
  - [Reranking](#reranking)
- [Available Providers](#available-providers)
- [Provider Setup](#provider-setup)
  - [OpenAI](#openai)
  - [Anthropic](#anthropic)
  - [Google Gemini](#google-gemini)
  - [Azure OpenAI](#azure-openai)
  - [Groq](#groq)
  - [xAI](#xai)
  - [DeepSeek](#deepseek)
  - [Mistral](#mistral)
  - [Ollama (Local)](#ollama-local)
  - [ElevenLabs](#elevenlabs)
  - [Cohere](#cohere)
  - [Jina](#jina)
  - [VoyageAI](#voyageai)
- [Using the `ai_setting()` Helper](#using-the-ai_setting-helper)
- [Overriding Provider at Runtime](#overriding-provider-at-runtime)
- [Adding a New Provider](#adding-a-new-provider)
- [Removing a Provider](#removing-a-provider)
- [Admin Panel](#admin-panel)
- [Architecture Overview](#architecture-overview)
- [Key Files](#key-files)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

---

## Overview

The AI system consists of two parts:

1. **AI Settings Module** — a config-driven admin UI (same pattern as Payment Gateway Settings) that stores provider credentials in the database
2. **Laravel AI SDK** (`laravel/ai`) — first-party Laravel package providing a unified API for text, images, audio, embeddings, and more

Admins enable providers and enter API keys through the UI. The `AiSettingsServiceProvider` automatically overrides `config('ai.providers')` at boot, so the SDK uses admin-configured values without touching `.env` files.

---

## How It Works

```
Admin enables OpenAI in Settings → AI Settings → OpenAI tab
        |
        v
Credentials stored in `ai_settings` DB table
        |
        v
AiSettingsServiceProvider::boot() reads DB values
        |
        v
config('ai.providers') overridden at runtime
        |
        v
Developer uses Laravel AI SDK normally:
  Agent::make()->prompt('...')     → uses configured OpenAI
  Image::of('...')->generate()     → uses configured image provider
  Audio::of('...')->generate()     → uses configured TTS provider
```

---

## Quick Start: Text Generation

No setup needed beyond enabling a provider in the admin panel.

```php
use Laravel\Ai\Facades\Ai;

// Simple one-shot prompt (uses default text provider)
$response = Ai::prompt('Summarize this article in 3 bullet points: ...');
echo $response->text;
```

---

## Quick Start: Creating an Agent

```bash
php artisan make:agent ProductDescriptionWriter
```

This creates `app/Ai/Agents/ProductDescriptionWriter.php`:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class ProductDescriptionWriter implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are an expert product copywriter. Write compelling, '
             . 'SEO-friendly product descriptions based on the details provided.';
    }
}
```

Use it anywhere:

```php
use App\Ai\Agents\ProductDescriptionWriter;

$description = (new ProductDescriptionWriter)->prompt(
    'Write a description for: Blue cotton t-shirt, size S-XL, $29.99'
);

echo $description->text;
```

---

## All SDK Capabilities

### Text Generation (Agents)

The primary way to generate text is through agents.

```php
use App\Ai\Agents\SalesCoach;

// Basic prompt
$response = (new SalesCoach)->prompt('Analyze this sales transcript...');
echo $response->text;

// With dependency injection
$response = SalesCoach::make(user: $user)->prompt('...');
```

### Structured Output

Get typed data back from the AI instead of raw text.

```bash
php artisan make:agent SalesCoach --structured
```

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\JsonSchema;
use Laravel\Ai\Promptable;

class SalesCoach implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sales coach analyzing call transcripts.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback' => $schema->string()->required(),
            'score'    => $schema->integer()->min(1)->max(10)->required(),
            'topics'   => $schema->array($schema->string())->required(),
        ];
    }
}

// Usage — returns structured array
$result = (new SalesCoach)->prompt('Analyze this transcript...');
$score    = $result['score'];    // int
$feedback = $result['feedback']; // string
$topics   = $result['topics'];   // array
```

### Agents with Tools

Give agents the ability to call functions.

```bash
php artisan make:tool LookupCustomer
```

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class SupportAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a customer support agent. Look up customer info when needed.';
    }

    public function tools(): iterable
    {
        return [
            new LookupCustomer,
        ];
    }
}
```

Built-in tools from the SDK:

```php
use Laravel\Ai\Tools\WebSearch;
use Laravel\Ai\Tools\WebFetch;
use Laravel\Ai\Tools\FileSearch;

public function tools(): iterable
{
    return [
        new WebSearch,   // Search the web
        new WebFetch,    // Fetch and read web pages
        new FileSearch,  // Search uploaded documents
    ];
}
```

### Conversation Memory

Persist multi-turn conversations across requests.

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Laravel\Ai\RemembersConversations;

class ChatBot implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function instructions(): string
    {
        return 'You are a helpful assistant.';
    }
}

// Start a conversation
$response = (new ChatBot)->forUser($user)->prompt('Hello!');
$conversationId = $response->conversationId;

// Continue the same conversation later
$response = (new ChatBot)
    ->continue($conversationId, as: $user)
    ->prompt('Tell me more about that.');
```

> **Note:** Conversations are stored in the `agent_conversations` and `agent_conversation_messages` tables (created by `laravel/ai` migrations).

### Streaming Responses

Stream text as it's generated for real-time UIs.

```php
use App\Ai\Agents\Writer;

// Stream to HTTP response
return (new Writer)->stream('Write a blog post about Laravel...');

// Stream with callback
return (new Writer)
    ->stream('Write a blog post...')
    ->then(function ($response) {
        Log::info('Generation complete', ['text' => $response->text]);
    });
```

### Image Generation

```php
use Laravel\Ai\Image;

// Generate an image (uses default image provider from AI Settings)
$image = Image::of('A serene mountain landscape at sunset')
    ->landscape()          // orientation
    ->quality('high')      // quality level
    ->generate();

// Store to disk
$path = $image->store();         // storage/app/ai-images/...
$path = $image->store('public'); // storage/app/public/...

// Get as base64
$base64 = $image->toBase64();
```

### Text-to-Speech (TTS)

```php
use Laravel\Ai\Audio;

$audio = Audio::of('Welcome to our application!')
    ->female()    // or ->male()
    ->generate();

$path = $audio->store();          // storage/app/ai-audio/...
$url  = $audio->store('public');  // public URL
```

### Speech-to-Text (STT)

```php
use Laravel\Ai\Transcription;

// From storage path
$transcript = Transcription::fromStorage('recordings/meeting.mp3')
    ->diarize()      // speaker identification
    ->generate();

echo $transcript->text;

// From uploaded file
$transcript = Transcription::fromUpload($request->file('audio'))
    ->generate();
```

### Embeddings

```php
use Illuminate\Support\Str;

// Generate embeddings (uses default embeddings provider)
$embeddings = Str::of('Laravel makes PHP development enjoyable.')
    ->toEmbeddings(cache: true);

// Use with similarity search
use Laravel\Ai\SimilaritySearch;

$results = SimilaritySearch::usingModel(Document::class, 'embedding')
    ->query('How do I deploy Laravel?')
    ->take(5)
    ->get();
```

### Reranking

Reorder search results by semantic relevance.

```php
use Laravel\Ai\Reranking;

$results = Reranking::query('best restaurants in Paris')
    ->documents($searchResults)
    ->rerank();
```

---

## Available Providers

| Provider | Text | Image | TTS | STT | Embeddings | Reranking |
|----------|:----:|:-----:|:---:|:---:|:----------:|:---------:|
| OpenAI | Yes | Yes | Yes | Yes | Yes | — |
| Anthropic | Yes | — | — | — | — | — |
| Google Gemini | Yes | Yes | — | — | Yes | — |
| Azure OpenAI | Yes | — | — | — | Yes | — |
| Groq | Yes | — | — | — | — | — |
| xAI | Yes | Yes | — | — | — | — |
| DeepSeek | Yes | — | — | — | — | — |
| Mistral | Yes | — | — | Yes | Yes | — |
| Ollama | Yes | — | — | — | — | — |
| ElevenLabs | — | — | Yes | Yes | — | — |
| Cohere | — | — | — | — | Yes | Yes |
| Jina | — | — | — | — | Yes | Yes |
| VoyageAI | — | — | — | — | Yes | — |

---

## Provider Setup

All providers are configured in **Admin Panel → Settings → AI Settings**. Each provider has its own tab.

### OpenAI

1. Get your API key from [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. In admin panel → Settings → AI Settings → OpenAI tab:
   - Enable the toggle
   - **API Key**: `sk-...`
   - **Organization ID**: optional `org-...`
   - **Base URL**: leave empty for default (or set for proxy/Azure-compatible endpoints)
3. Set as default in the General tab for desired capabilities

### Anthropic

1. Get your API key from [console.anthropic.com](https://console.anthropic.com)
2. In admin panel → Settings → AI Settings → Anthropic tab:
   - Enable the toggle
   - **API Key**: `sk-ant-...`

### Google Gemini

1. Get your API key from [aistudio.google.com](https://aistudio.google.com/apikey)
2. In admin panel → Settings → AI Settings → Google Gemini tab:
   - Enable the toggle
   - **API Key**: your Gemini API key

### Azure OpenAI

1. Create an Azure OpenAI resource in [portal.azure.com](https://portal.azure.com)
2. In admin panel → Settings → AI Settings → Azure OpenAI tab:
   - Enable the toggle
   - **API Key**: your Azure API key
   - **Endpoint URL**: `https://your-resource.openai.azure.com` (required)
   - **API Version**: `2024-02-01` (pre-filled)
   - **Deployment Name**: your model deployment name

### Groq

1. Get your API key from [console.groq.com](https://console.groq.com)
2. In admin panel → Settings → AI Settings → Groq tab:
   - Enable the toggle
   - **API Key**: `gsk_...`

### xAI

1. Get your API key from [console.x.ai](https://console.x.ai)
2. In admin panel → Settings → AI Settings → xAI tab:
   - Enable the toggle
   - **API Key**: your xAI API key

### DeepSeek

1. Get your API key from [platform.deepseek.com](https://platform.deepseek.com)
2. In admin panel → Settings → AI Settings → DeepSeek tab:
   - Enable the toggle
   - **API Key**: your DeepSeek API key

### Mistral

1. Get your API key from [console.mistral.ai](https://console.mistral.ai)
2. In admin panel → Settings → AI Settings → Mistral tab:
   - Enable the toggle
   - **API Key**: your Mistral API key

### Ollama (Local)

Run AI models locally without any API key.

1. Install Ollama from [ollama.com](https://ollama.com)
2. Pull a model: `ollama pull llama3.2`
3. In admin panel → Settings → AI Settings → Ollama tab:
   - Enable the toggle
   - **Base URL**: `http://localhost:11434` (pre-filled)

> **No API key needed.** Ollama runs on your local machine. Change the Base URL if running on a different host or port.

### ElevenLabs

1. Get your API key from [elevenlabs.io](https://elevenlabs.io)
2. In admin panel → Settings → AI Settings → ElevenLabs tab:
   - Enable the toggle
   - **API Key**: your ElevenLabs API key

### Cohere

1. Get your API key from [dashboard.cohere.com](https://dashboard.cohere.com)
2. In admin panel → Settings → AI Settings → Cohere tab:
   - Enable the toggle
   - **API Key**: your Cohere API key

### Jina

1. Get your API key from [jina.ai](https://jina.ai)
2. In admin panel → Settings → AI Settings → Jina tab:
   - Enable the toggle
   - **API Key**: your Jina API key

### VoyageAI

1. Get your API key from [dash.voyageai.com](https://dash.voyageai.com)
2. In admin panel → Settings → AI Settings → VoyageAI tab:
   - Enable the toggle
   - **API Key**: your VoyageAI API key

---

## Using the `ai_setting()` Helper

Read any AI setting value from anywhere in your application:

```php
// Get a provider's API key
$key = ai_setting('openai_api_key');

// Check if a provider is enabled
if (ai_setting('anthropic_enabled')) {
    // Anthropic is active
}

// Get default provider with fallback
$provider = ai_setting('ai_default_text_provider', 'openai');

// Get Ollama URL
$url = ai_setting('ollama_base_url'); // 'http://localhost:11434'
```

The helper reads from DB first, falls back to config defaults, then to the provided fallback value.

---

## Overriding Provider at Runtime

The default provider is set in the General tab, but you can override per-call:

```php
use Laravel\Ai\Lab;

// Use a specific provider for one call
$response = (new MyAgent)->prompt(
    'Translate this to French...',
    provider: Lab::Anthropic,
    model: 'claude-sonnet-4-20250514',
    timeout: 120,
);

// Image generation with specific provider
use Laravel\Ai\Image;

$image = Image::of('A cat wearing a hat')
    ->using(Lab::OpenAI)
    ->model('dall-e-3')
    ->generate();
```

Available `Lab` constants: `Lab::OpenAI`, `Lab::Anthropic`, `Lab::Gemini`, `Lab::Azure`, `Lab::Groq`, `Lab::xAI`, `Lab::DeepSeek`, `Lab::Mistral`, `Lab::Ollama`, `Lab::ElevenLabs`, `Lab::Cohere`, `Lab::Jina`, `Lab::VoyageAI`.

---

## Adding a New Provider

To add a provider that isn't built-in (e.g. OpenRouter, a custom proxy, or a future provider):

### Step 1: Add to config

Edit `config/ai-settings.php` and add a new group at the end:

```php
'openrouter' => [
    'label'       => 'OpenRouter',
    'icon'        => 'ph ph-arrows-split',
    'description' => 'Access multiple AI models through OpenRouter',
    'layout'      => 'full',
    'settings'    => [
        'openrouter_enabled' => [
            'type'    => 'feature',
            'label'   => 'Enable OpenRouter',
            'hint'    => 'Use OpenRouter to access multiple AI providers',
            'default' => false,
        ],
        'openrouter_api_key' => [
            'type'    => 'password',
            'label'   => 'API Key',
            'hint'    => 'Your OpenRouter API key',
            'default' => '',
            'rules'   => 'nullable|string|max:255',
        ],
        'openrouter_base_url' => [
            'type'    => 'text',
            'label'   => 'Base URL',
            'hint'    => 'Custom API base URL (leave empty for default)',
            'default' => '',
            'rules'   => 'nullable|string|max:500',
        ],
        'openrouter_logo' => [
            'type'    => 'media',
            'label'   => 'Provider Logo',
            'hint'    => 'Logo displayed in AI provider listings',
            'default' => null,
            'accept'  => 'image',
            'layout'  => 'sidebar',
        ],
    ],
],
```

### Step 2: Register the driver mapping

Edit `app/Modules/AiSettings/Services/AiSettingsService.php` and add the new provider to the `$driverMap` array inside `getProviderConfig()`:

```php
$driverMap = [
    // ... existing providers ...
    'openrouter' => 'openrouter',   // ← add this line
];
```

### Step 3: Add to General tab options (if applicable)

If the provider supports text generation, add it to the options in the `ai_default_text_provider` select in `config/ai-settings.php`:

```php
'ai_default_text_provider' => [
    'options' => [
        // ... existing options ...
        'openrouter' => 'OpenRouter',   // ← add this line
    ],
],
```

### Step 4: Seed and clear cache

```bash
php artisan db:seed --class="App\Modules\AiSettings\Database\Seeders\AiSettingSeeder"
php artisan config:clear
```

The new provider tab will appear automatically in Settings → AI Settings. No view changes, no route changes, no migration changes needed.

---

## Removing a Provider

### Step 1: Remove from config

Delete the provider's group from `config/ai-settings.php`.

### Step 2: Remove from driver map

Remove the entry from the `$driverMap` array in `AiSettingsService::getProviderConfig()`.

### Step 3: Remove from General tab options

Remove the provider from any select `options` arrays in the `general` group.

### Step 4: Clean up database (optional)

```bash
php artisan tinker --execute="
    App\Modules\AiSettings\Models\AiSetting::where('key', 'like', 'providername_%')->delete();
"
php artisan config:clear
```

The provider tab disappears immediately from the admin UI. No migration needed.

---

## Admin Panel

### Settings → AI Settings

Located at **Settings → AI Settings** in the admin sidebar. Has two types of tabs:

- **General** — Default provider and model selections for each capability (text, image, TTS, STT, embeddings)
- **Provider tabs** — One tab per provider with enable toggle, API key, base URL, and provider-specific fields

### Permissions

| Permission | Description |
|------------|-------------|
| `ai-settings.view` | View the AI Settings page |
| `ai-settings.edit` | Save AI settings changes |

Both permissions are automatically assigned to the `admin` and `super-admin` roles.

---

## Architecture Overview

```
config/ai-settings.php                    (Provider definitions — each group = tab)
        |
        v
AiSettings module                         (Dedicated DB table, service, helper)
  ├── Model:    AiSetting                  (key-value store in ai_settings table)
  ├── Service:  AiSettingsService          (get/set + getProviderConfig())
  ├── Helper:   ai_setting()               (global accessor)
  └── Provider: AiSettingsServiceProvider  (overrides config('ai.providers') at boot)
        |
        v
config/ai.php (runtime)                   (SDK reads providers from here)
        |
        v
Laravel AI SDK (laravel/ai)
  ├── Agents     → Text generation with tools, memory, streaming
  ├── Image      → DALL-E, Gemini, xAI image generation
  ├── Audio      → TTS via OpenAI, ElevenLabs
  ├── Transcript → STT via Whisper, ElevenLabs, Mistral
  ├── Embeddings → Vector embeddings for search/RAG
  └── Reranking  → Search result reranking via Cohere, Jina
```

---

## Key Files

| File | Purpose |
|------|---------|
| `config/ai-settings.php` | Provider group definitions (each group = tab in UI) |
| `config/ai.php` | Laravel AI SDK config (overridden at runtime by AiSettingsServiceProvider) |
| `app/Modules/AiSettings/Models/AiSetting.php` | Key-value model (`ai_settings` table) |
| `app/Modules/AiSettings/Services/AiSettingsService.php` | Settings service + `getProviderConfig()` bridge |
| `app/Modules/AiSettings/Helpers/AiSettingsHelper.php` | Global `ai_setting()` helper |
| `app/Modules/AiSettings/Providers/AiSettingsServiceProvider.php` | Overrides SDK config at boot |
| `app/Panels/Admin/Controllers/AiSettingsController.php` | Admin controller (index + update) |
| `resources/views/panels/admin/ai-settings/index.blade.php` | Tabbed settings view |

---

## Testing

The Laravel AI SDK includes faking utilities for all capabilities:

```php
use Laravel\Ai\Facades\Ai;
use Laravel\Ai\Image;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;

// Fake text generation
Ai::fake();
$response = (new MyAgent)->prompt('test');
Ai::assertPrompted();

// Fake image generation
Image::fake();
$image = Image::of('test')->generate();
Image::assertGenerated();

// Fake audio generation
Audio::fake();
$audio = Audio::of('test')->generate();
Audio::assertGenerated();

// Fake transcription
Transcription::fake('This is the transcribed text.');
$transcript = Transcription::fromStorage('test.mp3')->generate();
Transcription::assertTranscribed();
```

These fakes prevent real API calls and return predictable responses for your test suite.

---

## Troubleshooting

### "Provider not configured" error

The provider is not enabled or missing an API key. Go to Settings → AI Settings → select the provider tab, enable it, and enter valid credentials.

### AI SDK not using admin panel credentials

The `AiSettingsServiceProvider` overrides config at boot time. If you're still seeing `.env` values:
1. Clear config cache: `php artisan config:clear`
2. Verify settings are saved: `php artisan tinker --execute="echo ai_setting('openai_api_key');"`
3. Check the provider is enabled: `php artisan tinker --execute="echo ai_setting('openai_enabled') ? 'yes' : 'no';"`

### Settings not appearing after adding a new provider

Run the seeder to populate defaults:
```bash
php artisan db:seed --class="App\Modules\AiSettings\Database\Seeders\AiSettingSeeder"
php artisan config:clear
```

### Ollama connection refused

1. Make sure Ollama is running: `ollama serve`
2. Check the base URL in Settings → AI Settings → Ollama (default: `http://localhost:11434`)
3. If running in Docker, use the host IP instead of `localhost`

### "Class not found" for Agent or Tool

Run the artisan command to create them:
```bash
php artisan make:agent MyAgent
php artisan make:tool MyTool
```

Agent files are created in `app/Ai/Agents/`, tool files in `app/Ai/Tools/`.

### Conversation history not persisting

Make sure the `agent_conversations` and `agent_conversation_messages` tables exist:
```bash
php artisan migrate
```

Your agent must implement `Conversational` and use `RemembersConversations`:
```php
class MyAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;
    // ...
}
```

### Rate limits or timeouts

Override timeout per-call:
```php
$response = (new MyAgent)->prompt('...', timeout: 120);
```

For rate limits, consider using a different provider or implementing queued generation with Laravel jobs.
