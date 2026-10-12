{{-- email-template
version: 1.1.0
language: th
category: voicemail
subcategory: escalation-completion
format: html
layout: none
subject: {{ $email_subject }}
description: รายงานการส่งต่อวอยซ์เมลเมื่อเสร็จสิ้น
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">การส่งต่อวอยซ์เมล{{ $statusLabel }}</h2>

    <p>
        การส่งต่อวอยซ์เมลของกล่องเสียง <strong>{{ $notification->mailbox ?? 'ไม่ทราบ' }}</strong>
        เสร็จสิ้นแล้ว สถานะ <strong>{{ $statusLabel }}</strong>
    </p>

    <h3 style="margin-top: 24px;">รายละเอียดข้อความ</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>กล่องเสียง:</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>สถานะ:</strong></td>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <td><strong>ชื่อผู้โทร:</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>หมายเลขผู้โทร:</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>ความยาวข้อความ:</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} วินาที</td>
        </tr>
        <tr>
            <td><strong>เวลาที่ฝากข้อความ:</strong></td>
            <td>{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>รับสายโดย:</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>ครั้งที่ลองใหม่:</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>ลำดับความสำคัญสุดท้าย:</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>รหัสการแจ้งเตือน:</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">ความพยายาม</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">ปลายทาง</th>
                    <th align="left">สถานะ</th>
                    <th align="left">ลองใหม่</th>
                    <th align="left">ลำดับความสำคัญ</th>
                    <th align="left">ผลการรับสาย</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notification->attempts as $attempt)
                    <tr>
                        <td>{{ $attempt->destination ?? '—' }}</td>
                        <td>{{ $attempt->status ?? '—' }}</td>
                        <td>{{ $attempt->retry_number ?? '—' }}</td>
                        <td>{{ $attempt->priority ?? '—' }}</td>
                        <td>{{ $attempt->claim_result ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($template_logs->count())
        <h3 style="margin-top: 24px;">บันทึกการแจ้งเตือน</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">เวลา</th>
                    <th align="left">ระดับ</th>
                    <th align="left">ข้อความ</th>
                    <th align="left">ปลายทาง</th>
                    <th align="left">ลองใหม่</th>
                    <th align="left">ลำดับความสำคัญ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($template_logs as $log)
                    <tr>
                        <td>{{ $log['time'] }}</td>
                        <td>{{ $log['level'] }}</td>
                        <td>{{ $log['message'] }}</td>
                        <td>{{ $log['destination'] }}</td>
                        <td>{{ $log['retry_number'] }}</td>
                        <td>{{ $log['priority'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 24px; color: #6B7280; font-size: 12px;">
           อีเมลนี้สร้างขึ้นโดยอัตโนมัติจากระบบส่งต่อวอยซ์เมลของ {{ config('app.name', 'FS PBX') }}
    </p>
</body>
</html>
