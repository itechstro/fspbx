{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

AI 客服已收集下列資訊，供後續追蹤：

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

補充資訊：
{{ $attributes['notes'] }}
@endif
