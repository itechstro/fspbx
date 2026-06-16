<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI cost estimation rates (USD)
    |--------------------------------------------------------------------------
    |
    | These rates are used for per-call estimates and tenant usage reporting.
    | Update them when provider pricing changes. Actual billing still comes from
    | AssemblyAI / OpenAI dashboards.
    |
    */
    'assemblyai' => [
        'default_speech_model' => env('AI_USAGE_ASSEMBLYAI_DEFAULT_MODEL', 'universal-2'),
        'models' => [
            'universal-3-pro' => [
                'hourly_usd' => (float) env('AI_USAGE_ASSEMBLYAI_U3_PRO_HOURLY', 0.21),
            ],
            'universal-2' => [
                'hourly_usd' => (float) env('AI_USAGE_ASSEMBLYAI_U2_HOURLY', 0.15),
            ],
        ],
        'addons_hourly_usd' => [
            'sentiment_analysis' => (float) env('AI_USAGE_ASSEMBLYAI_SENTIMENT_HOURLY', 0.02),
            'entity_detection' => (float) env('AI_USAGE_ASSEMBLYAI_ENTITY_HOURLY', 0.02),
            'auto_highlights' => (float) env('AI_USAGE_ASSEMBLYAI_HIGHLIGHTS_HOURLY', 0.02),
            'content_safety' => (float) env('AI_USAGE_ASSEMBLYAI_CONTENT_SAFETY_HOURLY', 0.02),
            'summarization' => (float) env('AI_USAGE_ASSEMBLYAI_SUMMARIZATION_HOURLY', 0.02),
        ],
    ],

        'openai' => [
            'reserve_summary_usd' => (float) env('AI_USAGE_OPENAI_RESERVE_SUMMARY_USD', 0.01),
            'reserve_translation_usd' => (float) env('AI_USAGE_OPENAI_RESERVE_TRANSLATION_USD', 0.02),
            'reserve_executive_summary_usd' => (float) env('AI_USAGE_OPENAI_RESERVE_EXEC_SUMMARY_USD', 0.05),
            'models' => [
            'gpt-5-nano' => [
                'input_per_million_usd' => (float) env('AI_USAGE_OPENAI_GPT5_NANO_INPUT_M', 0.05),
                'output_per_million_usd' => (float) env('AI_USAGE_OPENAI_GPT5_NANO_OUTPUT_M', 0.40),
            ],
            'gpt-4.1-mini' => [
                'input_per_million_usd' => (float) env('AI_USAGE_OPENAI_GPT41_MINI_INPUT_M', 0.40),
                'output_per_million_usd' => (float) env('AI_USAGE_OPENAI_GPT41_MINI_OUTPUT_M', 1.60),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant metered limit keys (stored in v_default_settings / v_domain_settings
    | under category "limit"). Null / disabled = unlimited.
    |--------------------------------------------------------------------------
    */
    'limit_metrics' => [
        'ai_transcription_minutes' => [
            'ledger_metric' => 'ai_transcription_seconds',
            'unit' => 'minutes',
            'display' => 'AI transcription minutes',
            'scale' => 60,
        ],
        'ai_summary_requests' => [
            'ledger_metric' => 'ai_summary_count',
            'unit' => 'requests',
            'display' => 'AI summary requests',
            'scale' => 1,
        ],
        'ai_translation_requests' => [
            'ledger_metric' => 'ai_translation_count',
            'unit' => 'requests',
            'display' => 'AI translation requests',
            'scale' => 1,
        ],
        'ai_executive_summary_requests' => [
            'ledger_metric' => 'ai_executive_summary_count',
            'unit' => 'requests',
            'display' => 'AI executive summary requests',
            'scale' => 1,
        ],
        'ai_spend_usd_monthly' => [
            'ledger_metric' => 'ai_spend_usd',
            'unit' => 'usd',
            'display' => 'Estimated AI spend (USD)',
            'scale' => 1,
        ],
        'outbound_minutes_monthly' => [
            'ledger_metric' => 'outbound_seconds',
            'unit' => 'minutes',
            'display' => 'Outbound call minutes',
            'scale' => 60,
        ],
    ],
];
