{{-- email-template
format: text
layout: none
--}}
รายงานการจัดเก็บถาวรใหม่

สคริปต์ย้ายไฟล์ทำงานเสร็จแล้ว

เซิร์ฟเวอร์: {{ $attributes['hostname'] ?? 'unknown' }}
สำเร็จ: {{ isset($attributes['success']) ? count($attributes['success']) : 0 }}
ล้มเหลว: {{ isset($attributes['failed']) ? count($attributes['failed']) : 0 }}

@if(isset($attributes['failed']) && count($attributes['failed']) > 0)
รายการที่ล้มเหลว:
@foreach($attributes['failed'] as $record)
- {{ $record['name'] }} — {{ $record['msg'] }}
@endforeach
@endif
