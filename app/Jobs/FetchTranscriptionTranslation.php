<?php

namespace App\Jobs;

use App\Models\CallTranscription;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FetchTranscriptionTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 60;
    public $backoff = 60;
    public $timeout = 60;
    public $maxExceptions = 3;

    public function __construct(
        public string $uuid,
        public string $responseId
    ) {}

    public function handle(): void
    {
        Redis::throttle('translations')->allow(2)->every(1)->then(function () {
            $row = CallTranscription::find($this->uuid);
            if (!$row) {
                return;
            }

            if (!$row->translation_external_id || $row->translation_external_id !== $this->responseId) {
                return;
            }

            $openAiService = app(OpenAIService::class);
            $retrieved = $openAiService->retrieveResponseById($this->responseId);
            $status = $retrieved['status'] ?? 'unknown';
            $raw = $retrieved['raw'] ?? [];
            $outputText = trim((string) ($retrieved['text'] ?? ''));

            if (in_array($status, ['queued', 'in_progress'])) {
                $this->release(60);
                return;
            }

            if ($status === 'failed') {
                $row->update([
                    'translation_status' => 'failed',
                    'translation_error' => (string) data_get($raw, 'error.message') ?: 'OpenAI reported failure.',
                ]);
                return;
            }

            if ($status === 'completed') {
                if ($outputText === '') {
                    $row->update([
                        'translation_status' => 'failed',
                        'translation_error' => 'OpenAI returned empty translation output.',
                        'translation_payload' => ['raw_response' => $raw],
                    ]);
                    return;
                }

                $decoded = json_decode($outputText, true);
                $translatedTranscript = is_array($decoded)
                    ? trim((string) data_get($decoded, 'transcript_text', ''))
                    : '';
                $translatedSummary = is_array($decoded)
                    ? trim((string) data_get($decoded, 'summary_text', ''))
                    : '';

                if ($translatedTranscript === '') {
                    // Backward-compatible fallback if model returned plain text instead of JSON.
                    $translatedTranscript = $outputText;
                }

                $row->update([
                    'translation_status' => 'completed',
                    'translation_error' => null,
                    'translation_completed_at' => now(),
                    'translation_payload' => [
                        'text' => $translatedTranscript,
                        'summary_text' => $translatedSummary !== '' ? $translatedSummary : null,
                        'target_language' => $row->translation_target_language,
                    ],
                ]);

                $transcriptionService = app(\App\Services\CallTranscription\CallTranscriptionService::class);
                $cfg = $transcriptionService->emailDeliveryConfig($row->domain_uuid ?? null);
                if (($cfg['translation_enabled'] ?? false) && !empty($cfg['email'])) {
                    SendTranscriptionEmail::dispatch($row->uuid, $cfg['email']);
                }
                return;
            }

            $this->release(60);
        }, function () {
            return $this->release(60);
        });
    }

    public function failed(\Throwable $e): void
    {
        try {
            $row = CallTranscription::find($this->uuid);
            if (!$row) {
                report($e);
                return;
            }

            $message = trim($e->getMessage() ?? '');
            if ($message === '') {
                $message = class_basename($e) . ' thrown with empty message';
            }

            $short = sprintf(
                '[%s] %s at %s:%d',
                $e->getCode() ?: 0,
                $message,
                basename($e->getFile()),
                $e->getLine()
            );

            if ($row->translation_status !== 'completed') {
                $row->update([
                    'translation_status' => 'failed',
                    'translation_error' => Str::limit($short, 1000),
                ]);
            }
        } catch (\Throwable $inner) {
            report($inner);
            report($e);
        }
    }
}

