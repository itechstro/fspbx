{{-- email-template
format: text
layout: none
--}}
การส่งต่อวอยซ์เมล{{ $statusLabel }}

การส่งต่อวอยซ์เมลของกล่องเสียง {{ $notification->mailbox ?? 'ไม่ทราบ' }} เสร็จสิ้นแล้ว สถานะ {{ $statusLabel }}

กล่องเสียง: {{ $notification->mailbox ?? '—' }}
สถานะ: {{ $statusLabel }}
ชื่อผู้โทร: {{ $notification->caller_id_name ?? '—' }}
หมายเลขผู้โทร: {{ $notification->caller_id_number ?? '—' }}
ความยาวข้อความ: {{ $notification->message_length_seconds ?? '—' }} วินาที
เวลาที่ฝากข้อความ: {{ optional($notification->message_left_at)?->copy()->timezone($tenantTimeZone)->format('Y-m-d g:i:s A T') ?? '—' }}
รับสายโดย: {{ $notification->accepted_by_number ?? '—' }}
ครั้งที่ลองใหม่: {{ $notification->current_retry ?? 0 }}
ลำดับความสำคัญสุดท้าย: {{ $notification->current_priority ?? '—' }}
รหัสการแจ้งเตือน: {{ $notification->vm_notify_notification_uuid }}

@if($notification->attempts->count())
ความพยายาม:
@foreach($notification->attempts as $attempt)
{{ $attempt->destination ?? '—' }} | {{ $attempt->status ?? '—' }} | ลองใหม่ {{ $attempt->retry_number ?? '—' }} | ลำดับความสำคัญ {{ $attempt->priority ?? '—' }} | {{ $attempt->claim_result ?? '—' }}
@endforeach
@endif

@if($template_logs->count())
บันทึกการแจ้งเตือน:
@foreach($template_logs as $log)
{{ $log['time'] }} | {{ $log['level'] }} | {{ $log['message'] }} | {{ $log['destination'] }} | ลองใหม่ {{ $log['retry_number'] }} | ลำดับความสำคัญ {{ $log['priority'] }}
@endforeach
@endif

อีเมลนี้สร้างขึ้นโดยอัตโนมัติจากระบบส่งต่อวอยซ์เมลของ {{ config('app.name', 'FS PBX') }}
