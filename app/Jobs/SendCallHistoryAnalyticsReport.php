<?php

namespace App\Jobs;

use App\Mail\CallHistoryAnalyticsReportMail;
use App\Models\Domain;
use App\Services\CallHistoryAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class SendCallHistoryAnalyticsReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $timeout = 300;

    public function __construct(
        public string $domainUuid,
        public string $startPeriod,
        public string $endPeriod,
        public array $emails,
        public bool $includeExecutiveSummary = false,
        public array $filters = [],
    ) {
    }

    public function middleware(): array
    {
        return [(new RateLimitedWithRedis('email'))];
    }

    public function handle(CallHistoryAnalyticsService $analyticsService): void
    {
        Redis::throttle('emails')->allow(2)->every(1)->then(function () use ($analyticsService) {
            $emails = $analyticsService->normalizeEmails($this->emails);
            if ($emails === []) {
                return;
            }

            $domain = Domain::query()->find($this->domainUuid);
            if (! $domain) {
                return;
            }

            $report = $analyticsService->buildReport(
                $this->domainUuid,
                Carbon::parse($this->startPeriod),
                Carbon::parse($this->endPeriod),
                $this->filters,
            );

            $report = $analyticsService->maybeAttachExecutiveSummary(
                $report,
                $this->includeExecutiveSummary
            );

            $payload = array_merge($report, [
                'domain_name' => $domain->domain_description ?: $domain->domain_name,
                'email_subject' => sprintf(
                    '%s Call history analytics: %s',
                    config('app.name', 'FS PBX'),
                    $report['period_label']
                ),
                'call_history_url' => url('/call-detail-records'),
            ]);

            Mail::to($emails)->send(new CallHistoryAnalyticsReportMail(
                $payload,
                $analyticsService->buildCsvContent($report),
                $analyticsService->csvFilename($report),
            ));
        }, function () {
            return $this->release(30);
        });
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
