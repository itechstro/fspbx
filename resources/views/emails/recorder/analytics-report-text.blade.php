{{-- email-template
format: text
layout: none
--}}
Recorder Analytics Report
{{ $data['domain_name'] ?? 'Domain' }}
{{ $data['period_label'] ?? '' }}
Generated {{ $data['generated_at'] ?? '' }}

Total calls: {{ $data['summary']['total_calls'] ?? 0 }}
Total duration: {{ $data['summary']['total_duration'] ?? '00:00:00' }}
Average duration: {{ $data['summary']['average_duration'] ?? '00:00:00' }}
Transcribed: {{ $data['summary']['transcribed_count'] ?? 0 }}
Summarized: {{ $data['summary']['summarized_count'] ?? 0 }}

Sentiment
Positive: {{ $data['summary']['sentiment']['positive'] ?? 0 }}
Neutral: {{ $data['summary']['sentiment']['neutral'] ?? 0 }}
Negative: {{ $data['summary']['sentiment']['negative'] ?? 0 }}
Unknown: {{ $data['summary']['sentiment']['unknown'] ?? 0 }}

Calls Per Day
@if(empty($data['calls_by_day']))
No daily call data for this period.
@else
@foreach($data['calls_by_day'] as $row)
{{ $row['date'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Transcription Status
@if(empty($data['transcription_status_breakdown']))
No transcription data for this period.
@else
@foreach($data['transcription_status_breakdown'] as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Summary Status
@if(empty($data['summary_status_breakdown']))
No summary data for this period.
@else
@foreach($data['summary_status_breakdown'] as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Top Topics
@if(empty($data['top_topics']))
No summary topics for this period.
@else
@foreach($data['top_topics'] as $topic)
- {{ $topic['label'] ?? '' }} ({{ $topic['count'] ?? 0 }})
@endforeach
@endif

AI Executive Summary
@if(!empty($data['executive_summary']))
@if(!empty($data['executive_summary']['overview']))
{{ $data['executive_summary']['overview'] }}

@endif
@if(!empty($data['executive_summary']['highlights']))
Highlights:
@foreach($data['executive_summary']['highlights'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($data['executive_summary']['concerns']))
Concerns:
@foreach($data['executive_summary']['concerns'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($data['executive_summary']['recommendations']))
Recommendations:
@foreach($data['executive_summary']['recommendations'] as $item)
- {{ $item }}
@endforeach

@endif
@elseif(!empty($data['executive_summary_error']))
Executive summary was not included: {{ $data['executive_summary_error'] }}

@endif

Recorded Calls
@if(empty($data['template_calls']))
No recorder calls were found for this period.
@else
Showing up to 25 calls below. The attached CSV includes the full list.

@foreach($data['template_calls'] as $call)
---
{{ $call['date'] ?? '' }} {{ $call['time'] ?? '' }}
Caller: {{ $call['caller'] ?? '—' }}
Dialed: {{ $call['dialed'] ?? '—' }}
Duration: {{ $call['duration'] ?? '—' }}
Sentiment: {{ $call['sentiment'] ?? '—' }}
Summary: {{ $call['summary'] ?? '—' }}
@endforeach
@endif

Open Recorder: {{ $data['recorder_url'] ?? '' }}
