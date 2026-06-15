Recorder Analytics Report
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

Call Status
@php($statusBreakdown = $data['status_breakdown'] ?? [])
@if(empty($statusBreakdown))
No status data for this period.
@else
@foreach($statusBreakdown as $row)
{{ ucwords(str_replace('_', ' ', $row['status'] ?? 'unknown')) }}: {{ $row['count'] ?? 0 }}
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

Recorded Calls
@php($emailCalls = array_slice($data['calls'] ?? [], 0, 25))
@if(empty($emailCalls))
No recorder calls were found for this period.
@else
Showing up to 25 calls below. The attached CSV includes the full list.

@foreach($emailCalls as $call)
---
{{ $call['date'] ?? '' }} {{ $call['time'] ?? '' }}
Caller: {{ $call['caller'] ?? '—' }}
Dialed: {{ $call['dialed'] ?? '—' }}
Duration: {{ $call['duration'] ?? '—' }}
Sentiment: {{ $call['sentiment'] ?? '—' }}
Summary: {{ $call['summary'] ?? '—' }}
@endforeach
@endif

Open Recorder: {{ $data['recorder_url'] ?? url('/recorder') }}
