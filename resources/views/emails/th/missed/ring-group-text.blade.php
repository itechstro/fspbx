{{-- email-template
format: text
layout: none
--}}
สายที่ไม่ได้รับ{{ $attributes['caller_id_number'] ? ' จาก ' . $attributes['caller_id_number'] : '' }}

สายที่เข้ามาที่ {{ $attributes['ring_group_display'] ?: 'กลุ่มเรียกพร้อมกันของคุณ' }} ไม่มีผู้รับสาย

จาก: {{ $attributes['caller_display'] ?: 'ผู้โทรไม่ทราบชื่อ' }}
ถึง: {{ $attributes['ring_group_display'] ?: 'กลุ่มเรียกพร้อมกัน' }}
@if (!empty($attributes['destination_number']))
หมายเลขที่กด: {{ $attributes['destination_number'] }}
@endif

ขอบคุณ
ทีม {{ config('app.name', 'FS PBX') }}
