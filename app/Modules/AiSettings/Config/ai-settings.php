<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Settings
    |--------------------------------------------------------------------------
    |
    | Each group below appears as a tab in the AI Settings page.
    | The 'general' group configures default providers and models.
    | Each provider group configures credentials and connection details.
    |
    | Group-level keys:
    |   label        – Tab label
    |   icon         – Phosphor icon class
    |   description  – Shown below the tab title
    |   layout       – 'full' for full-width layout
    |   settings     – Field definitions (same format as config/settings.php)
    |
    | Setting-level extra keys:
    |   layout       – 'sidebar' renders in right column; default renders in left
    |
    */

    'general' => [
        'label' => 'General',
        'icon' => 'ph ph-brain',
        'description' => 'Default AI provider and model selections for each capability',
        'layout' => 'full',
        'card_groups' => true,
        'settings' => [
            'ai_default_text_provider' => [
                'type' => 'select',
                'label' => 'Provider',
                'hint' => 'Provider used for text generation (agents, prompts, chat)',
                'default' => '',
                'rules' => 'nullable|string|max:50',
                'options' => [
                    '' => '— Select Provider —',
                    'openai' => 'OpenAI',
                    'anthropic' => 'Anthropic',
                    'gemini' => 'Google Gemini',
                    'azure-openai' => 'Azure OpenAI',
                    'groq' => 'Groq',
                    'xai' => 'xAI',
                    'deepseek' => 'DeepSeek',
                    'mistral' => 'Mistral',
                    'ollama' => 'Ollama',
                ],
                'card_group' => ['label' => 'Text Generation', 'icon' => 'ph ph-chat-text', 'description' => 'Agents, prompts, and chat completions'],
            ],
            'ai_default_text_model' => [
                'type' => 'text',
                'label' => 'Model',
                'hint' => 'e.g. gpt-4o, claude-sonnet-4-20250514, gemini-2.0-flash',
                'default' => '',
                'rules' => 'nullable|string|max:100',
                'card_group' => ['label' => 'Text Generation'],
            ],
            'ai_default_image_provider' => [
                'type' => 'select',
                'label' => 'Provider',
                'hint' => 'Provider used for image generation',
                'default' => '',
                'rules' => 'nullable|string|max:50',
                'options' => [
                    '' => '— Select Provider —',
                    'openai' => 'OpenAI (DALL-E)',
                    'gemini' => 'Google Gemini',
                    'xai' => 'xAI',
                ],
                'card_group' => ['label' => 'Image Generation', 'icon' => 'ph ph-image', 'description' => 'AI-generated images and artwork'],
            ],
            'ai_default_image_model' => [
                'type' => 'text',
                'label' => 'Model',
                'hint' => 'e.g. dall-e-3, gemini-2.0-flash',
                'default' => '',
                'rules' => 'nullable|string|max:100',
                'card_group' => ['label' => 'Image Generation'],
            ],
            'ai_default_tts_provider' => [
                'type' => 'select',
                'label' => 'Provider',
                'hint' => 'Provider used for text-to-speech',
                'default' => '',
                'rules' => 'nullable|string|max:50',
                'options' => [
                    '' => '— Select Provider —',
                    'openai' => 'OpenAI',
                    'elevenlabs' => 'ElevenLabs',
                ],
                'card_group' => ['label' => 'Text-to-Speech', 'icon' => 'ph ph-speaker-high', 'description' => 'Convert text into spoken audio'],
            ],
            'ai_default_tts_model' => [
                'type' => 'text',
                'label' => 'Model',
                'hint' => 'e.g. tts-1, tts-1-hd',
                'default' => '',
                'rules' => 'nullable|string|max:100',
                'card_group' => ['label' => 'Text-to-Speech'],
            ],
            'ai_default_stt_provider' => [
                'type' => 'select',
                'label' => 'Provider',
                'hint' => 'Provider used for speech-to-text (transcription)',
                'default' => '',
                'rules' => 'nullable|string|max:50',
                'options' => [
                    '' => '— Select Provider —',
                    'openai' => 'OpenAI (Whisper)',
                    'elevenlabs' => 'ElevenLabs',
                    'mistral' => 'Mistral',
                ],
                'card_group' => ['label' => 'Speech-to-Text', 'icon' => 'ph ph-microphone', 'description' => 'Transcribe audio into text'],
            ],
            'ai_default_stt_model' => [
                'type' => 'text',
                'label' => 'Model',
                'hint' => 'e.g. whisper-1',
                'default' => '',
                'rules' => 'nullable|string|max:100',
                'card_group' => ['label' => 'Speech-to-Text'],
            ],
            'ai_default_embeddings_provider' => [
                'type' => 'select',
                'label' => 'Provider',
                'hint' => 'Provider used for generating vector embeddings',
                'default' => '',
                'rules' => 'nullable|string|max:50',
                'options' => [
                    '' => '— Select Provider —',
                    'openai' => 'OpenAI',
                    'gemini' => 'Google Gemini',
                    'azure-openai' => 'Azure OpenAI',
                    'cohere' => 'Cohere',
                    'mistral' => 'Mistral',
                    'jina' => 'Jina',
                    'voyageai' => 'VoyageAI',
                ],
                'card_group' => ['label' => 'Embeddings', 'icon' => 'ph ph-graph', 'description' => 'Vector embeddings for search and RAG'],
            ],
            'ai_default_embeddings_model' => [
                'type' => 'text',
                'label' => 'Model',
                'hint' => 'e.g. text-embedding-3-small, embed-english-v3.0',
                'default' => '',
                'rules' => 'nullable|string|max:100',
                'card_group' => ['label' => 'Embeddings'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Database
    |--------------------------------------------------------------------------
    */
    'vector-database' => [
        'label' => 'Vector Database',
        'icon' => 'ph ph-database',
        'description' => 'Semantic search storage for chatbot knowledge bases',
        'layout' => 'full',
        'settings' => [
            'vector_database_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Vector Database',
                'hint' => 'Use Qdrant for semantic knowledge base search when available',
                'default' => false,
            ],
            'vector_database_mode' => [
                'type' => 'select',
                'label' => 'Environment Mode',
                'hint' => 'Local mode does not require an API key; Cloud mode requires Qdrant credentials',
                'default' => 'local',
                'rules' => 'nullable|in:local,cloud',
                'options' => [
                    'local' => 'Local',
                    'cloud' => 'Cloud',
                ],
            ],
            'vector_database_provider' => [
                'type' => 'select',
                'label' => 'Provider',
                'hint' => 'Vector database provider',
                'default' => 'qdrant',
                'rules' => 'nullable|in:qdrant',
                'options' => [
                    'qdrant' => 'Qdrant',
                ],
            ],
            'qdrant_url' => [
                'type' => 'text',
                'label' => 'Qdrant URL',
                'hint' => 'Local: http://localhost:6333. Cloud: your Qdrant Cloud cluster URL',
                'default' => env('QDRANT_URL', 'http://localhost:6333'),
                'rules' => 'nullable|url|max:500',
            ],
            'qdrant_api_key' => [
                'type' => 'password',
                'label' => 'Qdrant API Key',
                'hint' => 'Required for Qdrant Cloud; optional for local development',
                'default' => env('QDRANT_API_KEY', ''),
                'rules' => 'nullable|string|max:1000',
                'encrypted' => true,
            ],
            'qdrant_collection' => [
                'type' => 'text',
                'label' => 'Collection Name',
                'hint' => 'Collection used for knowledge base vectors',
                'default' => env('QDRANT_COLLECTION', 'knowledge_base_chunks'),
                'rules' => 'nullable|string|max:100',
            ],
            'qdrant_vector_dimension' => [
                'type' => 'integer',
                'label' => 'Vector Dimension',
                'hint' => 'Use 1536 for OpenAI text-embedding-3-small',
                'default' => 1536,
                'rules' => 'nullable|integer|min:1|max:8192',
            ],
            'qdrant_search_limit' => [
                'type' => 'integer',
                'label' => 'Search Result Limit',
                'hint' => 'Maximum chunks returned from vector search',
                'default' => 5,
                'rules' => 'nullable|integer|min:1|max:25',
            ],
            'qdrant_score_threshold' => [
                'type' => 'float',
                'label' => 'Score Threshold',
                'hint' => 'Minimum similarity score required from Qdrant',
                'default' => 0.2,
                'rules' => 'nullable|numeric|min:0|max:1',
            ],
            'qdrant_timeout' => [
                'type' => 'integer',
                'label' => 'Request Timeout',
                'hint' => 'Timeout in seconds for Qdrant requests',
                'default' => 10,
                'rules' => 'nullable|integer|min:1|max:60',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'label' => 'OpenAI',
        'icon' => 'ph ph-open-ai-logo',
        'description' => 'GPT models, DALL-E image generation, Whisper STT, and TTS',
        'layout' => 'full',
        'settings' => [
            'openai_enabled' => [
                'type' => 'feature',
                'label' => 'Enable OpenAI',
                'hint' => 'Use OpenAI for text generation, image generation, TTS, STT, and embeddings',
                'default' => false,
            ],
            'openai_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your OpenAI API key (sk-...)',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'openai_organization_id' => [
                'type' => 'text',
                'label' => 'Organization ID',
                'hint' => 'Optional OpenAI organization ID (org-...)',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'openai_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default api.openai.com)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'openai_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic
    |--------------------------------------------------------------------------
    */
    'anthropic' => [
        'label' => 'Anthropic',
        'icon' => 'ph ph-robot',
        'description' => 'Claude models for text generation and analysis',
        'layout' => 'full',
        'settings' => [
            'anthropic_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Anthropic',
                'hint' => 'Use Anthropic Claude models for text generation',
                'default' => false,
            ],
            'anthropic_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Anthropic API key (sk-ant-...)',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'anthropic_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default api.anthropic.com)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'anthropic_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'label' => 'Google Gemini',
        'icon' => 'ph ph-google-logo',
        'description' => 'Gemini models for text generation, image generation, and embeddings',
        'layout' => 'full',
        'settings' => [
            'gemini_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Google Gemini',
                'hint' => 'Use Google Gemini for text, image generation, and embeddings',
                'default' => false,
            ],
            'gemini_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Google Gemini API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'gemini_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'gemini_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Azure OpenAI
    |--------------------------------------------------------------------------
    */
    'azure-openai' => [
        'label' => 'Azure OpenAI',
        'icon' => 'ph ph-microsoft-outlook-logo',
        'description' => 'Azure-hosted OpenAI models for text generation and embeddings',
        'layout' => 'full',
        'settings' => [
            'azure_openai_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Azure OpenAI',
                'hint' => 'Use Azure OpenAI for text generation and embeddings',
                'default' => false,
            ],
            'azure_openai_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Azure OpenAI API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'azure_openai_base_url' => [
                'type' => 'text',
                'label' => 'Endpoint URL',
                'hint' => 'Your Azure OpenAI endpoint (e.g. https://your-resource.openai.azure.com)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'azure_openai_api_version' => [
                'type' => 'text',
                'label' => 'API Version',
                'hint' => 'Azure OpenAI API version (e.g. 2024-02-01)',
                'default' => '2024-02-01',
                'rules' => 'nullable|string|max:20',
            ],
            'azure_openai_deployment' => [
                'type' => 'text',
                'label' => 'Deployment Name',
                'hint' => 'The name of your Azure OpenAI deployment',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'azure_openai_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq
    |--------------------------------------------------------------------------
    */
    'groq' => [
        'label' => 'Groq',
        'icon' => 'ph ph-lightning',
        'description' => 'Ultra-fast inference for open-source LLMs',
        'layout' => 'full',
        'settings' => [
            'groq_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Groq',
                'hint' => 'Use Groq for high-speed text generation',
                'default' => false,
            ],
            'groq_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Groq API key (gsk_...)',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'groq_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'groq_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | xAI
    |--------------------------------------------------------------------------
    */
    'xai' => [
        'label' => 'xAI',
        'icon' => 'ph ph-robot',
        'description' => 'Grok models for text generation and image generation',
        'layout' => 'full',
        'settings' => [
            'xai_enabled' => [
                'type' => 'feature',
                'label' => 'Enable xAI',
                'hint' => 'Use xAI Grok models for text and image generation',
                'default' => false,
            ],
            'xai_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your xAI API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'xai_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'xai_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DeepSeek
    |--------------------------------------------------------------------------
    */
    'deepseek' => [
        'label' => 'DeepSeek',
        'icon' => 'ph ph-magnifying-glass-plus',
        'description' => 'DeepSeek models for text generation and reasoning',
        'layout' => 'full',
        'settings' => [
            'deepseek_enabled' => [
                'type' => 'feature',
                'label' => 'Enable DeepSeek',
                'hint' => 'Use DeepSeek models for text generation',
                'default' => false,
            ],
            'deepseek_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your DeepSeek API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'deepseek_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'deepseek_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mistral
    |--------------------------------------------------------------------------
    */
    'mistral' => [
        'label' => 'Mistral',
        'icon' => 'ph ph-wind',
        'description' => 'Mistral models for text generation, speech-to-text, and embeddings',
        'layout' => 'full',
        'settings' => [
            'mistral_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Mistral',
                'hint' => 'Use Mistral for text generation, STT, and embeddings',
                'default' => false,
            ],
            'mistral_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Mistral API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'mistral_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'mistral_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ollama (Local)
    |--------------------------------------------------------------------------
    */
    'ollama' => [
        'label' => 'Ollama',
        'icon' => 'ph ph-desktop',
        'description' => 'Run open-source models locally via Ollama',
        'layout' => 'full',
        'settings' => [
            'ollama_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Ollama',
                'hint' => 'Use locally hosted models via Ollama (no API key required)',
                'default' => false,
            ],
            'ollama_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Ollama server URL (default: http://localhost:11434)',
                'default' => 'http://localhost:11434',
                'rules' => 'nullable|string|max:500',
            ],
            'ollama_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs
    |--------------------------------------------------------------------------
    */
    'elevenlabs' => [
        'label' => 'ElevenLabs',
        'icon' => 'ph ph-speaker-high',
        'description' => 'High-quality text-to-speech and speech-to-text',
        'layout' => 'full',
        'settings' => [
            'elevenlabs_enabled' => [
                'type' => 'feature',
                'label' => 'Enable ElevenLabs',
                'hint' => 'Use ElevenLabs for text-to-speech and speech-to-text',
                'default' => false,
            ],
            'elevenlabs_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your ElevenLabs API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'elevenlabs_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'elevenlabs_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cohere
    |--------------------------------------------------------------------------
    */
    'cohere' => [
        'label' => 'Cohere',
        'icon' => 'ph ph-circles-three-plus',
        'description' => 'Embeddings and search result reranking',
        'layout' => 'full',
        'settings' => [
            'cohere_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Cohere',
                'hint' => 'Use Cohere for embeddings and reranking',
                'default' => false,
            ],
            'cohere_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Cohere API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'cohere_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'cohere_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Jina
    |--------------------------------------------------------------------------
    */
    'jina' => [
        'label' => 'Jina',
        'icon' => 'ph ph-flow-arrow',
        'description' => 'Embeddings and search result reranking',
        'layout' => 'full',
        'settings' => [
            'jina_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Jina',
                'hint' => 'Use Jina for embeddings and reranking',
                'default' => false,
            ],
            'jina_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your Jina API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'jina_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'jina_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | VoyageAI
    |--------------------------------------------------------------------------
    */
    'voyageai' => [
        'label' => 'VoyageAI',
        'icon' => 'ph ph-compass',
        'description' => 'High-quality vector embeddings for search and RAG',
        'layout' => 'full',
        'settings' => [
            'voyageai_enabled' => [
                'type' => 'feature',
                'label' => 'Enable VoyageAI',
                'hint' => 'Use VoyageAI for generating vector embeddings',
                'default' => false,
            ],
            'voyageai_api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'hint' => 'Your VoyageAI API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            'voyageai_base_url' => [
                'type' => 'text',
                'label' => 'Base URL',
                'hint' => 'Custom API base URL (leave empty for default)',
                'default' => '',
                'rules' => 'nullable|string|max:500',
            ],
            'voyageai_logo' => [
                'type' => 'media',
                'label' => 'Provider Logo',
                'hint' => 'Logo displayed in AI provider listings',
                'default' => null,
                'accept' => 'image',
                'layout' => 'sidebar',
            ],
        ],
    ],

];
