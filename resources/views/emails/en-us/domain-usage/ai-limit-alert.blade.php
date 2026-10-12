{{-- email-template
version: 1.0.0
language: en-us
category: domain-usage
subcategory: ai-limit-alert
format: html
layout: none
subject: {{ $email_subject }}
description: Tenant AI usage approaching or reached limit alert
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>AI usage alert</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 0.25rem;">AI usage alert</h2>
    @if(!empty($data['is_test']))
        <p style="margin: 0 0 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #eff6ff; color: #1d4ed8; font-size: 0.9rem;">
            This is a test alert. Usage numbers below are sample data only.
        </p>
    @endif
    <p style="margin-top: 0; color: #4b5563;">
        {{ $data['domain_name'] ?? 'Tenant' }}
        · {{ $data['period_label'] ?? $data['period'] ?? '' }}
    </p>

    <p style="display: inline-block; padding: 0.35rem 0.75rem; border-radius: 9999px; background: {{ $data['badge_bg'] }}; color: {{ $data['badge_color'] }}; font-weight: 600;">
        {{ $data['badge_label'] }}
    </p>

    <table style="margin-top: 1rem; border-collapse: collapse; width: 100%; max-width: 520px;">
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">Service</td>
            <td style="padding: 0.5rem 0; font-weight: 600;">{{ $data['limit_label'] ?? '' }}</td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">Usage</td>
            <td style="padding: 0.5rem 0;">
                {{ $data['usage_formatted'] }}
                /
                {{ $data['limit_formatted'] }}
                {{ $data['unit'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">Remaining</td>
            <td style="padding: 0.5rem 0;">
                {{ $data['remaining_formatted'] }}
                {{ $data['unit'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">Used</td>
            <td style="padding: 0.5rem 0;">{{ $data['percent_used_formatted'] }}%</td>
        </tr>
    </table>

    <p style="margin-top: 1.25rem;">
        @if(!empty($data['is_reached']))
            New AI requests for this service may be blocked until usage resets or the limit is increased.
        @else
            Usage is nearing the monthly limit. Consider raising the tenant limit or reviewing AI activity.
        @endif
    </p>

    <p style="color: #6b7280; font-size: 0.9rem;">
        Review usage in Domain License for this tenant.
    </p>
</body>
</html>
