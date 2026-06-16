<?php

namespace App\Services;

use App\Models\CallHistoryAnalyticsReportSchedule;
use App\Services\Concerns\BuildsAnalyticsAiBreakdowns;
use App\Services\Contacts\ContactCallerIdResolver;
use Illuminate\Support\Carbon;

class CallHistoryAnalyticsService
{
    use BuildsAnalyticsAiBreakdowns;

    public function __construct(
        protected CdrDataService $cdrDataService,
        protected ContactCallerIdResolver $contactCallerIdResolver,
        protected RecorderAnalyticsService $recorderAnalyticsService,
    ) {
    }

    public function isExecutiveSummaryAvailable(): bool
    {
        return $this->recorderAnalyticsService->isExecutiveSummaryAvailable();
    }

    public function normalizeFilters(array $filters, ?string $domainUuid = null): array
    {
        $normalized = [
            'search' => $this->normalizeSearch($filters['search'] ?? null),
            'direction' => $this->normalizeDirection($filters['direction'] ?? null),
            'status' => $this->normalizeStatus($filters['status'] ?? null),
            'entity' => $this->normalizeEntity($filters['entity'] ?? null),
            'sentiment' => $this->normalizeSentiment($filters['sentiment'] ?? null),
        ];

        if ($domainUuid && $this->shouldRestrictToSelfRecords()) {
            $user = auth()->user();
            if ($user?->extension_uuid) {
                $normalized['entity'] = [
                    'type' => 'extension',
                    'value' => (string) $user->extension_uuid,
                ];
            }
        }

        return $normalized;
    }

    public function buildReport(string $domainUuid, Carbon $startUtc, Carbon $endUtc, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters, $domainUuid);
        $timezone = get_local_time_zone($domainUuid);
        $startEpoch = $startUtc->copy()->setTimezone('UTC')->timestamp;
        $endEpoch = $endUtc->copy()->setTimezone('UTC')->timestamp;

        $calls = $this->cdrDataService
            ->callHistoryAnalyticsQuery($domainUuid, $startEpoch, $endEpoch, $filters)
            ->with([
                'callTranscription:uuid,xml_cdr_uuid,status,summary_status,summary_payload',
                'archive_recording:xml_cdr_uuid,object_key',
            ])
            ->orderByDesc('start_epoch')
            ->get([
                'xml_cdr_uuid',
                'domain_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
                'duration',
                'status',
                'record_path',
                'record_name',
                'record_length',
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
        $directionBreakdown = [];
        $recordingStatusCounts = [];
        $transcriptionStatusCounts = [];
        $summaryStatusCounts = [];
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

            $directionKey = trim((string) ($call->direction ?? '')) ?: 'unknown';
            $directionBreakdown[$directionKey] = ($directionBreakdown[$directionKey] ?? 0) + 1;

            $this->bumpBreakdownCount(
                $recordingStatusCounts,
                $this->recordingStatusBucket($this->cdrDataService->cdrHasRecording($call))
            );
            $this->bumpBreakdownCount(
                $transcriptionStatusCounts,
                $this->transcriptionStatusBucket($transcription)
            );
            $this->bumpBreakdownCount(
                $summaryStatusCounts,
                $this->summaryStatusBucket($transcription)
            );

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
                'direction' => $call->direction,
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

        $filterLabel = $this->buildFilterLabel($filters);
        if ($filterLabel !== '') {
            $periodLabel .= ' · ' . $filterLabel;
        }

        ksort($callsByDay);

        return [
            'domain_uuid' => $domainUuid,
            'timezone' => $timezone,
            'filters' => $filters,
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
            'calls_by_day' => collect($callsByDay)->map(fn ($count, $date) => ['date' => $date, 'count' => $count])->values()->all(),
            'status_breakdown' => $this->sortedBreakdownRows($statusBreakdown),
            'direction_breakdown' => $this->sortedBreakdownRows($directionBreakdown),
            'recording_status_breakdown' => $this->formatRecordingStatusBreakdown($recordingStatusCounts),
            'transcription_status_breakdown' => $this->formatTranscriptionStatusBreakdown($transcriptionStatusCounts),
            'summary_status_breakdown' => $this->formatSummaryStatusBreakdown($summaryStatusCounts),
            'top_topics' => collect($topicCounts)->values()->sortByDesc('count')->take(10)->values()->all(),
            'calls' => $callRows,
        ];
    }

    public function buildCsvContent(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date', 'Time', 'Direction', 'Caller', 'Dialed', 'Duration', 'Status', 'Sentiment', 'Summary']);

        foreach ($report['calls'] ?? [] as $call) {
            fputcsv($handle, [
                $call['date'] ?? '',
                $call['time'] ?? '',
                $call['direction'] ?? '',
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

        return sprintf('call-history-analytics-%s-to-%s.csv', $start, $end);
    }

    public function generateExecutiveSummary(array $report, string $source = 'call_history_api'): array
    {
        return $this->recorderAnalyticsService->generateExecutiveSummary($report, $source);
    }

    public function maybeAttachExecutiveSummary(array $report, bool $includeExecutiveSummary): array
    {
        return $this->recorderAnalyticsService->maybeAttachExecutiveSummary(
            $report,
            $includeExecutiveSummary
        );
    }

    public function scheduledPeriod(string $frequency, string $domainUuid): array
    {
        return $this->recorderAnalyticsService->scheduledPeriod($frequency, $domainUuid);
    }

    public function isScheduleDue(CallHistoryAnalyticsReportSchedule $schedule): bool
    {
        return $this->recorderAnalyticsService->isScheduleDue($schedule);
    }

    public function normalizeEmails(array|string|null $emails): array
    {
        return $this->recorderAnalyticsService->normalizeEmails($emails);
    }

    protected function shouldRestrictToSelfRecords(): bool
    {
        return userCheckPermission('xml_cdr_view')
            && userCheckPermission('xml_cdr_view_self_records')
            && ! userCheckPermission('xml_cdr_view_all_records');
    }

    protected function sortedBreakdownRows(array $breakdown): array
    {
        $rows = [];
        foreach ($breakdown as $key => $count) {
            $rows[] = ['status' => $key, 'count' => $count];
        }

        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $rows;
    }

    protected function buildFilterLabel(array $filters): string
    {
        $parts = [];

        if (! empty($filters['search'])) {
            $parts[] = 'Search: "' . $filters['search'] . '"';
        }

        if (! empty($filters['direction'])) {
            $parts[] = 'Direction: ' . ucfirst((string) $filters['direction']);
        }

        if (! empty($filters['status'])) {
            $parts[] = 'Status: ' . ucwords(str_replace('_', ' ', (string) $filters['status']));
        }

        if (! empty($filters['sentiment'])) {
            $parts[] = 'Sentiment: ' . ucfirst((string) $filters['sentiment']);
        }

        return implode(' · ', $parts);
    }

    protected function normalizeSearch(mixed $search): ?string
    {
        $search = trim((string) $search);

        return $search !== '' ? mb_substr($search, 0, 200) : null;
    }

    protected function normalizeDirection(mixed $direction): ?string
    {
        $direction = strtolower(trim((string) $direction));

        return in_array($direction, ['inbound', 'outbound', 'local'], true) ? $direction : null;
    }

    protected function normalizeStatus(mixed $status): ?string
    {
        if (is_array($status)) {
            $status = $status['value'] ?? null;
        }

        $status = strtolower(trim((string) $status));
        $allowed = ['answered', 'no_answer', 'cancelled', 'voicemail', 'missed call', 'abandoned'];

        return in_array($status, $allowed, true) ? $status : null;
    }

    protected function normalizeEntity(mixed $entity): ?array
    {
        if (! is_array($entity)) {
            return null;
        }

        $type = (string) ($entity['type'] ?? '');
        $value = trim((string) ($entity['value'] ?? ''));

        if (! in_array($type, ['extension', 'queue'], true)) {
            return null;
        }

        if ($value === '') {
            return ['type' => $type, 'value' => ''];
        }

        return ['type' => $type, 'value' => $value];
    }

    protected function normalizeSentiment(mixed $sentiment): ?string
    {
        if (is_array($sentiment)) {
            $sentiment = $sentiment['value'] ?? null;
        }

        $sentiment = strtolower(trim((string) $sentiment));

        return in_array($sentiment, ['positive', 'neutral', 'negative'], true) ? $sentiment : null;
    }
}
