{{-- email-template
format: text
layout: none
--}}
寄件者：{{ $attributes['source'] ?? '—' }}
收件者：{{ $attributes['destination'] ?? '—' }}

@if(!empty($attributes['message']))
{{ $attributes['message'] }}
@else
沒有文字內容。
@endif

@if(!empty($attributes['media']) && is_array($attributes['media']))
附件：{{ count($attributes['media']) }}
@foreach($attributes['media'] as $index => $item)
- {{ $item['original_name'] ?? $item['stored_name'] ?? ('附件 ' . ($index + 1)) }}@if(!empty($item['mime_type'])) ({{ $item['mime_type'] }})@endif
@endforeach
@endif
