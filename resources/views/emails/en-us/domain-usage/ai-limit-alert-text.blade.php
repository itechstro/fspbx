{{-- email-template
format: text
layout: none
--}}
@if(!empty($data['is_test']))
This is a test alert. Usage numbers below are sample data only.

@endif
AI usage alert for {{ $data['domain_name'] ?? 'tenant' }}
Period: {{ $data['period_label'] ?? $data['period'] ?? '' }}

{{ $data['badge_label'] }}

Service: {{ $data['limit_label'] ?? '' }}
Usage: {{ $data['usage_formatted'] }} / {{ $data['limit_formatted'] }} {{ $data['unit'] ?? '' }}
Remaining: {{ $data['remaining_formatted'] }} {{ $data['unit'] ?? '' }}
Used: {{ $data['percent_used_formatted'] }}%

@if(!empty($data['is_reached']))
New AI requests for this service may be blocked until usage resets or the limit is increased.
@else
Usage is nearing the monthly limit. Consider raising the tenant limit or reviewing AI activity.
@endif

Review usage in Domain License for this tenant.
