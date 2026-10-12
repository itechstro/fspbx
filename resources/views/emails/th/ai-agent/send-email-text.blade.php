{{-- email-template
format: text
layout: none
--}}
{{ $attributes['email_subject'] }}

AI Agent ได้รวบรวมข้อมูลต่อไปนี้สำหรับการติดตามผล:

@foreach ($attributes['fields'] as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@endforeach
@if (!empty($attributes['notes']))

ข้อมูลเพิ่มเติม:
{{ $attributes['notes'] }}
@endif
