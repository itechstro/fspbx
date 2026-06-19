<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\CallTranscription;
use App\Models\CDR;
use App\Services\CdrDataService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\CallTranscription\CallTranscriptionService;
use App\Services\CallTranscriptionConfigService;
use App\Services\DomainUsageService;
use App\Exceptions\DomainUsageLimitExceededException;
use Exception;

class TranscribeCdrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $xmlCdrUuid;
    public ?string $domainUuid;
    public array $overrides;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */

    public $tries   = 10;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300, 1800, 3600];

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
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;


    public function __construct(string $xmlCdrUuid, ?string $domainUuid = null, array $overrides = [])
    {
        $this->xmlCdrUuid = $xmlCdrUuid;
        $this->domainUuid = $domainUuid;
        $this->overrides  = $overrides;
        $this->onQueue('transcriptions');
    }

    public function handle(CallTranscriptionService $service, DomainUsageService $domainUsageService): void
    {
        Redis::throttle('transcriptions')->allow(1)->every(1)->then(function () use ($service, $domainUsageService) {
            $direction = CDR::query()
                ->where('xml_cdr_uuid', $this->xmlCdrUuid)
                ->value('direction');

            if ($direction === 'recorder' && ! app(CdrDataService::class)->isPrimaryRecorderLeg($this->xmlCdrUuid)) {
                return;
            }

            $providerKey = $service->currentProviderKey($this->domainUuid);

            if (!$providerKey) {
                throw new Exception("No transcription provider defined");
            }

            $transcriptionConfig = app(CallTranscriptionConfigService::class)->effective($this->domainUuid);
            $requestPayload = (array) data_get($transcriptionConfig, 'provider_config.config', []);
            $estimate = $domainUsageService->estimateTranscriptionCostForCdr($this->xmlCdrUuid, $requestPayload);

            try {
                $domainUsageService->assertWithinLimit(
                    'ai_transcription_minutes',
                    (float) ($estimate['duration_seconds'] ?? 0),
                    $this->domainUuid
                );
                $domainUsageService->assertWithinLimit(
                    'ai_spend_usd_monthly',
                    (float) ($estimate['cost_usd'] ?? 0),
                    $this->domainUuid
                );
            } catch (DomainUsageLimitExceededException $exception) {
                CallTranscription::updateOrCreate(
                    ['xml_cdr_uuid' => $this->xmlCdrUuid],
                    [
                        'domain_uuid' => $this->domainUuid,
                        'provider_key' => $providerKey,
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                        'requested_at' => now(),
                        'completed_at' => now(),
                    ]
                );

                return;
            }

            $row = CallTranscription::updateOrCreate(
                ['xml_cdr_uuid' => $this->xmlCdrUuid],
                [
                    'domain_uuid'     => $this->domainUuid,
                    'provider_key'    => $providerKey,
                    'status'          => 'pending',
                    'request_payload' => $this->overrides ?: null,
                    'requested_at'    => now(),
                    'provider_job_id' => null,
                    'started_at'      => null,
                    'completed_at'    => null,
                    'error_message'   => null,
                    'notification_email_sent_at' => null,
                ]
            );

            try {
                $result = $service->transcribeCdr($this->xmlCdrUuid, $this->domainUuid, $this->overrides);
            } catch (\Throwable $exception) {
                $row->update([
                    'status'        => 'failed',
                    'error_message' => $exception->getMessage(),
                    'completed_at'  => now(),
                ]);

                throw $exception;
            }

            $row->update([
                'external_id'      => data_get($result, 'id'),
                'status'           => data_get($result, 'status', 'pending'),
                'request_payload'  => $service->payload ?? null,
                'response_payload' => $result ?: null,
            ]);
    
        }, function () {
            $this->release(30);
        });
    }

    public function failed(?\Throwable $exception): void
    {
        if (! $exception) {
            return;
        }

        CallTranscription::query()
            ->where('xml_cdr_uuid', $this->xmlCdrUuid)
            ->whereNull('external_id')
            ->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at'  => now(),
            ]);
    }
}
