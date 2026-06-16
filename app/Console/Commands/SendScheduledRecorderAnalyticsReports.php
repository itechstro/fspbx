<?php

namespace App\Console\Commands;

use App\Jobs\SendRecorderAnalyticsReport;
use App\Models\RecorderAnalyticsReportSchedule;
use App\Services\CdrDataService;
use App\Services\RecorderAnalyticsService;
use Illuminate\Console\Command;

class SendScheduledRecorderAnalyticsReports extends Command
{
    protected $signature = 'recorder:send-analytics-reports';

    protected $description = 'Send scheduled recorder analytics report emails';

    public function handle(
        RecorderAnalyticsService $analyticsService,
        CdrDataService $cdrDataService
    ): int {
        $schedules = RecorderAnalyticsReportSchedule::query()
            ->where('enabled', true)
            ->get();

        $sent = 0;

        foreach ($schedules as $schedule) {
            if (! $cdrDataService->isRecorderEnabled($schedule->domain_uuid)) {
                continue;
            }

            if (! $analyticsService->isScheduleDue($schedule)) {
                continue;
            }

            $emails = $analyticsService->normalizeEmails($schedule->emails);
            if ($emails === []) {
                continue;
            }

            [$start, $end] = $analyticsService->scheduledPeriod($schedule->frequency, $schedule->domain_uuid);

            SendRecorderAnalyticsReport::dispatch(
                $schedule->domain_uuid,
                $start->toIso8601String(),
                $end->toIso8601String(),
                $emails,
                (bool) $schedule->include_executive_summary,
                $schedule->search,
            )->onQueue('emails');

            $schedule->update(['last_sent_at' => now()]);
            $sent++;
        }

        $this->info("Queued {$sent} recorder analytics report(s).");

        return self::SUCCESS;
    }
}
