{{-- email-template
format: text
layout: none
--}}
รายงานการถอดเสียงสาย
=========================
วันที่:     {{ $data['date'] }}
ระยะเวลา:  {{ $data['duration'] }}
@if(!empty($data['has_summary']))
ความรู้สึก: {{ $data['sentiment'] }}

สรุปผู้บริหาร
-----------------
"{{ $data['summary'] }}"

@endif

@if(!empty($data['action_items']))
รายการที่ต้องดำเนินการและขั้นตอนถัดไป
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif

การถอดเสียงฉบับเต็ม
------------------
@foreach($data['template_utterances'] as $line)
{{ $line['speaker_name'] }} [{{ $line['time'] }}]:
{{ $line['text'] }}

@endforeach

@if(!empty($data['template_translation_utterances']) || !empty($data['translation_text']))
คำแปล @if(!empty($data['translation_target_language']))({{ $data['translation_target_language'] }})@endif
--------------------------------------------------------------------
@if(!empty($data['template_translation_utterances']))
@foreach($data['template_translation_utterances'] as $line)
{{ $line['speaker_name'] }} [{{ $line['time'] }}]:
{{ $line['text'] }}

@endforeach
@else
{{ $data['translation_text'] }}
@endif
@endif
