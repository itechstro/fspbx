{{-- email-template
format: text
layout: none
--}}
จาก: {{ $attributes['source'] ?? '—' }}
ถึง: {{ $attributes['destination'] ?? '—' }}

@if(!empty($attributes['message']))
{{ $attributes['message'] }}
@else
ไม่มีข้อความตัวอักษร
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
ไฟล์แนบ: {{ count($attributes['media']) }}
@foreach($attributes['media'] as $index => $item)
- {{ $item['original_name'] ?? $item['stored_name'] ?? ('ไฟล์แนบ ' . ($index + 1)) }}@if(!empty($item['mime_type'])) ({{ $item['mime_type'] }})@endif
@endforeach
@endif
