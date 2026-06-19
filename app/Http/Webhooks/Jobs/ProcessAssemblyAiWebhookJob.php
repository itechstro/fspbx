<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\CDR;
use App\Models\CallTranscription;
use App\Services\CallTranscription\CallTranscriptionService;
use App\Services\CallTranscription\AssemblyAiUtteranceNormalizer;
use App\Services\CallTranscriptionCostService;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessAssemblyAiWebhookJob extends SpatieProcessWebhookJob
{

    public $tries   = 10;
    public $backoff = [10, 30, 60, 120, 300];
    public $timeout = 300;
    public $maxExceptions = 5;

    public function __construct(public WebhookCall $webhookCall) {}

    public function handle(CallTranscriptionService $service, CallTranscriptionCostService $costService): void
    {

        $payload = $this->webhookCall->payload;

        $transcriptId = data_get($payload, 'transcript_id');
        $status       = data_get($payload, 'status'); // completed|error

        if (!$transcriptId) {
            // nothing to do
            return;
        }

        $row = CallTranscription::query()->where('external_id', $transcriptId)->first();
        if (!$row) {
            return;
        }

        if ($status === 'completed') {
            // fetch final transcript JSON from provider
            $provider = $service->providerForScope($row->domain_uuid);
            $full     = $provider->fetchTranscript($transcriptId);
            $full['utterances'] = app(AssemblyAiUtteranceNormalizer::class)->normalize($full);

            // Remove heavy fields like "words" everywhere in the structure
            $sanitized = $full ? $this->deepUnsetKeys($full, ['words']) : null;

            $direction = CDR::query()
                ->where('xml_cdr_uuid', $row->xml_cdr_uuid)
                ->value('direction');

            $shouldSummarize = $service->shouldAutoSummarize($row->domain_uuid, $direction);

            $updates = [
                'status'          => 'completed',
                'result_payload'  => $sanitized ?: null,
                'completed_at'    => now(),
                'error_message'   => null,
            ];

            if ($shouldSummarize) {
                $updates['summary_status'] = 'pending';
                $updates['summary_error'] = null;
                $updates['summary_requested_at'] = now();
            }

            $row->update($updates);
            $costService->applyTranscriptionCompletion($row->refresh(), $full ?: []);

            if ($shouldSummarize) {
                dispatch(new \App\Jobs\SummarizeCallTranscription($row->uuid))->onQueue('transcriptions');
            } elseif ($service->shouldAutoTranslate($row->domain_uuid, $direction)) {
                dispatch(new \App\Jobs\TranslateCallTranscription($row->uuid))->onQueue('transcriptions');
            } else {
                $service->maybeDispatchTranscriptionEmail($row);
            }

        } elseif ($status === 'error') {
            // Pull the current transcript for error details if useful
            $provider = $service->providerForScope($row->domain_uuid);
            $current  = $provider->fetchTranscript($transcriptId);

            $row->update([
                'status'         => 'failed',
                'error_message'  => $current['error'] ?? 'Transcription failed.',
                'result_payload' => $current ?: null,
                'completed_at'   => now(),
            ]);
        }

        // You can emit events/notifications here if you’d like.
    }

    /**
     * Recursively remove keys from arrays/objects (e.g., "words", "tokens", etc.).
     */
    function deepUnsetKeys(mixed $data, array $keysToRemove = ['words']): mixed
    {
        // Normalize to array for processing; preserve objects on return
        $isObject = is_object($data);
        $arr = json_decode(json_encode($data, JSON_UNESCAPED_UNICODE), true);

        $walker = function (&$value) use (&$walker, $keysToRemove) {
            if (is_array($value)) {
                // remove target keys at this level
                foreach ($keysToRemove as $k) {
                    if (array_key_exists($k, $value)) {
                        unset($value[$k]);
                    }
                }
                // descend
                foreach ($value as &$v) {
                    $walker($v);
                }
            }
        };
        $walker($arr);

        // Return same type we received (array or object)
        return $isObject ? json_decode(json_encode($arr)) : $arr;
    }
}
