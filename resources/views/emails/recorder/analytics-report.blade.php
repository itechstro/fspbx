<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recorder Analytics Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; line-height: 1.5; }
        .container { max-width: 760px; margin: 0 auto; padding: 24px; }
        .header { border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; margin-bottom: 24px; }
        .meta { color: #6b7280; font-size: 14px; }
        .cards { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .cards td { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; width: 33%; vertical-align: top; }
        .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .value { font-size: 22px; font-weight: 700; margin-top: 4px; }
        .section-title { font-size: 16px; font-weight: 700; margin: 24px 0 12px; }
        .sentiment span { display: inline-block; margin-right: 12px; font-size: 14px; }
        table.calls { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.calls th, table.calls td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: left; vertical-align: top; }
        table.calls th { font-size: 12px; color: #6b7280; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; font-size: 13px; color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1 style="margin:0 0 8px;">Recorder Analytics Report</h1>
        <div class="meta">{{ $data['domain_name'] ?? 'Domain' }}</div>
        <div class="meta">{{ $data['period_label'] ?? '' }}</div>
        <div class="meta">Generated {{ $data['generated_at'] ?? '' }}</div>
    </div>

    @php($summary = $data['summary'] ?? [])
    <table class="cards">
        <tr>
            <td>
                <div class="label">Total Calls</div>
                <div class="value">{{ $summary['total_calls'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Total Duration</div>
                <div class="value">{{ $summary['total_duration'] ?? '00:00:00' }}</div>
            </td>
            <td>
                <div class="label">Average Duration</div>
                <div class="value">{{ $summary['average_duration'] ?? '00:00:00' }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td>
                <div class="label">Transcribed</div>
                <div class="value">{{ $summary['transcribed_count'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Summarized</div>
                <div class="value">{{ $summary['summarized_count'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Sentiment</div>
                <div class="sentiment" style="margin-top:8px;">
                    @php($sentiment = $summary['sentiment'] ?? [])
                    <span>Positive: {{ $sentiment['positive'] ?? 0 }}</span>
                    <span>Neutral: {{ $sentiment['neutral'] ?? 0 }}</span>
                    <span>Negative: {{ $sentiment['negative'] ?? 0 }}</span>
                    <span>Unknown: {{ $sentiment['unknown'] ?? 0 }}</span>
                </div>
            </td>
        </tr>
    </table>

    @php($callsByDay = $data['calls_by_day'] ?? [])
    @if(!empty($callsByDay))
        <div class="section-title">Calls Per Day</div>
        <table class="calls">
            <thead>
            <tr>
                <th>Date</th>
                <th>Calls</th>
            </tr>
            </thead>
            <tbody>
            @foreach($callsByDay as $row)
                <tr>
                    <td>{{ $row['date'] ?? '' }}</td>
                    <td>{{ $row['count'] ?? 0 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @php($statusBreakdown = $data['status_breakdown'] ?? [])
    @if(!empty($statusBreakdown))
        <div class="section-title">Call Status</div>
        <div class="sentiment">
            @foreach($statusBreakdown as $row)
                <span>{{ ucwords(str_replace('_', ' ', $row['status'] ?? 'unknown')) }}: {{ $row['count'] ?? 0 }}</span>
            @endforeach
        </div>
    @endif

    @php($topTopics = $data['top_topics'] ?? [])
    @if(!empty($topTopics))
        <div class="section-title">Top Topics</div>
        <ol style="margin:0; padding-left:20px;">
            @foreach($topTopics as $topic)
                <li>{{ $topic['label'] ?? '' }} ({{ $topic['count'] ?? 0 }})</li>
            @endforeach
        </ol>
    @endif

    <div class="section-title">Recorded Calls</div>
    @php($emailCalls = array_slice($data['calls'] ?? [], 0, 25))
    @if(empty($emailCalls))
        <p class="muted">No recorder calls were found for this period.</p>
    @else
        <p class="muted">Showing up to 25 calls below. The attached CSV includes the full list.</p>
        <table class="calls">
            <thead>
            <tr>
                <th>Date</th>
                <th>Caller</th>
                <th>Dialed</th>
                <th>Duration</th>
                <th>Sentiment</th>
                <th>Summary</th>
            </tr>
            </thead>
            <tbody>
            @foreach($emailCalls as $call)
                <tr>
                    <td>{{ $call['date'] ?? '' }}<br><span class="muted">{{ $call['time'] ?? '' }}</span></td>
                    <td>{{ $call['caller'] ?? '—' }}</td>
                    <td>{{ $call['dialed'] ?? '—' }}</td>
                    <td>{{ $call['duration'] ?? '—' }}</td>
                    <td>{{ $call['sentiment'] ?? '—' }}</td>
                    <td>{{ $call['summary'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <a href="{{ $data['recorder_url'] ?? url('/recorder') }}">Open Recorder</a>
    </div>
</div>
</body>
</html>
