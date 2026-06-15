<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use App\Mail\CallTranscriptionReady;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class SendTranscriptionEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300, 1800, 3600];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        public string $transcriptionUuid,
        public string $email
    ) {}

    public function handle()
    {
        Redis::throttle('transcriptions')->allow(1)->every(1)->then(function () {

            $transcription = CallTranscription::find($this->transcriptionUuid);
            if (!$transcription) {
                return;
            }

            if ($transcription->notification_email_sent_at) {
                return;
            }

            $transcriptionService = app(\App\Services\CallTranscription\CallTranscriptionService::class);
            if (! $transcriptionService->isTranscriptionEmailReady($transcription)) {
                if ($this->attempts() < $this->tries) {
                    $this->release(90);
                }

                return;
            }

            $claimed = CallTranscription::query()
                ->where('uuid', $transcription->uuid)
                ->whereNull('notification_email_sent_at')
                ->update(['notification_email_sent_at' => now()]);

            if (! $claimed) {
                return;
            }

            $transcription = $transcription->fresh();
            if (! $transcription || ! $transcriptionService->hasEmailContent($transcription)) {
                CallTranscription::query()
                    ->where('uuid', $this->transcriptionUuid)
                    ->update(['notification_email_sent_at' => null]);

                if ($this->attempts() < $this->tries) {
                    $this->release(90);
                }

                return;
            }

            // 1. Decode Payloads
            $summaryPayload = $transcription->summary_payload ?? [];
            $resultPayload  = $transcription->result_payload ?? [];

            // 2. Create Speaker Map (The most important logic)
            // Maps "A" -> "Vanessa (Agent)" and "B" -> "Customer"
            $speakerMap = [];
            $agentLabel = null; // Track who the agent is to highlight them in CSS

            if (isset($summaryPayload['participants'])) {
                foreach ($summaryPayload['participants'] as $p) {
                    $label = $p['label']; // e.g., "A"

                    // Determine display name: Name Guess > Role Guess > Label
                    $name = $p['name_guess'] ?? ucfirst($p['role_guess'] ?? "Speaker $label");
                    $speakerMap[$label] = $name;

                    // Identify if this is the agent (for styling purposes)
                    if (($p['role_guess'] ?? '') === 'agent') {
                        $agentLabel = $label;
                    }
                }
            }

            // 3. Prepare Display Data
            $summaryText = trim((string) ($summaryPayload['summary'] ?? ''));
            $hasSummary = $summaryText !== '' && $summaryText !== 'No summary available.';
            $hasTranslation = (bool) (
                data_get($transcription->translation_payload, 'text')
                || data_get($transcription->translation_payload, 'utterances')
            );

            $data = [
                'id'             => $transcription->uuid,
                'date'           => $transcription->created_at->format('F j, Y @ g:i A'),
                'duration'       => gmdate("i:s", $resultPayload['audio_duration'] ?? 0),
                'has_summary'    => $hasSummary,
                'sentiment'      => $hasSummary ? ucfirst($summaryPayload['sentiment_overall'] ?? 'Neutral') : null,
                'summary'        => $hasSummary ? $summaryText : null,
                'action_items'   => $hasSummary ? ($summaryPayload['action_items'] ?? []) : [],
                'utterances'     => $resultPayload['utterances'] ?? [],
                'speaker_map'    => $speakerMap,
                'agent_label'    => $agentLabel,
                'translation_text' => data_get($transcription->translation_payload, 'text'),
                'translation_utterances' => data_get($transcription->translation_payload, 'utterances', []),
                'translation_summary' => data_get($transcription->translation_payload, 'summary_text'),
                'translation_target_language' => $transcription->translation_target_language,
                'email_subject'  => match (true) {
                    $hasTranslation => 'New transcription and translation',
                    $hasSummary => 'New call transcription summary',
                    default => 'New call transcription',
                },
            ];

            // 4. Send Email
            // Replace with your actual admin notification email
            Mail::to($this->email)->send(new CallTranscriptionReady($data));
        }, function () {
            $this->release(30);
        });
    }
}
