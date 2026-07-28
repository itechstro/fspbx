{{-- email-template
format: text
layout: none
--}}
CALL TRANSCRIPTION REPORT
=========================
Date:      {{ $data['date'] }}
Duration:  {{ $data['duration'] }}
@if(!empty($data['has_summary']))
Sentiment: {{ $data['sentiment'] }}

EXECUTIVE SUMMARY
-----------------
"{{ $data['summary'] }}"

@endif

@if(!empty($data['action_items']))
ACTION ITEMS & NEXT STEPS
-------------------------
@foreach($data['action_items'] as $item)
[ ] @if($item['owner'])({{ $item['owner'] }}) @endif{{ $item['description'] }}
@endforeach
@endif

FULL TRANSCRIPTION
------------------
@foreach($data['template_utterances'] as $line)
{{ $line['speaker_name'] }} [{{ $line['time'] }}]:
{{ $line['text'] }}

@endforeach

@if(!empty($data['template_translation_utterances']) || !empty($data['translation_text']))
TRANSLATION @if(!empty($data['translation_target_language']))({{ $data['translation_target_language'] }})@endif
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
