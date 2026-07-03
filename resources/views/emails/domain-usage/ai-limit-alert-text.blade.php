@if(!empty($data['is_test']))
This is a test alert. Usage numbers below are sample data only.

@endif
AI usage alert for {{ $data['domain_name'] ?? 'tenant' }}
Period: {{ $data['period_label'] ?? $data['period'] ?? '' }}

@if(($data['alert_level'] ?? '') === 'reached')
LIMIT REACHED
@else
APPROACHING LIMIT
@endif

Service: {{ $data['limit_label'] ?? '' }}
Usage: {{ number_format((float) ($data['usage'] ?? 0), 2) }} / {{ number_format((float) ($data['limit'] ?? 0), 2) }} {{ $data['unit'] ?? '' }}
Remaining: {{ number_format((float) ($data['remaining'] ?? 0), 2) }} {{ $data['unit'] ?? '' }}
Used: {{ number_format((float) ($data['percent_used'] ?? 0), 1) }}%

@if(($data['alert_level'] ?? '') === 'reached')
New AI requests for this service may be blocked until usage resets or the limit is increased.
@else
Usage is nearing the monthly limit. Consider raising the tenant limit or reviewing AI activity.
@endif

Review usage in Domain License for this tenant.
