{{-- email-template
format: text
layout: none
--}}
通話轉寫報告
=========================
日期：     {{ $data['date'] }}
時長：     {{ $data['duration'] }}
@if(!empty($data['has_summary']))
情緒：{{ $data['sentiment'] }}

執行摘要
-----------------
"{{ $data['summary'] }}"

@endif

@if(!empty($data['action_items']))
待辦事項與後續步驟
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif

完整轉寫
------------------
@foreach($data['template_utterances'] as $line)
{{ $line['speaker_name'] }} [{{ $line['time'] }}]:
{{ $line['text'] }}

@endforeach

@if(!empty($data['template_translation_utterances']) || !empty($data['translation_text']))
翻譯 @if(!empty($data['translation_target_language']))({{ $data['translation_target_language'] }})@endif
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
