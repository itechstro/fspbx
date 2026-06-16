<?php

namespace App\Services;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Cache;

class AiUsageRatesService
{
    private const CACHE_KEY = 'ai_usage_rates';

    private const CACHE_TTL_SECONDS = 300;

    public function schema(): array
    {
        return [
            [
                'group' => 'AssemblyAI models',
                'description' => 'USD per hour of audio.',
                'fields' => [
                    ['key' => 'assemblyai_universal_2_hourly_usd', 'label' => 'Universal-2 hourly rate'],
                    ['key' => 'assemblyai_universal_3_pro_hourly_usd', 'label' => 'Universal-3 Pro hourly rate'],
                ],
            ],
            [
                'group' => 'AssemblyAI add-ons',
                'description' => 'USD per hour when the add-on is enabled on a transcription request.',
                'fields' => [
                    ['key' => 'assemblyai_sentiment_hourly_usd', 'label' => 'Sentiment analysis'],
                    ['key' => 'assemblyai_entity_hourly_usd', 'label' => 'Entity detection'],
                    ['key' => 'assemblyai_highlights_hourly_usd', 'label' => 'Auto highlights'],
                    ['key' => 'assemblyai_content_safety_hourly_usd', 'label' => 'Content safety'],
                    ['key' => 'assemblyai_summarization_hourly_usd', 'label' => 'Summarization'],
                ],
            ],
            [
                'group' => 'OpenAI models',
                'description' => 'USD per 1M tokens.',
                'fields' => [
                    ['key' => 'openai_gpt5_nano_input_per_million_usd', 'label' => 'GPT-5 nano input'],
                    ['key' => 'openai_gpt5_nano_output_per_million_usd', 'label' => 'GPT-5 nano output'],
                    ['key' => 'openai_gpt41_mini_input_per_million_usd', 'label' => 'GPT-4.1 mini input'],
                    ['key' => 'openai_gpt41_mini_output_per_million_usd', 'label' => 'GPT-4.1 mini output'],
                ],
            ],
            [
                'group' => 'OpenAI spend reserves',
                'description' => 'Estimated USD reserved before starting a job for tenant limit checks.',
                'fields' => [
                    ['key' => 'openai_reserve_summary_usd', 'label' => 'Call summary reserve'],
                    ['key' => 'openai_reserve_translation_usd', 'label' => 'Translation reserve'],
                    ['key' => 'openai_reserve_executive_summary_usd', 'label' => 'Executive summary reserve'],
                ],
            ],
        ];
    }

    public function allKeys(): array
    {
        return collect($this->schema())
            ->flatMap(fn (array $group) => collect($group['fields'])->pluck('key'))
            ->values()
            ->all();
    }

    public function getRates(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $flat = $this->mergeFlatRates();

            return $this->toNestedRates($flat);
        });
    }

    public function getFlatRates(): array
    {
        return $this->mergeFlatRates();
    }

    public function saveFlatRates(array $input): void
    {
        $defaults = $this->fileDefaults();

        foreach ($this->allKeys() as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];
            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value) || (float) $value < 0) {
                throw new \InvalidArgumentException("Rate {$key} must be a non-negative number.");
            }

            $normalized = $this->normalizeValue((string) $value);
            $meta = $this->settingMeta($key);

            DefaultSettings::query()->updateOrCreate(
                [
                    'default_setting_category' => 'ai_usage',
                    'default_setting_subcategory' => $key,
                ],
                [
                    'default_setting_name' => 'numeric',
                    'default_setting_value' => $normalized,
                    'default_setting_enabled' => 'true',
                    'default_setting_description' => (string) ($meta['label'] ?? ''),
                ]
            );
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function mergeFlatRates(): array
    {
        $merged = $this->fileDefaults();
        $stored = DefaultSettings::query()
            ->where('default_setting_category', 'ai_usage')
            ->whereIn('default_setting_subcategory', $this->allKeys())
            ->where('default_setting_enabled', 'true')
            ->pluck('default_setting_value', 'default_setting_subcategory')
            ->all();

        foreach ($stored as $key => $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $merged[$key] = (float) $value;
            }
        }

        return $merged;
    }

    protected function toNestedRates(array $flat): array
    {
        return [
            'assemblyai' => [
                'default_speech_model' => (string) config('ai_usage.assemblyai.default_speech_model', 'universal-2'),
                'models' => [
                    'universal-2' => [
                        'hourly_usd' => (float) ($flat['assemblyai_universal_2_hourly_usd'] ?? 0.15),
                    ],
                    'universal-3-pro' => [
                        'hourly_usd' => (float) ($flat['assemblyai_universal_3_pro_hourly_usd'] ?? 0.21),
                    ],
                ],
                'addons_hourly_usd' => [
                    'sentiment_analysis' => (float) ($flat['assemblyai_sentiment_hourly_usd'] ?? 0.02),
                    'entity_detection' => (float) ($flat['assemblyai_entity_hourly_usd'] ?? 0.02),
                    'auto_highlights' => (float) ($flat['assemblyai_highlights_hourly_usd'] ?? 0.02),
                    'content_safety' => (float) ($flat['assemblyai_content_safety_hourly_usd'] ?? 0.02),
                    'summarization' => (float) ($flat['assemblyai_summarization_hourly_usd'] ?? 0.02),
                ],
            ],
            'openai' => [
                'reserve_summary_usd' => (float) ($flat['openai_reserve_summary_usd'] ?? 0.01),
                'reserve_translation_usd' => (float) ($flat['openai_reserve_translation_usd'] ?? 0.02),
                'reserve_executive_summary_usd' => (float) ($flat['openai_reserve_executive_summary_usd'] ?? 0.05),
                'models' => [
                    'gpt-5-nano' => [
                        'input_per_million_usd' => (float) ($flat['openai_gpt5_nano_input_per_million_usd'] ?? 0.05),
                        'output_per_million_usd' => (float) ($flat['openai_gpt5_nano_output_per_million_usd'] ?? 0.40),
                    ],
                    'gpt-4.1-mini' => [
                        'input_per_million_usd' => (float) ($flat['openai_gpt41_mini_input_per_million_usd'] ?? 0.40),
                        'output_per_million_usd' => (float) ($flat['openai_gpt41_mini_output_per_million_usd'] ?? 1.60),
                    ],
                ],
            ],
        ];
    }

    protected function fileDefaults(): array
    {
        return [
            'assemblyai_universal_2_hourly_usd' => (float) config('ai_usage.assemblyai.models.universal-2.hourly_usd', 0.15),
            'assemblyai_universal_3_pro_hourly_usd' => (float) config('ai_usage.assemblyai.models.universal-3-pro.hourly_usd', 0.21),
            'assemblyai_sentiment_hourly_usd' => (float) config('ai_usage.assemblyai.addons_hourly_usd.sentiment_analysis', 0.02),
            'assemblyai_entity_hourly_usd' => (float) config('ai_usage.assemblyai.addons_hourly_usd.entity_detection', 0.02),
            'assemblyai_highlights_hourly_usd' => (float) config('ai_usage.assemblyai.addons_hourly_usd.auto_highlights', 0.02),
            'assemblyai_content_safety_hourly_usd' => (float) config('ai_usage.assemblyai.addons_hourly_usd.content_safety', 0.02),
            'assemblyai_summarization_hourly_usd' => (float) config('ai_usage.assemblyai.addons_hourly_usd.summarization', 0.02),
            'openai_gpt5_nano_input_per_million_usd' => (float) config('ai_usage.openai.models.gpt-5-nano.input_per_million_usd', 0.05),
            'openai_gpt5_nano_output_per_million_usd' => (float) config('ai_usage.openai.models.gpt-5-nano.output_per_million_usd', 0.40),
            'openai_gpt41_mini_input_per_million_usd' => (float) config('ai_usage.openai.models.gpt-4.1-mini.input_per_million_usd', 0.40),
            'openai_gpt41_mini_output_per_million_usd' => (float) config('ai_usage.openai.models.gpt-4.1-mini.output_per_million_usd', 1.60),
            'openai_reserve_summary_usd' => (float) config('ai_usage.openai.reserve_summary_usd', 0.01),
            'openai_reserve_translation_usd' => (float) config('ai_usage.openai.reserve_translation_usd', 0.02),
            'openai_reserve_executive_summary_usd' => (float) config('ai_usage.openai.reserve_executive_summary_usd', 0.05),
        ];
    }

    protected function settingMeta(string $key): array
    {
        foreach ($this->schema() as $group) {
            foreach ($group['fields'] as $field) {
                if (($field['key'] ?? null) === $key) {
                    return $field;
                }
            }
        }

        return ['label' => $key];
    }

    protected function normalizeValue(string $value): string
    {
        $number = (float) $value;

        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.') ?: '0';
    }
}
