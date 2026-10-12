{{-- email-template
format: text
layout: none
--}}
語音信箱升級{{ $statusLabel }}

信箱 {{ $notification->mailbox ?? '未知' }} 的語音信箱升級已完成，狀態為 {{ $statusLabel }}。

信箱：{{ $notification->mailbox ?? '—' }}
狀態：{{ $statusLabel }}
來電顯示名稱：{{ $notification->caller_id_name ?? '—' }}
來電顯示號碼：{{ $notification->caller_id_number ?? '—' }}
留言長度：{{ $notification->message_length_seconds ?? '—' }} 秒
留言時間：{{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
接聽者：{{ $notification->accepted_by_number ?? '—' }}
重試次數：{{ $notification->current_retry ?? 0 }}
最終優先順序：{{ $notification->current_priority ?? '—' }}
通知 ID：{{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
嘗試紀錄：
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | 重試 {{ $attempt->retry_number ?? '—' }} | 優先順序 {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
通知紀錄：
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | 重試 {{ $log['retry_number'] }} | 優先順序 {{ $log['priority'] }}
@endforeach
@endif

此郵件由 {{ config('app.name', 'FS PBX') }} 語音信箱升級功能自動產生。
