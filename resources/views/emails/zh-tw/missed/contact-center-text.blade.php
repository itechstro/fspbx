{{-- email-template
format: text
layout: none
--}}
放棄來電{{ $attributes['caller_id_number'] ? '，來自 '.$attributes['caller_id_number'] : '' }}。

來電者在坐席接聽前離開了 {{ $attributes['queue_display'] }}。

來電者：{{ $attributes['caller_display'] }}
聯絡中心：{{ $attributes['queue_display'] }}
原因：{{ $attributes['departure_reason'] }}
@if (!empty($attributes['wait_duration']))
等待時間：{{ $attributes['wait_duration'] }}
@endif
通話 ID：{{ $attributes['call_uuid'] }}

謝謝，
{{ config('app.name', 'FS PBX') }} 團隊
