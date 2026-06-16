<?php

namespace App\Services;

use App\Exceptions\DomainUsageLimitExceededException;
use App\Models\CDR;
use App\Models\DomainUsageLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DomainUsageService
{
    public function __construct(
        protected AiCostEstimationService $costEstimationService,
        protected DomainLimitsService $domainLimitsService,
    ) {
    }

    public function currentPeriod(?string $domainUuid = null): string
    {
        $timezone = $domainUuid ? get_local_time_zone($domainUuid) : config('app.timezone', 'UTC');

        return now($timezone)->format('Y-m');
    }

    public function getLimit(string $limitKey, ?string $domainUuid): ?float
    {
        $value = get_limit_setting($limitKey, $domainUuid);

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public function getLedgerMetric(string $limitKey): ?string
    {
        $meta = $this->domainLimitsService->metric($limitKey);

        return ($meta['usage_type'] ?? '') === 'ledger'
            ? (string) ($meta['ledger_metric'] ?? '')
            : null;
    }

    public function getUsage(string $metric, ?string $domainUuid, ?string $period = null): float
    {
        if (! $domainUuid) {
            return 0.0;
        }

        $period ??= $this->currentPeriod($domainUuid);

        if ($metric === 'outbound_seconds') {
            return $this->calculateOutboundSecondsForPeriod($domainUuid, $period);
        }

        $amount = DomainUsageLedger::query()
            ->where('domain_uuid', $domainUuid)
            ->where('period', $period)
            ->where('metric', $metric)
            ->value('amount');

        return (float) ($amount ?? 0);
    }

    public function recordUsage(
        string $metric,
        float $amount,
        ?string $domainUuid,
        ?string $period = null,
        array $metadata = [],
    ): void {
        if (! $domainUuid || $amount <= 0) {
            return;
        }

        $period ??= $this->currentPeriod($domainUuid);

        DB::transaction(function () use ($metric, $amount, $domainUuid, $period, $metadata) {
            $row = DomainUsageLedger::query()->firstOrCreate(
                [
                    'domain_uuid' => $domainUuid,
                    'period' => $period,
                    'metric' => $metric,
                ],
                ['amount' => 0]
            );

            $row->amount = round(((float) $row->amount) + $amount, 6);
            if ($metadata !== []) {
                $row->metadata = array_merge((array) ($row->metadata ?? []), $metadata);
            }
            $row->save();
        });
    }

    /**
     * @throws DomainUsageLimitExceededException
     */
    public function assertWithinLimit(string $limitKey, float $proposedAmount, ?string $domainUuid): void
    {
        $limit = $this->getLimit($limitKey, $domainUuid);
        if ($limit === null) {
            return;
        }

        $metric = $this->getLedgerMetric($limitKey);
        if (! $metric) {
            return;
        }

        $meta = $this->domainLimitsService->metric($limitKey) ?? [];
        $scale = (float) ($meta['scale'] ?? 1);
        $limitAmount = $limit * $scale;
        $currentUsage = $this->getUsage($metric, $domainUuid);

        if (($currentUsage + $proposedAmount) > $limitAmount) {
            throw new DomainUsageLimitExceededException(
                metric: $metric,
                limitKey: $limitKey,
                limitValue: $limitAmount,
                currentUsage: $currentUsage,
                proposedAmount: $proposedAmount,
            );
        }
    }

    public function buildSummary(?string $domainUuid, ?string $period = null): array
    {
        $period ??= $this->currentPeriod($domainUuid);
        $limits = [];

        foreach ($this->domainLimitsService->metrics() as $limitKey => $config) {
            if (($config['usage_type'] ?? '') === 'unknown') {
                continue;
            }

            $metric = (string) ($config['ledger_metric'] ?? '');
            $limit = $this->getLimit($limitKey, $domainUuid);
            $usage = $this->domainLimitsService->resolveUsage($limitKey, $domainUuid, $period, $this);
            $scale = (float) ($config['scale'] ?? 1);
            $usageDisplay = $scale > 0 ? $usage / $scale : $usage;

            $limits[] = [
                'key' => $limitKey,
                'group' => (string) ($config['group'] ?? ''),
                'metric' => $metric,
                'label' => (string) ($config['display'] ?? $limitKey),
                'unit' => (string) ($config['unit'] ?? ''),
                'monthly' => ($config['usage_type'] ?? '') === 'ledger',
                'limit' => $limit,
                'effective_limit' => $limit,
                'usage' => round($usageDisplay, 4),
                'usage_raw' => round($usage, 4),
                'remaining' => $limit === null ? null : max(0, round($limit - $usageDisplay, 4)),
                'unlimited' => $limit === null,
            ];
        }

        return [
            'domain_uuid' => $domainUuid,
            'period' => $period,
            'limits' => $limits,
        ];
    }

    public function recordTranscriptionUsage(
        ?string $domainUuid,
        int $durationSeconds,
        float $costUsd,
        array $metadata = [],
    ): void {
        $this->recordUsage('ai_transcription_seconds', (float) $durationSeconds, $domainUuid, null, $metadata);
        $this->recordUsage('ai_spend_usd', $costUsd, $domainUuid, null, $metadata);
    }

    public function recordSummaryUsage(?string $domainUuid, float $costUsd, array $metadata = []): void
    {
        $this->recordUsage('ai_summary_count', 1, $domainUuid, null, $metadata);
        $this->recordUsage('ai_spend_usd', $costUsd, $domainUuid, null, $metadata);
    }

    public function recordTranslationUsage(?string $domainUuid, float $costUsd, array $metadata = []): void
    {
        $this->recordUsage('ai_translation_count', 1, $domainUuid, null, $metadata);
        $this->recordUsage('ai_spend_usd', $costUsd, $domainUuid, null, $metadata);
    }

    public function recordExecutiveSummaryUsage(
        ?string $domainUuid,
        float $costUsd,
        array $metadata = [],
    ): void {
        $this->recordUsage('ai_executive_summary_count', 1, $domainUuid, null, $metadata);
        $this->recordUsage('ai_spend_usd', $costUsd, $domainUuid, null, $metadata);
    }

    public function estimateTranscriptionCostForCdr(string $xmlCdrUuid, array $requestPayload = []): array
    {
        $cdr = CDR::query()
            ->where('xml_cdr_uuid', $xmlCdrUuid)
            ->first(['duration', 'billsec', 'record_length']);

        $durationSeconds = max(
            1,
            (int) ($cdr?->record_length ?: $cdr?->billsec ?: $cdr?->duration ?: 0)
        );

        return $this->costEstimationService->estimateAssemblyAiTranscription(
            $durationSeconds,
            (string) data_get($requestPayload, 'speech_models.0'),
            $requestPayload,
        );
    }

    protected function calculateOutboundSecondsForPeriod(string $domainUuid, string $period): float
    {
        $timezone = get_local_time_zone($domainUuid);
        $start = Carbon::createFromFormat('Y-m', $period, $timezone)->startOfMonth()->utc();
        $end = $start->copy()->endOfMonth();

        return (float) CDR::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('direction', ['outbound', 'out'])
            ->whereBetween('start_epoch', [(string) $start->timestamp, (string) $end->timestamp])
            ->get(['billsec', 'duration'])
            ->sum(fn ($row) => max(0, (int) ($row->billsec ?: $row->duration ?: 0)));
    }

    public function buildAiCostSummary(?string $domainUuid, ?string $period = null): array
    {
        if (! $domainUuid) {
            return [
                'transcription_cost_usd' => 0,
                'summary_cost_usd' => 0,
                'translation_cost_usd' => 0,
                'executive_summary_cost_usd' => 0,
                'total_cost_usd' => 0,
                'transcription_count' => 0,
                'executive_summary_count' => 0,
            ];
        }

        $period ??= $this->currentPeriod($domainUuid);
        $timezone = get_local_time_zone($domainUuid);
        $start = Carbon::createFromFormat('Y-m', $period, $timezone)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = \App\Models\CallTranscription::query()
            ->where('domain_uuid', $domainUuid)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start->copy()->utc(), $end->copy()->utc()]);

        $callTotalCostUsd = round((float) (clone $query)->sum('total_ai_cost_usd'), 6);

        $executiveSummaryQuery = \App\Models\RecorderAnalyticsExecutiveSummaryRun::query()
            ->where('domain_uuid', $domainUuid)
            ->whereBetween('created_at', [$start->copy()->utc(), $end->copy()->utc()]);

        $executiveSummaryCostUsd = round((float) (clone $executiveSummaryQuery)->sum('estimated_cost_usd'), 6);

        return [
            'transcription_cost_usd' => round((float) (clone $query)->sum('transcription_cost_usd'), 6),
            'summary_cost_usd' => round((float) (clone $query)->sum('summary_cost_usd'), 6),
            'translation_cost_usd' => round((float) (clone $query)->sum('translation_cost_usd'), 6),
            'executive_summary_cost_usd' => $executiveSummaryCostUsd,
            'total_cost_usd' => round($callTotalCostUsd + $executiveSummaryCostUsd, 6),
            'transcription_count' => (int) (clone $query)->whereNotNull('transcription_cost_usd')->count(),
            'executive_summary_count' => (int) (clone $executiveSummaryQuery)->count(),
        ];
    }
}
