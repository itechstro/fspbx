<?php

namespace App\Services;

use App\Models\CallTranscription;

class AiCostEstimationService
{
    public function __construct(
        protected AiUsageRatesService $ratesService,
    ) {
    }

    public function estimateAssemblyAiTranscription(
        int $durationSeconds,
        ?string $speechModel,
        array $requestPayload = [],
    ): array {
        $durationSeconds = max(0, $durationSeconds);
        $model = $this->normalizeSpeechModel($speechModel);
        $rates = $this->ratesService->getRates();
        $hourlyBase = (float) data_get($rates, "assemblyai.models.{$model}.hourly_usd", 0.15);
        $hours = $durationSeconds / 3600;
        $baseCost = $hours * $hourlyBase;
        $addonCosts = [];

        foreach ((array) data_get($rates, 'assemblyai.addons_hourly_usd', []) as $addon => $hourlyRate) {
            if (! $this->isAssemblyAiAddonEnabled($addon, $requestPayload)) {
                continue;
            }

            $addonCosts[$addon] = round($hours * (float) $hourlyRate, 6);
        }

        $total = round($baseCost + array_sum($addonCosts), 6);

        return [
            'cost_usd' => $total,
            'duration_seconds' => $durationSeconds,
            'speech_model' => $model,
            'breakdown' => [
                'base_usd' => round($baseCost, 6),
                'addons_usd' => $addonCosts,
            ],
        ];
    }

    public function estimateOpenAiUsage(?string $model, array $usage): array
    {
        $model = $this->normalizeOpenAiModel($model);
        $inputTokens = max(0, (int) data_get($usage, 'input_tokens', 0));
        $outputTokens = max(0, (int) data_get($usage, 'output_tokens', 0));
        $totalTokens = max($inputTokens + $outputTokens, (int) data_get($usage, 'total_tokens', 0));

        $models = (array) data_get($this->ratesService->getRates(), 'openai.models', []);
        $rates = (array) ($models[$model] ?? []);
        $inputRate = (float) ($rates['input_per_million_usd'] ?? 0);
        $outputRate = (float) ($rates['output_per_million_usd'] ?? 0);

        $inputCost = ($inputTokens / 1_000_000) * $inputRate;
        $outputCost = ($outputTokens / 1_000_000) * $outputRate;

        return [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => round($inputCost + $outputCost, 6),
            'breakdown' => [
                'input_usd' => round($inputCost, 6),
                'output_usd' => round($outputCost, 6),
            ],
        ];
    }

    public function recalculateCallTotal(array|object $row): float
    {
        $transcription = (float) data_get($row, 'transcription_cost_usd', 0);
        $summary = (float) data_get($row, 'summary_cost_usd', 0);
        $translation = (float) data_get($row, 'translation_cost_usd', 0);

        return round($transcription + $summary + $translation, 6);
    }

    /**
     * Estimate summary usage when OpenAI no longer returns the background response.
     */
    public function estimateOpenAiSummaryFromStoredRow(CallTranscription $row): array
    {
        $model = (string) ($row->summary_model ?? 'gpt-5-nano');

        if ((int) ($row->summary_input_tokens ?? 0) > 0 || (int) ($row->summary_output_tokens ?? 0) > 0) {
            return $this->estimateOpenAiUsage($model, [
                'input_tokens' => (int) ($row->summary_input_tokens ?? 0),
                'output_tokens' => (int) ($row->summary_output_tokens ?? 0),
            ]);
        }

        $lines = $this->buildUtteranceLines((array) data_get($row->result_payload, 'utterances', []));
        $inputText = implode("\n", array_merge(
            [
                'Using the utterances below (speaker-labeled, no timestamps), produce the Output JSON.',
                'Return ONLY the JSON object (no markdown, no commentary).',
                '',
                'Utterances:',
            ],
            $lines,
        ));
        $outputText = json_encode($row->summary_payload ?? [], JSON_UNESCAPED_UNICODE) ?: '';

        $inputTokens = $this->estimateTokensFromText($inputText) + 280;
        $outputTokens = $this->estimateTokensFromText($outputText);

        return $this->estimateOpenAiUsage($model, [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);
    }

    /**
     * Estimate translation usage when OpenAI no longer returns the background response.
     */
    public function estimateOpenAiTranslationFromStoredRow(CallTranscription $row): array
    {
        $model = (string) ($row->translation_model ?? 'gpt-4.1-mini');

        if ((int) ($row->translation_input_tokens ?? 0) > 0 || (int) ($row->translation_output_tokens ?? 0) > 0) {
            return $this->estimateOpenAiUsage($model, [
                'input_tokens' => (int) ($row->translation_input_tokens ?? 0),
                'output_tokens' => (int) ($row->translation_output_tokens ?? 0),
            ]);
        }

        $utterances = (array) data_get($row->result_payload, 'utterances', []);
        $summaryText = trim((string) data_get($row->summary_payload, 'summary', ''));
        $targetLanguage = trim((string) ($row->translation_target_language ?? 'unknown'));

        if ($utterances !== []) {
            $inputText = implode("\n\n", [
                "Target language: {$targetLanguage}",
                'Utterances JSON:',
                json_encode($utterances, JSON_UNESCAPED_UNICODE) ?: '',
                'Summary:',
                $summaryText !== '' ? $summaryText : '[none]',
            ]);
            $outputText = json_encode($row->translation_payload ?? [], JSON_UNESCAPED_UNICODE) ?: '';
        } else {
            $transcriptText = trim((string) data_get($row->result_payload, 'text', ''));
            $inputText = implode("\n\n", [
                "Target language: {$targetLanguage}",
                'Transcript:',
                $transcriptText,
                'Summary:',
                $summaryText !== '' ? $summaryText : '[none]',
            ]);
            $outputText = json_encode($row->translation_payload ?? [], JSON_UNESCAPED_UNICODE) ?: '';
        }

        $inputTokens = $this->estimateTokensFromText($inputText) + 180;
        $outputTokens = $this->estimateTokensFromText($outputText);

        return $this->estimateOpenAiUsage($model, [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ]);
    }

    protected function buildUtteranceLines(array $utterances): array
    {
        $lines = [];

        foreach ($utterances as $utterance) {
            $speaker = data_get($utterance, 'speaker');
            $text = trim((string) data_get($utterance, 'text', ''));
            if ($speaker && $text !== '') {
                $lines[] = "{$speaker}: {$text}";
            }
        }

        return $lines;
    }

    protected function estimateTokensFromText(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return max(1, (int) ceil(mb_strlen($text, 'UTF-8') / 4));
    }

    protected function normalizeSpeechModel(?string $speechModel): string
    {
        $model = strtolower(trim((string) $speechModel));

        if ($model === '') {
            return (string) data_get($this->ratesService->getRates(), 'assemblyai.default_speech_model', 'universal-2');
        }

        return $model;
    }

    protected function normalizeOpenAiModel(?string $model): string
    {
        $model = trim((string) $model);
        if ($model === '') {
            return 'gpt-4.1-mini';
        }

        $configuredModels = array_keys((array) data_get($this->ratesService->getRates(), 'openai.models', []));
        if (in_array($model, $configuredModels, true)) {
            return $model;
        }

        foreach ($configuredModels as $configuredModel) {
            if (str_starts_with($model, $configuredModel)) {
                return $configuredModel;
            }
        }

        return $model;
    }

    protected function isAssemblyAiAddonEnabled(string $addon, array $requestPayload): bool
    {
        $value = data_get($requestPayload, $addon);

        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
