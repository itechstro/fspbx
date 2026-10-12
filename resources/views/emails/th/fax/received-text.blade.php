{{-- email-template
format: text
layout: none
--}}
ได้รับแฟกซ์แล้ว{{ $attributes['caller_id_number'] ? ' จาก ' . $attributes['caller_id_number'] : '' }}

มีแฟกซ์ใหม่สำหรับ {{ $attributes['fax_destination'] }} และแนบมากับอีเมลนี้

จาก: {{ $attributes['caller_display'] }}
ถึง: {{ $attributes['fax_destination'] }}
จำนวนหน้า: {{ $attributes['fax_pages'] ?? '' }}
@if (!empty($attributes['fax_date']))
ได้รับเมื่อ: {{ $attributes['fax_date'] }}
@endif

แฟกซ์ถูกแนบเป็นไฟล์ {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}

มีคำถามหรือไม่? ส่งอีเมลถึงทีมลูกค้าสัมพันธ์:
{{ $attributes['support_email'] ?? '' }}

ขอบคุณ
ทีม {{ config('app.name', 'Laravel') }}
