<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class CallHistoryAnalyticsReportMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data,
        protected ?string $csvContent = null,
        protected ?string $csvFilename = null,
    ) {
        parent::__construct($data);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.call-history.analytics-report',
            text: 'emails.call-history.analytics-report-text',
            with: ['data' => $this->data],
        );
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
}
