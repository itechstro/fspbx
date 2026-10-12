{{-- email-template
version: 1.0.0
language: th
category: domain-usage
subcategory: ai-limit-alert
format: html
layout: none
subject: {{ $email_subject }}
description: การแจ้งเตือนเมื่อบัญชีใกล้ถึงหรือถึงขีดจำกัดการใช้งาน AI
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>การแจ้งเตือนการใช้งาน AI</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 0.25rem;">การแจ้งเตือนการใช้งาน AI</h2>
    @if(!empty($data['is_test']))
        <p style="margin: 0 0 1rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #eff6ff; color: #1d4ed8; font-size: 0.9rem;">
            นี่คือการแจ้งเตือนทดสอบ ตัวเลขการใช้งานด้านล่างเป็นข้อมูลตัวอย่างเท่านั้น
        </p>
    @endif
    <p style="margin-top: 0; color: #4b5563;">
        {{ $data['domain_name'] ?? 'บัญชี' }}
        · {{ $data['period_label'] ?? $data['period'] ?? '' }}
    </p>

    <p style="display: inline-block; padding: 0.35rem 0.75rem; border-radius: 9999px; background: {{ $data['badge_bg'] }}; color: {{ $data['badge_color'] }}; font-weight: 600;">
        {{ $data['badge_label'] }}
    </p>

    <table style="margin-top: 1rem; border-collapse: collapse; width: 100%; max-width: 520px;">
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">บริการ</td>
            <td style="padding: 0.5rem 0; font-weight: 600;">{{ $data['limit_label'] ?? '' }}</td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">การใช้งาน</td>
            <td style="padding: 0.5rem 0;">
                {{ $data['usage_formatted'] }}
                /
                {{ $data['limit_formatted'] }}
                {{ $data['unit'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">คงเหลือ</td>
            <td style="padding: 0.5rem 0;">
                {{ $data['remaining_formatted'] }}
                {{ $data['unit'] ?? '' }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #6b7280;">ใช้ไปแล้ว</td>
            <td style="padding: 0.5rem 0;">{{ $data['percent_used_formatted'] }}%</td>
        </tr>
    </table>

    <p style="margin-top: 1.25rem;">
        @if(!empty($data['is_reached']))
            คำขอ AI ใหม่สำหรับบริการนี้อาจถูกบล็อกจนกว่าการใช้งานจะรีเซ็ตหรือมีการเพิ่มขีดจำกัด
        @else
            การใช้งานใกล้ถึงขีดจำกัดรายเดือนแล้ว โปรดพิจารณาเพิ่มขีดจำกัดของบัญชีหรือตรวจสอบกิจกรรม AI
        @endif
    </p>

    <p style="color: #6b7280; font-size: 0.9rem;">
        ตรวจสอบการใช้งานได้ที่ใบอนุญาตบัญชีของบัญชีนี้
    </p>
</body>
</html>
