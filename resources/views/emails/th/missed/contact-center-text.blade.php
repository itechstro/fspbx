{{-- email-template
format: text
layout: none
--}}
สายที่วางก่อนได้รับบริการ{{ $attributes['caller_id_number'] ? ' จาก '.$attributes['caller_id_number'] : '' }}

ผู้โทรออกจาก {{ $attributes['queue_display'] }} ก่อนที่เจ้าหน้าที่จะรับสาย

จาก: {{ $attributes['caller_display'] }}
ศูนย์ติดต่อ: {{ $attributes['queue_display'] }}
สาเหตุ: {{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
เวลารอ: {{ $attributes['wait_duration'] }}
@endif
รหัสสาย: {{ $attributes['call_uuid'] }}

ขอบคุณ
ทีม {{ config('app.name', 'FS PBX') }}
