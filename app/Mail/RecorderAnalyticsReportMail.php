<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Collection;

class RecorderAnalyticsReportMail extends BaseMailable
{
    public function __construct(
        public array $data,
        protected ?string $csvContent = null,
        protected ?string $csvFilename = null,
    ) {
        $data = $this->prepareTemplateData($data);
        $data['email_subject'] = $data['email_subject']
            ?? 'Recorder analytics report'
                .(! empty($data['domain_name']) ? ' — '.$data['domain_name'] : '');

        parent::__construct($data);
        $this->data = $data;
        $this->useEmailTemplate('recorder', 'analytics-report');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.recorder.analytics-report',
            text: 'emails.recorder.analytics-report-text',
        ));
    }

    public function attachments(): array
    {
        if ($this->csvContent === null || $this->csvContent === '') {
            return [];
        }

        $filename = $this->csvFilename ?: 'recorder-analytics.csv';
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
        $data['transcription_status_breakdown'] = is_array($data['transcription_status_breakdown'] ?? null)
            ? $data['transcription_status_breakdown']
            : [];
        $data['summary_status_breakdown'] = is_array($data['summary_status_breakdown'] ?? null)
            ? $data['summary_status_breakdown']
            : [];
        $data['top_topics'] = is_array($data['top_topics'] ?? null) ? $data['top_topics'] : [];
        $data['recorder_url'] = $data['recorder_url'] ?? url('/recorder');
        $data['template_calls'] = Collection::make($data['calls'] ?? [])
            ->take(25)
            ->values()
            ->all();

        return $data;
    }
}
