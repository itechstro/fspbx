<?php

namespace App\Services;

use App\Jobs\SendDomainAiUsageLimitAlert;
use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\DomainUsageLimitAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class DomainUsageLimitAlertService
{
    public function __construct(
        protected DomainUsageService $domainUsageService,
        protected DomainLimitsService $domainLimitsService,
    ) {
    }

    public function evaluateAiLimits(?string $domainUuid, ?string $period = null): void
    {
        if (! $domainUuid) {
            return;
        }

        $period ??= $this->domainUsageService->currentPeriod($domainUuid);

        foreach ($this->aiLimitKeys() as $limitKey) {
            $this->evaluateLimit($domainUuid, $limitKey, $period);
        }
    }

    public function evaluateLimit(string $domainUuid, string $limitKey, ?string $period = null): void
    {
        $period ??= $this->domainUsageService->currentPeriod($domainUuid);
        $snapshot = $this->buildLimitSnapshot($domainUuid, $limitKey, $period);

        if ($snapshot === null) {
            return;
        }

        if ($snapshot['usage_display'] >= $snapshot['limit']) {
            $this->queueAlertIfNew($domainUuid, $limitKey, 'reached', $period, $snapshot);

            return;
        }

        $approachingPercent = $this->approachingPercent($domainUuid);
        if ($snapshot['percent_used'] >= $approachingPercent) {
            $this->queueAlertIfNew($domainUuid, $limitKey, 'approaching', $period, $snapshot);
        }
    }

    public function notifyLimitBlocked(
        string $domainUuid,
        string $limitKey,
        float $currentUsage,
        float $limitAmount,
    ): void {
        $period = $this->domainUsageService->currentPeriod($domainUuid);
        $snapshot = $this->buildLimitSnapshot($domainUuid, $limitKey, $period);

        if ($snapshot === null) {
            $meta = $this->domainLimitsService->metric($limitKey) ?? [];
            $scale = (float) ($meta['scale'] ?? 1);
            $snapshot = [
                'label' => (string) ($meta['display'] ?? $limitKey),
                'unit' => (string) ($meta['unit'] ?? ''),
                'limit' => $scale > 0 ? $limitAmount / $scale : $limitAmount,
                'usage_display' => $scale > 0 ? $currentUsage / $scale : $currentUsage,
                'remaining' => 0,
                'percent_used' => 100,
                'period' => $period,
            ];
        }

        $this->queueAlertIfNew($domainUuid, $limitKey, 'reached', $period, $snapshot);
    }

    public function resolveAlertEmails(?string $domainUuid): array
    {
        $configured = trim((string) $this->getAlertEmailSetting($domainUuid));
        if ($configured !== '') {
            return $this->parseEmailList($configured);
        }

        $support = DefaultSettings::query()
            ->where('default_setting_category', 'email')
            ->where('default_setting_subcategory', 'support_email')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');

        if ($support) {
            return $this->parseEmailList((string) $support);
        }

        return [];
    }

    public function getAlertEmailSetting(?string $domainUuid): ?string
    {
        if ($domainUuid) {
            $domainRow = DomainSettings::query()
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'limit')
                ->where('domain_setting_subcategory', 'ai_usage_alert_email')
                ->where('domain_setting_enabled', 'true')
                ->value('domain_setting_value');

            if ($domainRow !== null && trim((string) $domainRow) !== '') {
                return trim((string) $domainRow);
            }
        }

        $default = DefaultSettings::query()
            ->where('default_setting_category', 'limit')
            ->where('default_setting_subcategory', 'ai_usage_alert_email')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');

        return $default !== null ? trim((string) $default) : null;
    }

    public function updateAlertEmail(string $domainUuid, ?string $email): void
    {
        $email = trim((string) $email);
        $default = DefaultSettings::query()
            ->where('default_setting_category', 'limit')
            ->where('default_setting_subcategory', 'ai_usage_alert_email')
            ->first();

        if ($email === '') {
            DomainSettings::query()
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'limit')
                ->where('domain_setting_subcategory', 'ai_usage_alert_email')
                ->delete();

            return;
        }

        DomainSettings::query()->updateOrCreate(
            [
                'domain_uuid' => $domainUuid,
                'domain_setting_category' => 'limit',
                'domain_setting_subcategory' => 'ai_usage_alert_email',
            ],
            [
                'domain_setting_name' => 'text',
                'domain_setting_value' => $email,
                'domain_setting_order' => $default?->default_setting_order,
                'domain_setting_enabled' => 'true',
                'domain_setting_description' => (string) ($default?->default_setting_description ?? ''),
            ]
        );
    }

    protected function queueAlertIfNew(
        string $domainUuid,
        string $limitKey,
        string $alertLevel,
        string $period,
        array $snapshot,
    ): void {
        $created = DomainUsageLimitAlert::query()->firstOrCreate(
            [
                'domain_uuid' => $domainUuid,
                'period' => $period,
                'limit_key' => $limitKey,
                'alert_level' => $alertLevel,
            ],
            [
                'sent_at' => now(),
            ]
        );

        if (! $created->wasRecentlyCreated) {
            return;
        }

        $emails = $this->resolveAlertEmails($domainUuid);
        if ($emails === []) {
            return;
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domainUuid)
            ->first(['domain_uuid', 'domain_name', 'domain_description']);

        SendDomainAiUsageLimitAlert::dispatch(
            $emails,
            [
                'domain_uuid' => $domainUuid,
                'domain_name' => $domain?->domain_description ?: $domain?->domain_name ?: $domainUuid,
                'period' => $period,
                'period_label' => $this->formatPeriodLabel($period, $domainUuid),
                'limit_key' => $limitKey,
                'limit_label' => $snapshot['label'],
                'unit' => $snapshot['unit'],
                'alert_level' => $alertLevel,
                'limit' => $snapshot['limit'],
                'usage' => $snapshot['usage_display'],
                'remaining' => $snapshot['remaining'],
                'percent_used' => $snapshot['percent_used'],
            ],
        )->onQueue('default');
    }

    public function sendTestAlert(string $domainUuid, string $email, string $alertLevel = 'approaching'): void
    {
        $alertLevel = $alertLevel === 'reached' ? 'reached' : 'approaching';
        $period = $this->domainUsageService->currentPeriod($domainUuid);
        $limitKey = 'ai_transcription_minutes';
        $meta = $this->domainLimitsService->metric($limitKey) ?? [];
        $limit = 1000.0;
        $approachingPercent = $this->approachingPercent($domainUuid);
        $usage = $alertLevel === 'reached'
            ? $limit
            : round($limit * ($approachingPercent / 100), 2);
        $remaining = max(0, round($limit - $usage, 2));
        $percentUsed = $limit > 0 ? round(($usage / $limit) * 100, 2) : 0;

        $domain = Domain::query()
            ->where('domain_uuid', $domainUuid)
            ->first(['domain_uuid', 'domain_name', 'domain_description']);

        $payload = [
            'domain_uuid' => $domainUuid,
            'domain_name' => $domain?->domain_description ?: $domain?->domain_name ?: $domainUuid,
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period, $domainUuid),
            'limit_key' => $limitKey,
            'limit_label' => (string) ($meta['display'] ?? 'AI transcription minutes'),
            'unit' => (string) ($meta['unit'] ?? 'minutes'),
            'alert_level' => $alertLevel,
            'limit' => $limit,
            'usage' => $usage,
            'remaining' => $remaining,
            'percent_used' => $percentUsed,
            'is_test' => true,
        ];

        Mail::to($email)->send(new \App\Mail\DomainAiUsageLimitAlert($payload));
    }

    protected function buildLimitSnapshot(string $domainUuid, string $limitKey, string $period): ?array
    {
        $meta = $this->domainLimitsService->metric($limitKey);
        if (! $meta || ($meta['group'] ?? '') !== 'AI services (monthly)') {
            return null;
        }

        $limit = $this->domainUsageService->getLimit($limitKey, $domainUuid);
        if ($limit === null) {
            return null;
        }

        $usage = $this->domainLimitsService->resolveUsage(
            $limitKey,
            $domainUuid,
            $period,
            $this->domainUsageService,
        );
        $scale = (float) ($meta['scale'] ?? 1);
        $usageDisplay = $scale > 0 ? $usage / $scale : $usage;
        $percentUsed = $limit > 0 ? ($usageDisplay / $limit) * 100 : 0;

        return [
            'label' => (string) ($meta['display'] ?? $limitKey),
            'unit' => (string) ($meta['unit'] ?? ''),
            'limit' => $limit,
            'usage_display' => round($usageDisplay, 4),
            'remaining' => max(0, round($limit - $usageDisplay, 4)),
            'percent_used' => round($percentUsed, 2),
            'period' => $period,
        ];
    }

    protected function aiLimitKeys(): array
    {
        return (array) config('ai_usage.limit_alerts.limit_keys', []);
    }

    protected function approachingPercent(?string $domainUuid): float
    {
        $value = get_domain_setting('ai_usage_alert_approaching_percent', $domainUuid);

        if ($value !== null && is_numeric($value)) {
            return max(1, min(100, (float) $value));
        }

        return 80.0;
    }

    protected function parseEmailList(string $value): array
    {
        return collect(preg_split('/[,\s;]+/', $value) ?: [])
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    protected function formatPeriodLabel(string $period, string $domainUuid): string
    {
        $timezone = get_local_time_zone($domainUuid);

        return Carbon::createFromFormat('Y-m', $period, $timezone)
            ->format('F Y');
    }
}
