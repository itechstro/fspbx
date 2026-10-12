{{-- email-template
version: 1.0.0
language: zh-tw
category: domain-usage
subcategory: ai-limit-alert
format: html
layout: none
subject: {{ $email_subject }}
description: 租戶 AI 用量接近或達到上限警示
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>AI 用量警示</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 0.25rem;">AI 用量警示</h2>
    @if(!empty($data['is_test']))
        <p style="margin: 0 0 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #eff6ff; color: #1d4ed8; font-size: 0.9rem;">
            這是測試警示。下列用量數字僅為範例資料。
        </p>
    @endif
    <p style="margin-top: 0; color: #4b5563;">
        {{ $data['domain_name'] ?? '租戶' }}
        · {{ $data['period_label'] ?? $data['period'] ?? '' }}
    </p>

    <p style="display: inline-block; padding: 0.35rem 0.75rem; border-radius: 9999px; background: {{ $data['badge_bg'] }}; color: {{ $data['badge_color'] }}; font-weight: 600;">
        {{ $data['badge_label'] }}
    </p>

    <table style="margin-top: 1rem; border-collapse: collapse; width: 100%; max-width: 520px;">
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">服務</td>
            <td style="padding: 0.5rem 0; font-weight: 600;">{{ $data['limit_label'] ?? '' }}</td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">用量</td>
            <td style="padding: 0.5rem 0;">
                {{ $data['usage_formatted'] }}
                /
                {{ $data['limit_formatted'] }}
                {{ $data['unit'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">剩餘</td>
            <td style="padding: 0.5rem 0;">
                {{ $data['remaining_formatted'] }}
                {{ $data['unit'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">已使用</td>
            <td style="padding: 0.5rem 0;">{{ $data['percent_used_formatted'] }}%</td>
        </tr>
    </table>

    <p style="margin-top: 1.25rem;">
        @if(!empty($data['is_reached']))
            在用量重置或提高上限之前，此服務的新 AI 請求可能會被阻擋。
        @else
            用量已接近每月上限。請考慮提高租戶上限或檢視 AI 活動。
        @endif
    </p>

    <p style="color: #6b7280; font-size: 0.9rem;">
        請在此租戶的網域授權中檢視用量。
    </p>
</body>
</html>
