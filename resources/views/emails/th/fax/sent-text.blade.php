{{-- email-template
format: text
layout: none
--}}
แฟกซ์ของคุณถึง {{ $attributes['fax_destination'] }} ถูกส่งสำเร็จแล้ว

แฟกซ์ถูกส่งสำเร็จเมื่อ {{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}

@if (!empty($attributes['fax_pages']))
จำนวนหน้าที่ส่ง: {{ $attributes['fax_pages'] }}@if (isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages']) จาก {{ $attributes['fax_total_pages'] }}@endif
@endif
@if (!empty($attributes['fax_duration_formatted']))
ระยะเวลา: {{ $attributes['fax_duration_formatted'] }}
@endif

แฟกซ์ที่ส่งถูกแนบมากับอีเมลนี้เพื่อเก็บเป็นหลักฐาน

ขอบคุณ
ทีม {{ config('app.name', 'Laravel') }}
