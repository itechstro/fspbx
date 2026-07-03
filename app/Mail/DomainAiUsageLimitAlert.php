<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DomainAiUsageLimitAlert extends BaseMailable
{
    public function __construct(public array $data)
    {
        parent::__construct($data);
    }

    public function envelope(): Envelope
    {
        $domain = (string) ($this->data['domain_name'] ?? 'Tenant');
        $label = (string) ($this->data['limit_label'] ?? 'AI usage');
        $level = ($this->data['alert_level'] ?? '') === 'reached' ? 'limit reached' : 'approaching limit';
        $prefix = ! empty($this->data['is_test']) ? '[Test] ' : '';

        return new Envelope(
            subject: "{$prefix}AI usage alert: {$domain} — {$label} {$level}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.domain-usage.ai-limit-alert',
            text: 'emails.domain-usage.ai-limit-alert-text',
            with: ['data' => $this->data],
        );
    }
}
