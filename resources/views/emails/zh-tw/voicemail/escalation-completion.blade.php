{{-- email-template
version: 1.1.0
language: zh-tw
category: voicemail
subcategory: escalation-completion
format: html
layout: none
subject: {{ $email_subject }}
description: 語音信箱升級完成報告
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 16px;">語音信箱升級{{ $statusLabel }}</h2>

    <p>
        信箱 <strong>{{ $notification->mailbox ?? '未知' }}</strong>
        的語音信箱升級已完成，狀態為 <strong>{{ $statusLabel }}</strong>。
    </p>

    <h3 style="margin-top: 24px;">留言詳細資料</h3>
    <table cellpadding="6" cellspacing="0" border="0">
        <tr>
            <td><strong>信箱：</strong></td>
            <td>{{ $notification->mailbox ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>狀態：</strong></td>
            <td>{{ $statusLabel }}</td>
        </tr>
        <tr>
            <td><strong>來電顯示名稱：</strong></td>
            <td>{{ $notification->caller_id_name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>來電顯示號碼：</strong></td>
            <td>{{ $notification->caller_id_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>留言長度：</strong></td>
            <td>{{ $notification->message_length_seconds ?? '—' }} 秒</td>
        </tr>
        <tr>
            <td><strong>留言時間：</strong></td>
            <td>{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>接聽者：</strong></td>
            <td>{{ $notification->accepted_by_number ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>重試次數：</strong></td>
            <td>{{ $notification->current_retry ?? 0 }}</td>
        </tr>
        <tr>
            <td><strong>最終優先順序：</strong></td>
            <td>{{ $notification->current_priority ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>通知 ID：</strong></td>
            <td>{{ $notification->vm_notify_notification_uuid }}</td>
        </tr>
    </table>

    @if($notification->attempts->count())
        <h3 style="margin-top: 24px;">嘗試紀錄</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">目的地</th>
                    <th align="left">狀態</th>
                    <th align="left">重試</th>
                    <th align="left">優先順序</th>
                    <th align="left">認領結果</th>
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
        <h3 style="margin-top: 24px;">通知紀錄</h3>
        <table cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th align="left">時間</th>
                    <th align="left">層級</th>
                    <th align="left">訊息</th>
                    <th align="left">目的地</th>
                    <th align="left">重試</th>
                    <th align="left">優先順序</th>
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
           此郵件由 {{ config('app.name', 'FS PBX') }} 語音信箱升級功能自動產生。
    </p>
</body>
</html>
