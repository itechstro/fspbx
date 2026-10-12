{{-- email-template
format: text
layout: none
--}}
錄音分析報告
{{ $data['domain_name'] ?? '帳戶' }}
{{ $data['period_label'] ?? '' }}
產生時間 {{ $data['generated_at'] ?? '' }}

通話總數： {{ $data['summary']['total_calls'] ?? 0 }}
總時長： {{ $data['summary']['total_duration'] ?? '00:00:00' }}
平均時長： {{ $data['summary']['average_duration'] ?? '00:00:00' }}
已轉寫： {{ $data['summary']['transcribed_count'] ?? 0 }}
已摘要： {{ $data['summary']['summarized_count'] ?? 0 }}

情緒
正面：{{ $data['summary']['sentiment']['positive'] ?? 0 }}
中性：{{ $data['summary']['sentiment']['neutral'] ?? 0 }}
負面：{{ $data['summary']['sentiment']['negative'] ?? 0 }}
未知：{{ $data['summary']['sentiment']['unknown'] ?? 0 }}

每日通話數
@if(empty($data['calls_by_day']))
此期間沒有每日通話資料。
@else
@foreach($data['calls_by_day'] as $row)
{{ $row['date'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

轉寫狀態
@if(empty($data['transcription_status_breakdown']))
此期間沒有轉寫資料。
@else
@foreach($data['transcription_status_breakdown'] as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

摘要狀態
@if(empty($data['summary_status_breakdown']))
此期間沒有摘要資料。
@else
@foreach($data['summary_status_breakdown'] as $row)
{{ $row['label'] ?? '' }}: {{ $row['count'] ?? 0 }}
@endforeach
@endif

熱門主題
@if(empty($data['top_topics']))
此期間沒有摘要主題。
@else
@foreach($data['top_topics'] as $topic)
- {{ $topic['label'] ?? '' }} ({{ $topic['count'] ?? 0 }})
@endforeach
@endif

AI 執行摘要
@if(!empty($data['executive_summary']))
@if(!empty($data['executive_summary']['overview']))
{{ $data['executive_summary']['overview'] }}

@endif
@if(!empty($data['executive_summary']['highlights']))
重點：
@foreach($data['executive_summary']['highlights'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($data['executive_summary']['concerns']))
注意事項：
@foreach($data['executive_summary']['concerns'] as $item)
- {{ $item }}
@endforeach

@endif
@if(!empty($data['executive_summary']['recommendations']))
建議：
@foreach($data['executive_summary']['recommendations'] as $item)
- {{ $item }}
@endforeach

@endif
@elseif(!empty($data['executive_summary_error']))
未包含執行摘要： {{ $data['executive_summary_error'] }}

@endif

錄音通話
@if(empty($data['template_calls']))
此期間沒有找到錄音通話。
@else
下列最多顯示 25 通。完整清單請見附加的 CSV。

@foreach($data['template_calls'] as $call)
---
{{ $call['date'] ?? '' }} {{ $call['time'] ?? '' }}
來電者： {{ $call['caller'] ?? '—' }}
撥打號碼： {{ $call['dialed'] ?? '—' }}
時長： {{ $call['duration'] ?? '—' }}
情緒： {{ $call['sentiment'] ?? '—' }}
摘要： {{ $call['summary'] ?? '—' }}
@endforeach
@endif

開啟錄音: {{ $data['recorder_url'] ?? '' }}
