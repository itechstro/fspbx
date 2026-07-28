<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Collection;

class CallHistoryAnalyticsReportMail extends BaseMailable
{
    public function __construct(
        public array $data,
        protected ?string $csvContent = null,
        protected ?string $csvFilename = null,
    ) {
        $data = $this->prepareTemplateData($data);
        $data['email_subject'] = $data['email_subject']
            ?? 'Call History analytics report'
                .(! empty($data['domain_name']) ? ' — '.$data['domain_name'] : '');

        parent::__construct($data);
        $this->data = $data;
        $this->useEmailTemplate('call-history', 'analytics-report');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.call-history.analytics-report',
            text: 'emails.call-history.analytics-report-text',
        ));
    }

    public function attachments(): array
    {
        if ($this->csvContent === null || $this->csvContent === '') {
            return [];
        }

        $filename = $this->csvFilename ?: 'call-history-analytics.csv';
        $content = $this->csvContent;

        return [
            Attachment::fromData(fn () => $content, $filename)
                ->withMime('text/csv'),
        ];
    }

    private function prepareTemplateData(array $data): array
    {
        $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
        $summary['sentiment'] = is_array($summary['sentiment'] ?? null) ? $summary['sentiment'] : [];
        $data['summary'] = $summary;

        $data['calls_by_day'] = is_array($data['calls_by_day'] ?? null) ? $data['calls_by_day'] : [];
        $data['direction_breakdown'] = $this->withStatusLabels($data['direction_breakdown'] ?? [], 'ucfirst');
        $data['status_breakdown'] = $this->withStatusLabels($data['status_breakdown'] ?? [], 'title');
        $data['recording_status_breakdown'] = is_array($data['recording_status_breakdown'] ?? null)
            ? $data['recording_status_breakdown']
            : [];
        $data['transcription_status_breakdown'] = is_array($data['transcription_status_breakdown'] ?? null)
            ? $data['transcription_status_breakdown']
            : [];
        $data['summary_status_breakdown'] = is_array($data['summary_status_breakdown'] ?? null)
            ? $data['summary_status_breakdown']
            : [];
        $data['top_topics'] = is_array($data['top_topics'] ?? null) ? $data['top_topics'] : [];
        $data['call_history_url'] = $data['call_history_url'] ?? url('/call-detail-records');

        $data['template_calls'] = Collection::make($data['calls'] ?? [])
            ->take(25)
            ->map(function (array $call) {
                $direction = (string) ($call['direction'] ?? '');

                return array_merge($call, [
                    'direction_label' => $direction !== ''
                        ? ucfirst($direction)
                        : '—',
                ]);
            })
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  mixed  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withStatusLabels(mixed $rows, string $style): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return Collection::make($rows)
            ->map(function (array $row) use ($style) {
                $status = (string) ($row['status'] ?? 'unknown');
                $row['label'] = $style === 'title'
                    ? ucwords(str_replace('_', ' ', $status))
                    : ucfirst($status);

                return $row;
            })
            ->all();
    }
}
