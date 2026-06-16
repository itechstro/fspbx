<?php

namespace App\Services;

use App\Models\CallTranscription;

class CallTranscriptionCostService
{
    public function __construct(
        protected AiCostEstimationService $costEstimationService,
        protected DomainUsageService $domainUsageService,
    ) {
    }

    public function applyTranscriptionCompletion(
        CallTranscription $row,
        array $fullTranscript,
        bool $force = false,
        bool $recordUsage = true,
    ): CallTranscription {
        if (! $force && $row->transcription_cost_usd !== null && (float) $row->transcription_cost_usd > 0) {
            return $row;
        }

        $durationSeconds = max(0, (int) round((float) data_get($fullTranscript, 'audio_duration', 0)));
        $speechModel = (string) (
            data_get($fullTranscript, 'speech_model_used')
            ?: data_get($fullTranscript, 'speech_model')
            ?: data_get($fullTranscript, 'speech_models.0')
        );
        $requestPayload = (array) ($row->request_payload ?? []);
        $estimate = $this->costEstimationService->estimateAssemblyAiTranscription(
            $durationSeconds,
            $speechModel,
            $requestPayload,
        );

        $row->fill([
            'transcription_audio_duration_seconds' => $durationSeconds,
            'transcription_speech_model' => $estimate['speech_model'],
            'transcription_cost_usd' => $estimate['cost_usd'],
            'total_ai_cost_usd' => $this->costEstimationService->recalculateCallTotal([
                'transcription_cost_usd' => $estimate['cost_usd'],
                'summary_cost_usd' => $row->summary_cost_usd,
                'translation_cost_usd' => $row->translation_cost_usd,
            ]),
        ]);
        $row->save();

        if ($recordUsage && ($durationSeconds > 0 || $estimate['cost_usd'] > 0)) {
            $this->domainUsageService->recordTranscriptionUsage(
                $row->domain_uuid,
                $durationSeconds,
                (float) $estimate['cost_usd'],
                ['xml_cdr_uuid' => $row->xml_cdr_uuid]
            );
        }

        return $row->refresh();
    }

    public function applySummaryCompletion(
        CallTranscription $row,
        ?string $model,
        array $usage,
        bool $force = false,
        bool $recordUsage = true,
    ): CallTranscription {
        if (! $force && $row->summary_cost_usd !== null && (float) $row->summary_cost_usd > 0) {
            return $row;
        }

        $estimate = $this->costEstimationService->estimateOpenAiUsage($model, $usage);

        $row->fill([
            'summary_provider' => 'openai',
            'summary_model' => $estimate['model'],
            'summary_input_tokens' => $estimate['input_tokens'],
            'summary_output_tokens' => $estimate['output_tokens'],
            'summary_total_tokens' => $estimate['total_tokens'],
            'summary_cost_usd' => $estimate['cost_usd'],
            'total_ai_cost_usd' => $this->costEstimationService->recalculateCallTotal([
                'transcription_cost_usd' => $row->transcription_cost_usd,
                'summary_cost_usd' => $estimate['cost_usd'],
                'translation_cost_usd' => $row->translation_cost_usd,
            ]),
        ]);
        $row->save();

        if ($recordUsage && $estimate['cost_usd'] > 0) {
            $this->domainUsageService->recordSummaryUsage(
                $row->domain_uuid,
                (float) $estimate['cost_usd'],
                ['xml_cdr_uuid' => $row->xml_cdr_uuid]
            );
        }

        return $row->refresh();
    }

    public function applySummaryCompletionFromStoredData(
        CallTranscription $row,
        bool $force = false,
        bool $recordUsage = true,
    ): CallTranscription {
        $estimate = $this->costEstimationService->estimateOpenAiSummaryFromStoredRow($row);

        return $this->applySummaryCompletion(
            $row,
            $estimate['model'],
            [
                'input_tokens' => $estimate['input_tokens'],
                'output_tokens' => $estimate['output_tokens'],
            ],
            $force,
            $recordUsage,
        );
    }

    public function applyTranslationCompletion(
        CallTranscription $row,
        ?string $model,
        array $usage,
        bool $force = false,
        bool $recordUsage = true,
    ): CallTranscription {
        if (! $force && $row->translation_cost_usd !== null && (float) $row->translation_cost_usd > 0) {
            return $row;
        }

        $estimate = $this->costEstimationService->estimateOpenAiUsage($model, $usage);

        $row->fill([
            'translation_model' => $estimate['model'],
            'translation_input_tokens' => $estimate['input_tokens'],
            'translation_output_tokens' => $estimate['output_tokens'],
            'translation_total_tokens' => $estimate['total_tokens'],
            'translation_cost_usd' => $estimate['cost_usd'],
            'total_ai_cost_usd' => $this->costEstimationService->recalculateCallTotal([
                'transcription_cost_usd' => $row->transcription_cost_usd,
                'summary_cost_usd' => $row->summary_cost_usd,
                'translation_cost_usd' => $estimate['cost_usd'],
            ]),
        ]);
        $row->save();

        if ($recordUsage && $estimate['cost_usd'] > 0) {
            $this->domainUsageService->recordTranslationUsage(
                $row->domain_uuid,
                (float) $estimate['cost_usd'],
                ['xml_cdr_uuid' => $row->xml_cdr_uuid]
            );
        }

        return $row->refresh();
    }

    public function applyTranslationCompletionFromStoredData(
        CallTranscription $row,
        bool $force = false,
        bool $recordUsage = true,
    ): CallTranscription {
        $estimate = $this->costEstimationService->estimateOpenAiTranslationFromStoredRow($row);

        return $this->applyTranslationCompletion(
            $row,
            $estimate['model'],
            [
                'input_tokens' => $estimate['input_tokens'],
                'output_tokens' => $estimate['output_tokens'],
            ],
            $force,
            $recordUsage,
        );
    }
}
