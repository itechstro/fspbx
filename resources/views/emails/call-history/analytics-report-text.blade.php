Call History Analytics Report
{{ $data['domain_name'] ?? 'Domain' }}
{{ $data['period_label'] ?? '' }}
Generated {{ $data['generated_at'] ?? '' }}

@php($summary = $data['summary'] ?? [])
Total calls: {{ $summary['total_calls'] ?? 0 }}
Total duration: {{ $summary['total_duration'] ?? '00:00:00' }}
Average duration: {{ $summary['average_duration'] ?? '00:00:00' }}
Transcribed: {{ $summary['transcribed_count'] ?? 0 }}
Summarized: {{ $summary['summarized_count'] ?? 0 }}

Sentiment
@php($sentiment = $summary['sentiment'] ?? [])
Positive: {{ $sentiment['positive'] ?? 0 }}
Neutral: {{ $sentiment['neutral'] ?? 0 }}
Negative: {{ $sentiment['negative'] ?? 0 }}
Unknown: {{ $sentiment['unknown'] ?? 0 }}

Calls Per Day
@php($callsByDay = $data['calls_by_day'] ?? [])
@if(empty($callsByDay))
No daily call data for this period.
@else
@foreach($callsByDay as $row)
{{ $row['date'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Call Direction
@php($directionBreakdown = $data['direction_breakdown'] ?? [])
@if(empty($directionBreakdown))
No direction data for this period.
@else
@foreach($directionBreakdown as $row)
{{ ucfirst($row['status'] ?? 'unknown') }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Call Status
@php($statusBreakdown = $data['status_breakdown'] ?? [])
@if(empty($statusBreakdown))
No status data for this period.
@else
@foreach($statusBreakdown as $row)
{{ ucwords(str_replace('_', ' ', $row['status'] ?? 'unknown')) }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Recording Availability
@php($recordingStatusBreakdown = $data['recording_status_breakdown'] ?? [])
@if(empty($recordingStatusBreakdown))
No recording data for this period.
@else
@foreach($recordingStatusBreakdown as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Transcription Status
@php($transcriptionStatusBreakdown = $data['transcription_status_breakdown'] ?? [])
@if(empty($transcriptionStatusBreakdown))
No transcription data for this period.
@else
@foreach($transcriptionStatusBreakdown as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Summary Status
@php($summaryStatusBreakdown = $data['summary_status_breakdown'] ?? [])
@if(empty($summaryStatusBreakdown))
No summary data for this period.
@else
@foreach($summaryStatusBreakdown as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

Top Topics
@php($topTopics = $data['top_topics'] ?? [])
@if(empty($topTopics))
No summary topics for this period.
@else
@foreach($topTopics as $topic)
- {{ $topic['label'] ?? '' }} ({{ $topic['count'] ?? 0 }})
@endforeach
@endif

AI Executive Summary
@php($executiveSummary = $data['executive_summary'] ?? null)
@if(!empty($executiveSummary))
@if(!empty($executiveSummary['overview']))
{{ $executiveSummary['overview'] }}

@endif
@if(!empty($executiveSummary['highlights']))
Highlights:
@foreach($executiveSummary['highlights'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($executiveSummary['concerns']))
Concerns:
@foreach($executiveSummary['concerns'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($executiveSummary['recommendations']))
Recommendations:
@foreach($executiveSummary['recommendations'] as $item)
- {{ $item }}
@endforeach

@endif
@elseif(!empty($data['executive_summary_error']))
Executive summary was not included: {{ $data['executive_summary_error'] }}

@endif

Calls
@php($emailCalls = array_slice($data['calls'] ?? [], 0, 25))
@if(empty($emailCalls))
No calls were found for this period.
@else
Showing up to 25 calls below. The attached CSV includes the full list.

@foreach($emailCalls as $call)
---
{{ $call['date'] ?? '' }} {{ $call['time'] ?? '' }}
Direction: {{ ucfirst($call['direction'] ?? '—') }}
Caller: {{ $call['caller'] ?? '—' }}
Dialed: {{ $call['dialed'] ?? '—' }}
Duration: {{ $call['duration'] ?? '—' }}
Status: {{ $call['status'] ?? '—' }}
Sentiment: {{ $call['sentiment'] ?? '—' }}
Summary: {{ $call['summary'] ?? '—' }}
@endforeach
@endif

Open Call History: {{ $data['call_history_url'] ?? url('/call-detail-records') }}
