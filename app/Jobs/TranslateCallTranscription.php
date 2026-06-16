<?php

namespace App\Jobs;

use App\Models\CallTranscription;
use App\Models\CallTranscriptionPolicy;
use App\Exceptions\DomainUsageLimitExceededException;
use App\Services\DomainUsageService;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TranslateCallTranscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;
    public $backoff = [10, 30, 60, 120];
    public $timeout = 300;
    public $maxExceptions = 3;

    public function __construct(public string $uuid) {}

    public function handle(DomainUsageService $domainUsageService): void
    {
        Redis::throttle('translations')->allow(2)->every(1)->then(function () use ($domainUsageService) {
            $row = CallTranscription::find($this->uuid);
            if (!$row) {
                return;
            }

            try {
                $domainUsageService->assertWithinLimit('ai_translation_requests', 1, $row->domain_uuid);
                $domainUsageService->assertWithinLimit(
                    'ai_spend_usd_monthly',
                    (float) data_get(ai_usage_rates(), 'openai.reserve_translation_usd', 0.02),
                    $row->domain_uuid
                );
            } catch (DomainUsageLimitExceededException $exception) {
                $row->update([
                    'translation_status' => 'failed',
                    'translation_error' => $exception->getMessage(),
                ]);
                return;
            }

            $utterances = (array) data_get($row->result_payload, 'utterances', []);
            $transcriptText = trim((string) data_get($row->result_payload, 'text', ''));
            if ($transcriptText === '' && $utterances === []) {
                $row->update([
                    'translation_status' => 'failed',
                    'translation_error' => 'No transcript text available for translation.',
                ]);
                return;
            }

            $targetLanguage = 'en-us';
            if ($row->domain_uuid) {
                $domainPolicy = CallTranscriptionPolicy::query()
                    ->where('domain_uuid', $row->domain_uuid)
                    ->first();
                $systemPolicy = CallTranscriptionPolicy::query()
                    ->whereNull('domain_uuid')
                    ->first();

                $targetLanguage = $domainPolicy?->translation_language
                    ?? $systemPolicy?->translation_language
                    ?? get_domain_setting('transcription_translation_language', $row->domain_uuid)
                    ?? get_domain_setting('language', $row->domain_uuid)
                    ?? 'en-us';
            }

            $summaryText = trim((string) data_get($row->summary_payload, 'summary', ''));

            $openAiService = app(OpenAIService::class);
            $start = $openAiService->createBackgroundTranslation(
                $utterances,
                $summaryText !== '' ? $summaryText : null,
                (string) $targetLanguage,
                $transcriptText !== '' ? $transcriptText : null,
                'gpt-4.1-mini'
            );

            $responseId = $start['id'] ?? null;
            $status = $start['status'] ?? 'queued';

            if (!$responseId) {
                $row->update([
                    'translation_status' => 'failed',
                    'translation_error' => 'OpenAI did not return response id.',
                ]);
                return;
            }

            $row->update([
                'translation_model' => 'gpt-4.1-mini',
                'translation_external_id' => $responseId,
                'translation_status' => in_array($status, ['queued', 'in_progress']) ? $status : 'queued',
                'translation_error' => null,
                'translation_requested_at' => now(),
                'translation_completed_at' => null,
                'translation_payload' => null,
                'translation_target_language' => (string) $targetLanguage,
            ]);

            FetchTranscriptionTranslation::dispatch($row->uuid, $responseId)
                ->delay(now()->addMinutes(1))
                ->onQueue('transcriptions');
        }, function () {
            return $this->release(30);
        });
    }
}

