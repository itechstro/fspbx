CALL TRANSCRIPTION REPORT
=========================
Date:      {{ $data['date'] }}
Duration:  {{ $data['duration'] }}
Sentiment: {{ $data['sentiment'] }}

EXECUTIVE SUMMARY
-----------------
"{{ $data['summary'] }}"

@if(!empty($data['translation_summary']))
SUMMARY TRANSLATION @if(!empty($data['translation_target_language']))({{ $data['translation_target_language'] }})@endif
--------------------------------------------------------------------
{{ $data['translation_summary'] }}
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
@foreach($data['utterances'] as $line)
{{ $data['speaker_map'][$line['speaker']] ?? "Speaker ".$line['speaker'] }} [{{ gmdate("i:s", intval(($line['start'] ?? 0) / 1000)) }}]:
{{ $line['text'] ?? '' }}

@endforeach

@if(!empty($data['translation_text']))
TRANSLATION @if(!empty($data['translation_target_language']))({{ $data['translation_target_language'] }})@endif
--------------------------------------------------------------------
{{ $data['translation_text'] }}
@endif
