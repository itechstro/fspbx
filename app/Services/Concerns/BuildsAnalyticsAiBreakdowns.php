<?php

namespace App\Services\Concerns;

trait BuildsAnalyticsAiBreakdowns
{
    protected function transcriptionStatusBucket(?object $transcription): string
    {
        if (! $transcription) {
            return 'none';
        }

        $status = strtolower(trim((string) ($transcription->status ?? '')));
        if ($status === '') {
            return 'none';
        }

        if (in_array($status, ['pending', 'queued', 'in_progress', 'completed', 'failed'], true)) {
            return $status;
        }

        return 'other';
    }

    protected function summaryStatusBucket(?object $transcription): string
    {
        if (($transcription?->summary_status ?? null) === 'completed') {
            return 'summarized';
        }

        return 'not_summarized';
    }

    protected function recordingStatusBucket(bool $hasRecording): string
    {
        return $hasRecording ? 'available' : 'unavailable';
    }

    protected function bumpBreakdownCount(array &$breakdown, string $key): void
    {
        $breakdown[$key] = ($breakdown[$key] ?? 0) + 1;
    }

    protected function formatTranscriptionStatusBreakdown(array $counts): array
    {
        return $this->formatAnalyticsBreakdown($counts, [
            'none' => 'None',
            'pending' => 'Pending',
            'queued' => 'Queued',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'other' => 'Other',
        ]);
    }

    protected function formatSummaryStatusBreakdown(array $counts): array
    {
        return $this->formatAnalyticsBreakdown($counts, [
            'summarized' => 'Summarized',
            'not_summarized' => 'Not summarized',
        ]);
    }

    protected function formatRecordingStatusBreakdown(array $counts): array
    {
        return $this->formatAnalyticsBreakdown($counts, [
            'available' => 'Recording available',
            'unavailable' => 'No recording',
        ]);
    }

    protected function formatAnalyticsBreakdown(array $counts, array $labels): array
    {
        $rows = [];

        foreach ($labels as $status => $label) {
            $count = (int) ($counts[$status] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $rows[] = [
                'status' => $status,
                'label' => $label,
                'count' => $count,
            ];
        }

        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $rows;
    }
}
