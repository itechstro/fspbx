<?php

namespace App\Services;

use App\Models\RecorderAnalyticsReportSchedule;
use App\Services\Contacts\ContactCallerIdResolver;
use Illuminate\Support\Carbon;

class RecorderAnalyticsService
{
    public function __construct(
        protected CdrDataService $cdrDataService,
        protected ContactCallerIdResolver $contactCallerIdResolver,
        protected OpenAIService $openAIService,
    ) {
    }

    public function isExecutiveSummaryAvailable(): bool
    {
        return $this->openAIService->isConfigured();
    }

    public function buildReport(string $domainUuid, Carbon $startUtc, Carbon $endUtc): array
    {
        $timezone = get_local_time_zone($domainUuid);
        $startEpoch = $startUtc->copy()->setTimezone('UTC')->timestamp;
        $endEpoch = $endUtc->copy()->setTimezone('UTC')->timestamp;

        $calls = $this->cdrDataService
            ->recorderAnalyticsQuery($domainUuid, $startEpoch, $endEpoch)
            ->with([
                'callTranscription:uuid,xml_cdr_uuid,status,summary_status,summary_payload',
            ])
            ->orderByDesc('start_epoch')
            ->get([
                'xml_cdr_uuid',
                'domain_uuid',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
                'duration',
                'status',
            ]);

        $this->contactCallerIdResolver->enrichCollection($calls);

        $sentiment = [
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'unknown' => 0,
        ];

        $totalDuration = 0;
        $transcribedCount = 0;
        $summarizedCount = 0;
        $callRows = [];
        $callsByDay = [];
        $statusBreakdown = [];
        $topicCounts = [];

        foreach ($calls as $call) {
            $totalDuration += (int) $call->duration;
            $transcription = $call->callTranscription;
            $hasTranscription = ($transcription?->status ?? null) === 'completed';
            $hasSummary = ($transcription?->summary_status ?? null) === 'completed';

            if ($hasTranscription) {
                $transcribedCount++;
            }

            if ($hasSummary) {
                $summarizedCount++;
            }

            $rawSentiment = strtolower(trim((string) data_get($transcription?->summary_payload, 'sentiment_overall', '')));
            if (in_array($rawSentiment, ['positive', 'neutral', 'negative'], true)) {
                $sentiment[$rawSentiment]++;
            } else {
                $sentiment['unknown']++;
            }

            $startLocal = Carbon::createFromTimestamp((int) $call->start_epoch, 'UTC')->timezone($timezone);
            $dateKey = $startLocal->format('Y-m-d');
            $callsByDay[$dateKey] = ($callsByDay[$dateKey] ?? 0) + 1;

            $statusKey = trim((string) ($call->status ?? '')) ?: 'unknown';
            $statusBreakdown[$statusKey] = ($statusBreakdown[$statusKey] ?? 0) + 1;

            foreach ((array) data_get($transcription?->summary_payload, 'key_points', []) as $topic) {
                $topic = trim((string) $topic);
                if ($topic === '') {
                    continue;
                }

                $topicKey = mb_strtolower($topic);
                if (! isset($topicCounts[$topicKey])) {
                    $topicCounts[$topicKey] = ['label' => $topic, 'count' => 0];
                }
                $topicCounts[$topicKey]['count']++;
            }

            $callRows[] = [
                'xml_cdr_uuid' => $call->xml_cdr_uuid,
                'date' => $startLocal->format('Y-m-d'),
                'time' => $startLocal->format('H:i:s'),
                'caller' => $call->caller_id_name_formatted ?: $call->caller_id_number,
                'dialed' => $call->caller_destination_name_formatted ?: $call->caller_destination,
                'duration' => $this->cdrDataService->getFormattedDuration((int) $call->duration),
                'status' => $call->status,
                'sentiment' => in_array($rawSentiment, ['positive', 'neutral', 'negative'], true)
                    ? ucfirst($rawSentiment)
                    : null,
                'summary' => trim((string) data_get($transcription?->summary_payload, 'summary', '')) ?: null,
            ];
        }

        $totalCalls = $calls->count();
        $periodLabel = sprintf(
            '%s – %s',
            $startUtc->copy()->timezone($timezone)->format('M j, Y g:i A'),
            $endUtc->copy()->timezone($timezone)->format('M j, Y g:i A')
        );

        ksort($callsByDay);

        $callsByDayRows = [];
        foreach ($callsByDay as $date => $count) {
            $callsByDayRows[] = ['date' => $date, 'count' => $count];
        }

        $statusRows = [];
        foreach ($statusBreakdown as $status => $count) {
            $statusRows[] = ['status' => $status, 'count' => $count];
        }
        usort($statusRows, fn ($a, $b) => $b['count'] <=> $a['count']);

        $topTopics = array_values($topicCounts);
        usort($topTopics, fn ($a, $b) => $b['count'] <=> $a['count']);
        $topTopics = array_slice($topTopics, 0, 10);

        return [
            'domain_uuid' => $domainUuid,
            'timezone' => $timezone,
            'period_label' => $periodLabel,
            'generated_at' => now($timezone)->format('M j, Y g:i A T'),
            'summary' => [
                'total_calls' => $totalCalls,
                'total_duration' => $this->cdrDataService->getFormattedDuration($totalDuration),
                'average_duration' => $totalCalls > 0
                    ? $this->cdrDataService->getFormattedDuration((int) round($totalDuration / $totalCalls))
                    : '00:00:00',
                'transcribed_count' => $transcribedCount,
                'summarized_count' => $summarizedCount,
                'sentiment' => $sentiment,
            ],
            'calls_by_day' => $callsByDayRows,
            'status_breakdown' => $statusRows,
            'top_topics' => $topTopics,
            'calls' => $callRows,
        ];
    }

    public function buildCsvContent(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date', 'Time', 'Caller', 'Dialed', 'Duration', 'Status', 'Sentiment', 'Summary']);

        foreach ($report['calls'] ?? [] as $call) {
            fputcsv($handle, [
                $call['date'] ?? '',
                $call['time'] ?? '',
                $call['caller'] ?? '',
                $call['dialed'] ?? '',
                $call['duration'] ?? '',
                $call['status'] ?? '',
                $call['sentiment'] ?? '',
                $call['summary'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public function csvFilename(array $report): string
    {
        $start = collect($report['calls_by_day'] ?? [])->pluck('date')->first() ?? now()->format('Y-m-d');
        $end = collect($report['calls_by_day'] ?? [])->pluck('date')->last() ?? $start;

        return sprintf('recorder-analytics-%s-to-%s.csv', $start, $end);
    }

    public function buildExecutiveSummaryContext(array $report): array
    {
        $callSummaries = collect($report['calls'] ?? [])
            ->filter(fn (array $call) => trim((string) ($call['summary'] ?? '')) !== '')
            ->take(40)
            ->map(fn (array $call) => [
                'date' => $call['date'] ?? null,
                'caller' => $call['caller'] ?? null,
                'dialed' => $call['dialed'] ?? null,
                'sentiment' => $call['sentiment'] ?? null,
                'summary' => $call['summary'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'period_label' => $report['period_label'] ?? null,
            'generated_at' => $report['generated_at'] ?? null,
            'summary' => $report['summary'] ?? [],
            'calls_by_day' => $report['calls_by_day'] ?? [],
            'status_breakdown' => $report['status_breakdown'] ?? [],
            'top_topics' => $report['top_topics'] ?? [],
            'call_summaries' => $callSummaries,
        ];
    }

    public function generateExecutiveSummary(array $report): array
    {
        if (! $this->isExecutiveSummaryAvailable()) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        $summarizedCount = (int) data_get($report, 'summary.summarized_count', 0);
        if ($summarizedCount < 1) {
            throw new \RuntimeException('No summarized calls are available for this period.');
        }

        $decoded = $this->openAIService->createExecutiveSummary(
            $this->buildExecutiveSummaryContext($report)
        );

        return [
            'overview' => trim((string) ($decoded['overview'] ?? '')) ?: null,
            'highlights' => $this->normalizeSummaryList($decoded['highlights'] ?? []),
            'concerns' => $this->normalizeSummaryList($decoded['concerns'] ?? []),
            'recommendations' => $this->normalizeSummaryList($decoded['recommendations'] ?? []),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function maybeAttachExecutiveSummary(array $report, bool $includeExecutiveSummary): array
    {
        if (! $includeExecutiveSummary) {
            return $report;
        }

        try {
            $report['executive_summary'] = $this->generateExecutiveSummary($report);
        } catch (\Throwable $exception) {
            report($exception);
            $report['executive_summary'] = null;
            $report['executive_summary_error'] = $exception->getMessage();
        }

        return $report;
    }

    protected function normalizeSummaryList(mixed $items): array
    {
        return collect((array) $items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    public function scheduledPeriod(string $frequency, string $domainUuid): array
    {
        $timezone = get_local_time_zone($domainUuid);
        $now = now($timezone);

        return match ($frequency) {
            'daily' => [
                $now->copy()->subDay()->startOfDay()->utc(),
                $now->copy()->subDay()->endOfDay()->utc(),
            ],
            'monthly' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay()->utc(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay()->utc(),
            ],
            default => [
                $now->copy()->subDays(7)->startOfDay()->utc(),
                $now->copy()->subDay()->endOfDay()->utc(),
            ],
        };
    }

    public function isScheduleDue(RecorderAnalyticsReportSchedule $schedule): bool
    {
        if (! $schedule->enabled || empty($schedule->emails)) {
            return false;
        }

        $timezone = get_local_time_zone($schedule->domain_uuid);
        $now = now($timezone);
        $sendAt = Carbon::parse($schedule->send_time, $timezone)->setDate(
            $now->year,
            $now->month,
            $now->day
        );

        if ($now->lt($sendAt)) {
            return false;
        }

        if ($now->diffInMinutes($sendAt) > 59) {
            return false;
        }

        $lastSent = $schedule->last_sent_at?->timezone($timezone);

        return match ($schedule->frequency) {
            'daily' => ! $lastSent || ! $lastSent->isSameDay($now),
            'weekly' => $this->isWeeklyScheduleDue($schedule, $now, $lastSent),
            'monthly' => $this->isMonthlyScheduleDue($schedule, $now, $lastSent),
            default => false,
        };
    }

    public function normalizeEmails(array|string|null $emails): array
    {
        if (is_string($emails)) {
            $emails = preg_split('/[\s,;]+/', $emails) ?: [];
        }

        $normalized = [];
        foreach ((array) $emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $normalized[$email] = $email;
            }
        }

        return array_values($normalized);
    }

    protected function isWeeklyScheduleDue($schedule, Carbon $now, ?Carbon $lastSent): bool
    {
        $dayOfWeek = $schedule->day_of_week ?? 1;

        if ((int) $now->dayOfWeek !== (int) $dayOfWeek) {
            return false;
        }

        return ! $lastSent || $lastSent->lt($now->copy()->startOfDay());
    }

    protected function isMonthlyScheduleDue($schedule, Carbon $now, ?Carbon $lastSent): bool
    {
        $dayOfMonth = max(1, min(28, (int) ($schedule->day_of_month ?? 1)));

        if ((int) $now->day !== $dayOfMonth) {
            return false;
        }

        return ! $lastSent || ! $lastSent->isSameMonth($now);
    }
}
