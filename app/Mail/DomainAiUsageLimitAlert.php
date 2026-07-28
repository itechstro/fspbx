<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class DomainAiUsageLimitAlert extends BaseMailable
{
    public function __construct(public array $data)
    {
        $data = $this->prepareTemplateData($data);

        parent::__construct($data);
        $this->data = $data;
        $this->useEmailTemplate('domain-usage', 'ai-limit-alert');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.domain-usage.ai-limit-alert',
            text: 'emails.domain-usage.ai-limit-alert-text',
        ));
    }

    private function prepareTemplateData(array $data): array
    {
        $isReached = ($data['alert_level'] ?? '') === 'reached';
        $domain = (string) ($data['domain_name'] ?? 'Tenant');
        $label = (string) ($data['limit_label'] ?? 'AI usage');
        $level = $isReached ? 'limit reached' : 'approaching limit';
        $prefix = ! empty($data['is_test']) ? '[Test] ' : '';

        $data['is_reached'] = $isReached;
        $data['badge_label'] = $isReached ? 'Limit reached' : 'Approaching limit';
        $data['badge_color'] = $isReached ? '#b91c1c' : '#b45309';
        $data['badge_bg'] = $isReached ? '#fef2f2' : '#fffbeb';
        $data['usage_formatted'] = number_format((float) ($data['usage'] ?? 0), 2);
        $data['limit_formatted'] = number_format((float) ($data['limit'] ?? 0), 2);
        $data['remaining_formatted'] = number_format((float) ($data['remaining'] ?? 0), 2);
        $data['percent_used_formatted'] = number_format((float) ($data['percent_used'] ?? 0), 1);
        $data['email_subject'] = $data['email_subject']
            ?? "{$prefix}AI usage alert: {$domain} — {$label} {$level}";

        return $data;
    }
}
