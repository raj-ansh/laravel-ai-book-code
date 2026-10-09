<?php

return [

    /*
    | Which provider and model each AI feature uses. Leave a value
    | empty to use the default from config/ai.php.
    */

    'ai' => [
        'copywriter' => [
            'provider' => env('ACME_COPYWRITER_PROVIDER'),
            'model' => env('ACME_COPYWRITER_MODEL'),
        ],

        /*
        | The active version of each prompt in resources/prompts.
        */

        'prompts' => [
            'manager-briefing' => env('ACME_PROMPT_BRIEFING', 'v2'),
        ],

        /*
        | Queued AI jobs allowed per minute (Chapter 13).
        */

        'jobs_per_minute' => (int) env('ACME_AI_JOBS_PER_MINUTE', 30),

        /*
        | How reviews are classified (Chapter 22): "api" for the
        | Classification API, "agent" for structured output.
        */

        'review_classifier' => env('ACME_REVIEW_CLASSIFIER', 'agent'),

        /*
        | Acme's brand voice (Chapter 37): rules every piece of
        | generated marketing copy is checked against.
        */

        'brand' => [
            'voice' => 'Warm, plain and specific. Short sentences. '
                .'British spelling. No hype.',
            'banned' => [
                'best ever', 'world-class', 'guaranteed', 'cheapest',
                'revolutionary', 'must-have', 'perfect for everyone',
            ],
            'limits' => [
                'description' => 600,
                'seo_title' => 60,
                'meta_description' => 155,
                'social_post' => 220,
            ],
        ],

        /*
        | A model that can see images (Chapter 26).
        */

        'vision' => [
            'provider' => env('ACME_VISION_PROVIDER'),
            'model' => env('ACME_VISION_MODEL'),
        ],

        /*
        | Embeddings (Chapter 27). Some models, such as
        | nomic-embed-text, expect a task prefix on every input.
        */

        'embeddings' => [
            'prefixes' => [
                'query' => env('ACME_EMBED_QUERY_PREFIX', ''),
                'document' => env('ACME_EMBED_DOCUMENT_PREFIX', ''),
            ],
            'faq_min_score' => (float) env('ACME_FAQ_MIN_SCORE', 0.6),

            // Must match the embedding model (Chapter 28).
            'dimensions' => (int) env('ACME_EMBED_DIMENSIONS', 768),
        ],

        /*
        | The handbook in a provider's vector store (Chapter 29).
        */

        /*
        | Reranking (Chapter 30): "provider" for a reranking API
        | such as Cohere, "judge" for a chat model scoring results.
        */

        'reranker' => env('ACME_RERANKER', 'judge'),

        /*
        | Chunk size for ingested documents (Chapter 34), chosen
        | by measuring retrieval on real questions.
        */

        'rag_chunk_chars' => (int) env('ACME_RAG_CHUNK_CHARS', 300),

        'handbook_store' => [
            'id' => env('ACME_HANDBOOK_STORE_ID'),
            'provider' => env('ACME_HANDBOOK_STORE_PROVIDER', 'openai'),
        ],

        /*
        | Output guards (Chapter 42). The canary is a secret
        | string placed in system prompts: if it ever appears in
        | a reply, the prompt has leaked. Rotate it like a key.
        */

        'security' => [
            'canary' => env('ACME_AI_CANARY', 'ACME-7F3A9C'),
            'link_hosts' => ['acme.test', 'www.acme.test'],
        ],

        /*
        | AI features per plan (Chapter 43). "consumer" applies
        | to customers without an organisation.
        */

        'plans' => [
            'consumer' => ['support_assistant'],
            'none' => [],
            'basic' => ['support_assistant', 'account_assistant'],
            'pro' => [
                'support_assistant', 'account_assistant',
                'knowledge_search', 'document_intake',
            ],
        ],

        /*
        | What a tenant may choose. Never free text into a
        | system prompt: a choice from a list, mapped by code.
        */

        /*
        | Prices in USD per million tokens (Chapter 44), keyed
        | "provider/model" or "provider". Copy them from your
        | provider's current price list; they change.
        */

        'pricing' => [
            'ollama' => ['input' => 0, 'output' => 0],
            'openai' => [
                'input' => (float) env('ACME_PRICE_OPENAI_IN', 0),
                'output' => (float) env('ACME_PRICE_OPENAI_OUT', 0),
            ],
        ],

        /*
        | Token allowances (Chapter 44): organisations per
        | month by plan, consumers per day.
        */

        'token_budgets' => [
            'consumer_daily' => (int) env('ACME_AI_CONSUMER_DAILY', 20_000),
            'none' => 0,
            'basic' => (int) env('ACME_AI_BASIC_MONTHLY', 300_000),
            'pro' => (int) env('ACME_AI_PRO_MONTHLY', 3_000_000),
        ],

        /*
        | Resilience (Chapter 45): providers in the order to try
        | them, and when to stop trying one that keeps failing.
        */

        'failover' => [
            'ollama-primary' => 'qwen2.5:1.5b',
            'ollama' => 'qwen2.5:1.5b',
        ],

        'circuit' => [
            'failures' => 3,     // within this many seconds:
            'window' => 60,
            'cooldown' => 120,   // then skip the provider this long
        ],

        'tenant_options' => [
            'models' => ['qwen2.5:1.5b', 'qwen2.5:3b'],
            'tones' => [
                'friendly' => 'Be warm and brief. Use the '
                    .'customer\'s first name.',
                'formal' => 'Be formal and precise. Use full '
                    .'sentences and no exclamation marks.',
            ],
        ],
    ],

];
