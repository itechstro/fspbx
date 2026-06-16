<?php

namespace App\Console\Commands;

use App\Jobs\SendCallHistoryAnalyticsReport;
use App\Models\CallHistoryAnalyticsReportSchedule;
use App\Services\CallHistoryAnalyticsService;
use Illuminate\Console\Command;

class SendScheduledCallHistoryAnalyticsReports extends Command
{
    protected $signature = 'cdr:send-analytics-reports';

    protected $description = 'Send scheduled call history analytics report emails';

    public function handle(CallHistoryAnalyticsService $analyticsService): int
    {
        $schedules = CallHistoryAnalyticsReportSchedule::query()
            ->where('enabled', true)
            ->get();

        $sent = 0;

        foreach ($schedules as $schedule) {
            if (! $analyticsService->isScheduleDue($schedule)) {
                continue;
            }

            $emails = $analyticsService->normalizeEmails($schedule->emails);
            if ($emails === []) {
                continue;
            }

            [$start, $end] = $analyticsService->scheduledPeriod($schedule->frequency, $schedule->domain_uuid);

            SendCallHistoryAnalyticsReport::dispatch(
                $schedule->domain_uuid,
                $start->toIso8601String(),
                $end->toIso8601String(),
                $emails,
                (bool) $schedule->include_executive_summary,
                (array) ($schedule->filters ?? []),
            )->onQueue('emails');

            $schedule->update(['last_sent_at' => now()]);
            $sent++;
        }

        $this->info("Queued {$sent} call history analytics report(s).");

        return self::SUCCESS;
    }
}
