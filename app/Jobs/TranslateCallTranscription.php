<?php

namespace App\Jobs;

use App\Models\CallTranscription;
use App\Models\CallTranscriptionPolicy;
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

    public function handle(): void
    {
        Redis::throttle('translations')->allow(2)->every(1)->then(function () {
            $row = CallTranscription::find($this->uuid);
            if (!$row) {
                return;
            }

            $transcriptText = trim((string) data_get($row->result_payload, 'text', ''));
            if ($transcriptText === '') {
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
                $transcriptText,
                $summaryText !== '' ? $summaryText : null,
                (string) $targetLanguage,
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

