<?php

namespace App\Services\CallTranscription;

use RuntimeException;
use App\Jobs\SendTranscriptionEmail;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Cache;
use App\Services\CallRecordingUrlService;
use App\Services\CallTranscriptionConfigService;
use App\Services\CallTranscription\TranscriptionProviderRegistry;

class CallTranscriptionService
{
    public array $payload;

    public function __construct(
        private TranscriptionProviderRegistry $registry,
        private CallRecordingUrlService $recordingUrlService,
        private CallTranscriptionConfigService $configService,
    ) {}

    /**
     * Start a transcription for a given CDR UUID within optional domain scope.
     */
    public function transcribeCdr(string $xmlCdrUuid, ?string $domainUuid = null, array $overrides = []): array
    {
        try {

            $config = $this->transcriptionConfigCached($domainUuid);

            if (empty($config['enabled'])) {
                return [];
            }

            $providerKey  = $config['provider_key']    ?? null;
            $providerCfg  = (array)($config['provider_config'] ?? []);
            if (!$providerKey) {
                return [];
            }

            $provider = $this->registry->make($providerKey, $providerCfg);

            // Resolve recording URL (signed, expiring)
            $urls = $this->recordingUrlService->urlsForCdr($xmlCdrUuid, 600);
            $audioUrl = $urls['audio_url'] ?? null;
            if (!$audioUrl) {
                throw new RuntimeException("Recording URL not available for CDR {$xmlCdrUuid}");
            }

            $response =  $provider->transcribe($audioUrl, $overrides);
            $this->payload = $provider->payload;

            return $response;
        } catch (\Exception $e) {
            logger($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    /** Build provider instance for read paths */
    public function providerForScope(?string $domainUuid)
    {
        $config = $this->transcriptionConfigCached($domainUuid);
        $providerKey = $config['provider_key'] ?? null;
        $providerCfg = (array)($config['provider_config'] ?? []);
        if (!$providerKey) {
            throw new RuntimeException('No transcription provider selected.');
        }
        return $this->registry->make($providerKey, $providerCfg);
    }

    /** Cached policy+config  */
    private function transcriptionConfigCached(?string $domainUuid): array
    {
        $cacheKey = 'call-transcription:config:' . ($domainUuid ?: 'system');

        return Cache::tags('ct-config')
            ->remember($cacheKey, now()->addHours(24), function () use ($domainUuid) {
                // Service should return:
                // ['enabled'=>bool,'provider_key'=>'assemblyai','provider_uuid'=>'...','provider_config'=>array]
                return $this->configService->effective($domainUuid);
            });
    }

    public function getCachedConfig(?string $domainUuid = null): array
    {
        return $this->transcriptionConfigCached($domainUuid);
    }

    public function currentProviderKey(?string $domainUuid): ?string
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);
        return $cfg['provider_key'] ?? null;
    }

    public function shouldAutoTranscribe(?string $domainUuid, ?string $direction = null): bool
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);

        if ($direction === 'recorder') {
            return (bool) ($cfg['auto_transcribe_recorder'] ?? false);
        }

        return (bool) ($cfg['auto_transcribe'] ?? false);
    }

    public function shouldAutoSummarize(?string $domainUuid, ?string $direction = null): bool
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);

        if ($direction === 'recorder') {
            return (bool) ($cfg['auto_summarize_recorder'] ?? false);
        }

        return (bool) ($cfg['auto_summarize'] ?? false);
    }

    public function emailDeliveryConfig(?string $domainUuid, ?string $direction = null): array
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);
        $isRecorder = $direction === 'recorder';

        if ($isRecorder) {
            $enabled = (bool) ($cfg['email_transcription_recorder'] ?? false);
            $email = isset($cfg['email_recorder']) ? trim((string) $cfg['email_recorder']) : '';
            if ($email === '' && isset($cfg['email'])) {
                $email = trim((string) $cfg['email']);
            }
        } else {
            $enabled = (bool) ($cfg['email_transcription'] ?? false);
            $email = isset($cfg['email']) ? trim((string) $cfg['email']) : '';
        }

        $translationEnabled = $isRecorder
            ? (bool) ($cfg['email_translation_recorder'] ?? false)
            : (bool) ($cfg['email_translation'] ?? false);

        return [
            'enabled' => $enabled,
            'translation_enabled' => $translationEnabled,
            'email'   => ($email !== '') ? $email : null,
        ];
    }

    public function cdrDirectionForTranscription(?string $xmlCdrUuid): ?string
    {
        if (!$xmlCdrUuid) {
            return null;
        }

        return \App\Models\CDR::query()
            ->where('xml_cdr_uuid', $xmlCdrUuid)
            ->value('direction');
    }

    /**
     * Send the transcription notification email once optional auto steps have finished or been skipped.
     * Includes whatever transcript, summary, and translation content is available at send time.
     */
    public function maybeDispatchTranscriptionEmail(CallTranscription $row): void
    {
        $row = $row->fresh();
        if (!$row || $row->notification_email_sent_at) {
            return;
        }

        $direction = $this->cdrDirectionForTranscription($row->xml_cdr_uuid ?? null);
        $cfg = $this->emailDeliveryConfig($row->domain_uuid ?? null, $direction);

        if (!$this->isTranscriptionEmailReady($row, $direction, $cfg)) {
            return;
        }

        $email = $cfg['email'] ?? null;
        if (!$email) {
            return;
        }

        SendTranscriptionEmail::dispatch($row->uuid, $email);
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    public function isTranscriptionEmailReady(CallTranscription $row, ?string $direction = null, ?array $cfg = null): bool
    {
        if ($row->status !== 'completed') {
            return false;
        }

        $direction ??= $this->cdrDirectionForTranscription($row->xml_cdr_uuid ?? null);
        $cfg ??= $this->emailDeliveryConfig($row->domain_uuid ?? null, $direction);

        $wantsTranscriptEmail = (bool) ($cfg['enabled'] ?? false);
        $legacyTranslationEmail = (bool) ($cfg['translation_enabled'] ?? false);

        if (!$wantsTranscriptEmail && !$legacyTranslationEmail) {
            return false;
        }

        $autoSummarize = $this->shouldAutoSummarize($row->domain_uuid, $direction);
        $autoTranslate = $this->shouldAutoTranslate($row->domain_uuid, $direction);

        if ($autoSummarize && ! $this->isOptionalStepTerminal($row->summary_status)) {
            return false;
        }

        $shouldWaitForTranslation = $autoTranslate && ($wantsTranscriptEmail || $legacyTranslationEmail);
        if ($shouldWaitForTranslation && ! $this->isOptionalStepTerminal($row->translation_status)) {
            return false;
        }

        return $this->hasEmailContent($row);
    }

    public function hasEmailContent(CallTranscription $row): bool
    {
        $utterances = (array) data_get($row->result_payload, 'utterances', []);
        $summary = trim((string) data_get($row->summary_payload, 'summary', ''));
        $hasTranslation = (bool) (
            data_get($row->translation_payload, 'text')
            || data_get($row->translation_payload, 'utterances')
        );

        return $utterances !== [] || $summary !== '' || $hasTranslation;
    }

    private function isOptionalStepTerminal(?string $status): bool
    {
        return in_array($status, ['completed', 'failed'], true);
    }

    public function shouldAutoTranslate(?string $domainUuid, ?string $direction = null): bool
    {
        $cfg = $this->transcriptionConfigCached($domainUuid);

        if ($direction === 'recorder') {
            return (bool) ($cfg['auto_translate_recorder'] ?? false);
        }

        return (bool) ($cfg['auto_translate'] ?? false);
    }

}
