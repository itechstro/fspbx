{{-- email-template
format: text
layout: none
--}}
@if(!empty($data['is_test']))
這是測試警示。下列用量數字僅為範例資料。

@endif
{{ $data['domain_name'] ?? '租戶' }} 的 AI 用量警示
期間：{{ $data['period_label'] ?? $data['period'] ?? '' }}

{{ $data['badge_label'] }}

服務：{{ $data['limit_label'] ?? '' }}
用量：{{ $data['usage_formatted'] }} / {{ $data['limit_formatted'] }} {{ $data['unit'] ?? '' }}
剩餘：{{ $data['remaining_formatted'] }} {{ $data['unit'] ?? '' }}
已使用：{{ $data['percent_used_formatted'] }}%

@if(!empty($data['is_reached']))
在用量重置或提高上限之前，此服務的新 AI 請求可能會被阻擋。
@else
用量已接近每月上限。請考慮提高租戶上限或檢視 AI 活動。
@endif

請在此租戶的網域授權中檢視用量。
