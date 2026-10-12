{{-- email-template
version: 1.0.0
language: zh-tw
category: recorder
subcategory: analytics-report
format: html
layout: none
subject: {{ $email_subject }}
description: 錄音分析電子郵件報告
--}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>錄音分析報告</title>
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
        <h1 style="margin:0 0 8px;">錄音分析報告</h1>
        <div class="meta">{{ $data['domain_name'] ?? '帳戶' }}</div>
        <div class="meta">{{ $data['period_label'] ?? '' }}</div>
        <div class="meta">產生時間 {{ $data['generated_at'] ?? '' }}</div>
    </div>

    <table class="cards">
        <tr>
            <td>
                <div class="label">通話總數</div>
                <div class="value">{{ $data['summary']['total_calls'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">總時長</div>
                <div class="value">{{ $data['summary']['total_duration'] ?? '00:00:00' }}</div>
            </td>
            <td>
                <div class="label">平均時長</div>
                <div class="value">{{ $data['summary']['average_duration'] ?? '00:00:00' }}</div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td>
                <div class="label">已轉寫</div>
                <div class="value">{{ $data['summary']['transcribed_count'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">已摘要</div>
                <div class="value">{{ $data['summary']['summarized_count'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">情緒</div>
                <div class="sentiment" style="margin-top:8px;">
                    <span>正面：{{ $data['summary']['sentiment']['positive'] ?? 0 }}</span>
                    <span>中性：{{ $data['summary']['sentiment']['neutral'] ?? 0 }}</span>
                    <span>負面：{{ $data['summary']['sentiment']['negative'] ?? 0 }}</span>
                    <span>未知：{{ $data['summary']['sentiment']['unknown'] ?? 0 }}</span>
                </div>
            </td>
        </tr>
    </table>

    @if(!empty($data['calls_by_day']))
        <div class="section-title">每日通話數</div>
        <table class="calls">
            <thead>
            <tr>
                <th>日期</th>
                <th>通話</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['calls_by_day'] as $row)
                <tr>
                    <td>{{ $row['date'] ?? '' }}</td>
                    <td>{{ $row['count'] ?? 0 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($data['transcription_status_breakdown']))
        <div class="section-title">轉寫狀態</div>
        <div class="sentiment">
            @foreach($data['transcription_status_breakdown'] as $row)
                <span>{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}</span>
            @endforeach
        </div>
    @endif

    @if(!empty($data['summary_status_breakdown']))
        <div class="section-title">摘要狀態</div>
        <div class="sentiment">
            @foreach($data['summary_status_breakdown'] as $row)
                <span>{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}</span>
            @endforeach
        </div>
    @endif

    @if(!empty($data['top_topics']))
        <div class="section-title">熱門主題</div>
        <ol style="margin:0; padding-left:20px;">
            @foreach($data['top_topics'] as $topic)
                <li>{{ $topic['label'] ?? '' }} ({{ $topic['count'] ?? 0 }})</li>
            @endforeach
        </ol>
    @endif

    @if(!empty($data['executive_summary']))
        <div class="section-title">AI 執行摘要</div>
        @if(!empty($data['executive_summary']['overview']))
            <p>{{ $data['executive_summary']['overview'] }}</p>
        @endif
        @if(!empty($data['executive_summary']['highlights']))
            <p><strong>重點</strong></p>
            <ul style="margin-top:0; padding-left:20px;">
                @foreach($data['executive_summary']['highlights'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
        @if(!empty($data['executive_summary']['concerns']))
            <p><strong>注意事項</strong></p>
            <ul style="margin-top:0; padding-left:20px;">
                @foreach($data['executive_summary']['concerns'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
        @if(!empty($data['executive_summary']['recommendations']))
            <p><strong>建議</strong></p>
            <ul style="margin-top:0; padding-left:20px;">
                @foreach($data['executive_summary']['recommendations'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @endif
    @elseif(!empty($data['executive_summary_error']))
        <div class="section-title">AI 執行摘要</div>
        <p class="muted">未包含執行摘要： {{ $data['executive_summary_error'] }}</p>
    @endif

    <div class="section-title">錄音通話</div>
    @if(empty($data['template_calls']))
        <p class="muted">此期間沒有找到錄音通話。</p>
    @else
        <p class="muted">下列最多顯示 25 通。完整清單請見附加的 CSV。</p>
        <table class="calls">
            <thead>
            <tr>
                <th>日期</th>
                <th>來電者</th>
                <th>撥打號碼</th>
                <th>時長</th>
                <th>情緒</th>
                <th>摘要</th>
            </tr>
            </thead>
            <tbody>
            @foreach($data['template_calls'] as $call)
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
        <a href="{{ $data['recorder_url'] ?? '#' }}">開啟錄音</a>
    </div>
</div>
</body>
</html>
