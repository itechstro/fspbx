{{-- email-template
format: text
layout: none
--}}
未接來電{{ $attributes['caller_id_number'] ? '，來自 ' . $attributes['caller_id_number'] : '' }}。

撥打至 {{ $attributes['ring_group_display'] ?: '您的響鈴群組' }} 的來電無人接聽。

來電者：{{ $attributes['caller_display'] ?: '未知來電者' }}
對象：{{ $attributes['ring_group_display'] ?: '響鈴群組' }}
@if (!empty($attributes['destination_number']))
撥打號碼：{{ $attributes['destination_number'] }}
@endif

謝謝，
{{ config('app.name', 'FS PBX') }} 團隊
