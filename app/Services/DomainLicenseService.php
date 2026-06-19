<?php

namespace App\Services;

use App\Models\CallTranscription;
use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use App\Models\RecorderAnalyticsExecutiveSummaryRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DomainLicenseService
{
    public function __construct(
        protected DomainUsageService $domainUsageService,
        protected DomainLimitsService $domainLimitsService,
        protected CdrDataService $cdrDataService,
    ) {
    }

    public function buildPageData(string $domainUuid, ?string $period = null): array
    {
        $period ??= $this->domainUsageService->currentPeriod($domainUuid);

        return [
            'domain_uuid' => $domainUuid,
            'period' => $period,
            'usage' => $this->domainUsageService->buildSummary($domainUuid, $period),
            'ai_costs' => $this->domainUsageService->buildAiCostSummary($domainUuid, $period),
            'limits' => $this->buildLimitRows($domainUuid, $period),
        ];
    }

    public function buildLimitRows(string $domainUuid, ?string $period = null): array
    {
        $period ??= $this->domainUsageService->currentPeriod($domainUuid);
        $rows = [];

        foreach ($this->limitKeys() as $limitKey => $meta) {
            $rows[] = $this->buildLimitRow($domainUuid, $limitKey, $meta, $period);
        }

        return $rows;
    }

    public function buildUsageDetailsSummary(string $domainUuid, ?string $period = null): array
    {
        $period ??= $this->domainUsageService->currentPeriod($domainUuid);

        return $this->domainUsageService->buildAiCostSummary($domainUuid, $period);
    }

    public function usageDetailsQuery(string $domainUuid, string $period)
    {
        [$startUtc, $endUtc] = $this->periodBoundsUtc($domainUuid, $period);

        return CallTranscription::query()
            ->where('domain_uuid', $domainUuid)
            ->where('status', 'completed')
            ->where(function ($query) use ($startUtc, $endUtc) {
                $query->whereBetween('completed_at', [$startUtc, $endUtc])
                    ->orWhere(function ($fallback) use ($startUtc, $endUtc) {
                        $fallback->whereNull('completed_at')
                            ->whereBetween('created_at', [$startUtc, $endUtc]);
                    });
            })
            ->with([
                'cdr:xml_cdr_uuid,caller_id_name,caller_id_number,caller_destination,destination_number,start_epoch,duration,status',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at');
    }

    public function buildUsageDetailsCsvContent(string $domainUuid, ?string $period = null): string
    {
        $period ??= $this->domainUsageService->currentPeriod($domainUuid);
        $rows = $this->usageDetailsQuery($domainUuid, $period)
            ->get()
            ->map(fn (CallTranscription $row) => $this->mapUsageDetailRow($row, $domainUuid));
        $executiveSummaries = $this->executiveSummaryRunsForPeriod($domainUuid, $period);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Record Type',
            'Date',
            'Time',
            'Source',
            'Caller',
            'Destination',
            'Duration',
            'Call Status',
            'Transcription Cost (USD)',
            'Summary Cost (USD)',
            'Translation Cost (USD)',
            'Total AI Cost (USD)',
            'Model',
            'Input Tokens',
            'Output Tokens',
            'Total Tokens',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                'Call',
                $row['date'] ?? '',
                $row['time'] ?? '',
                '',
                $row['caller'] ?? '',
                $row['destination'] ?? '',
                $row['duration'] ?? '',
                $row['call_status'] ?? '',
                $this->formatCsvCost($row['transcription_cost_usd'] ?? null),
                $this->formatCsvCost($row['summary_cost_usd'] ?? null),
                $this->formatCsvCost($row['translation_cost_usd'] ?? null),
                $this->formatCsvCost($row['total_ai_cost_usd'] ?? null),
                trim(implode(' / ', array_filter([
                    $row['speech_model'] ? 'T: '.$row['speech_model'] : null,
                    $row['summary_model'] ? 'S: '.$row['summary_model'] : null,
                    $row['translation_model'] ? 'X: '.$row['translation_model'] : null,
                ]))),
                '',
                '',
                '',
            ]);
        }

        foreach ($executiveSummaries as $row) {
            fputcsv($handle, [
                'Executive Summary',
                $row['date'] ?? '',
                $row['time'] ?? '',
                $row['source_label'] ?? '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $this->formatCsvCost($row['estimated_cost_usd'] ?? null),
                $row['model'] ?? '',
                $row['input_tokens'] ?? '',
                $row['output_tokens'] ?? '',
                $row['total_tokens'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    public function usageDetailsCsvFilename(string $domainUuid, ?string $period = null): string
    {
        $period ??= $this->domainUsageService->currentPeriod($domainUuid);

        return sprintf('ai-usage-details-%s-%s.csv', $period, substr($domainUuid, 0, 8));
    }

    protected function formatCsvCost(?float $cost): string
    {
        return $cost !== null && $cost > 0 ? (string) round($cost, 6) : '';
    }

    public function executiveSummaryRunsForPeriod(string $domainUuid, string $period): array
    {
        [$startUtc, $endUtc] = $this->periodBoundsUtc($domainUuid, $period);
        $timezone = get_local_time_zone($domainUuid);

        return RecorderAnalyticsExecutiveSummaryRun::query()
            ->where('domain_uuid', $domainUuid)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RecorderAnalyticsExecutiveSummaryRun $run) => $this->mapExecutiveSummaryRunRow($run, $timezone))
            ->all();
    }

    public function mapExecutiveSummaryRunRow(RecorderAnalyticsExecutiveSummaryRun $run, string $timezone): array
    {
        $created = $run->created_at?->copy()->timezone($timezone);

        return [
            'uuid' => $run->uuid,
            'date' => $created?->format('Y-m-d'),
            'time' => $created?->format('H:i:s'),
            'source' => $run->source,
            'source_label' => $this->executiveSummarySourceLabel($run->source),
            'model' => $run->model,
            'input_tokens' => $run->input_tokens,
            'output_tokens' => $run->output_tokens,
            'total_tokens' => $run->total_tokens,
            'estimated_cost_usd' => round((float) $run->estimated_cost_usd, 6),
        ];
    }

    protected function executiveSummarySourceLabel(?string $source): string
    {
        return match ($source) {
            'call_history_api' => 'Call History Analytics',
            'scheduled_email' => 'Scheduled report',
            'api' => 'Recorder Analytics',
            default => ucwords(str_replace('_', ' ', (string) $source)),
        };
    }

    public function mapUsageDetailRow(CallTranscription $row, string $domainUuid): array
    {
        $timezone = get_local_time_zone($domainUuid);
        $cdr = $row->cdr;
        $timestamp = $cdr?->start_epoch
            ? Carbon::createFromTimestamp((int) $cdr->start_epoch, 'UTC')->timezone($timezone)
            : ($row->completed_at?->copy()->timezone($timezone) ?? $row->created_at?->copy()->timezone($timezone));

        $transcriptionCost = (float) ($row->transcription_cost_usd ?? 0);
        $summaryCost = (float) ($row->summary_cost_usd ?? 0);
        $translationCost = (float) ($row->translation_cost_usd ?? 0);
        $totalCost = (float) ($row->total_ai_cost_usd ?? 0);

        if ($totalCost <= 0) {
            $totalCost = round($transcriptionCost + $summaryCost + $translationCost, 6);
        }

        return [
            'uuid' => $row->uuid,
            'xml_cdr_uuid' => $row->xml_cdr_uuid,
            'date' => $timestamp?->format('Y-m-d'),
            'time' => $timestamp?->format('H:i:s'),
            'caller' => trim((string) ($cdr?->caller_id_name ?: $cdr?->caller_id_number ?: '')) ?: null,
            'destination' => trim((string) ($cdr?->caller_destination ?: $cdr?->destination_number ?: '')) ?: null,
            'duration' => $cdr ? $this->cdrDataService->getFormattedDuration((int) $cdr->duration) : null,
            'call_status' => $cdr?->status,
            'transcription_cost_usd' => round($transcriptionCost, 6),
            'summary_cost_usd' => round($summaryCost, 6),
            'translation_cost_usd' => round($translationCost, 6),
            'total_ai_cost_usd' => round($totalCost, 6),
            'summary_status' => $row->summary_status,
            'translation_status' => $row->translation_status,
            'transcription_duration_seconds' => (int) ($row->transcription_audio_duration_seconds ?? 0),
            'speech_model' => $row->transcription_speech_model,
            'summary_model' => $row->summary_model,
            'translation_model' => $row->translation_model,
        ];
    }

    protected function periodBoundsUtc(string $domainUuid, string $period): array
    {
        $timezone = get_local_time_zone($domainUuid);
        $start = Carbon::createFromFormat('Y-m', $period, $timezone)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start->copy()->utc(), $end->copy()->utc()];
    }

    public function updateLimit(
        string $domainUuid,
        string $limitKey,
        bool $enabled,
        ?string $value,
    ): void {
        if (! $this->domainLimitsService->metric($limitKey)) {
            throw new \InvalidArgumentException("Unknown limit key: {$limitKey}");
        }

        $existing = DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'limit')
            ->where('domain_setting_subcategory', $limitKey)
            ->first();

        if (! $enabled) {
            if ($existing) {
                $existing->update([
                    'domain_setting_enabled' => 'false',
                ]);
            }

            return;
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            throw new \InvalidArgumentException('Limit value must be numeric when enabled.');
        }

        $default = $this->defaultLimitRow($limitKey);

        DomainSettings::query()->updateOrCreate(
            [
                'domain_uuid' => $domainUuid,
                'domain_setting_category' => 'limit',
                'domain_setting_subcategory' => $limitKey,
            ],
            [
                'domain_setting_name' => 'numeric',
                'domain_setting_value' => (string) $value,
                'domain_setting_order' => $default?->default_setting_order,
                'domain_setting_enabled' => 'true',
                'domain_setting_description' => (string) ($default?->default_setting_description ?? ''),
            ]
        );
    }

    public function revertLimit(string $domainUuid, string $limitKey): void
    {
        DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'limit')
            ->where('domain_setting_subcategory', $limitKey)
            ->delete();
    }

    protected function buildLimitRow(string $domainUuid, string $limitKey, array $meta, string $period): array
    {
        $default = $this->defaultLimitRow($limitKey);
        $override = DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'limit')
            ->where('domain_setting_subcategory', $limitKey)
            ->first();

        $effectiveLimit = $this->domainUsageService->getLimit($limitKey, $domainUuid);
        $usage = $this->domainLimitsService->resolveUsage($limitKey, $domainUuid, $period, $this->domainUsageService);
        $scale = (float) ($meta['scale'] ?? 1);
        $usageDisplay = $scale > 0 ? $usage / $scale : $usage;

        return [
            'key' => $limitKey,
            'group' => (string) ($meta['group'] ?? 'Other limits'),
            'monthly' => $this->domainLimitsService->isMonthly($limitKey),
            'label' => (string) ($meta['display'] ?? $limitKey),
            'unit' => (string) ($meta['unit'] ?? ''),
            'description' => (string) ($default?->default_setting_description ?? ''),
            'default_value' => $default?->default_setting_value,
            'default_enabled' => filter_var($default?->default_setting_enabled, FILTER_VALIDATE_BOOLEAN),
            'override_value' => $override?->domain_setting_value,
            'override_enabled' => $override
                ? filter_var($override->domain_setting_enabled, FILTER_VALIDATE_BOOLEAN)
                : null,
            'inherited_from_default' => ! $override && filter_var($default?->default_setting_enabled, FILTER_VALIDATE_BOOLEAN),
            'effective_limit' => $effectiveLimit,
            'unlimited' => $effectiveLimit === null,
            'usage' => round($usageDisplay, 4),
            'usage_raw' => round($usage, 4),
            'remaining' => $effectiveLimit === null
                ? null
                : max(0, round($effectiveLimit - $usageDisplay, 4)),
        ];
    }

    protected function limitKeys(): array
    {
        return collect($this->domainLimitsService->metrics())
            ->filter(fn (array $meta) => ($meta['usage_type'] ?? '') !== 'unknown')
            ->all();
    }

    protected function defaultLimitRow(string $limitKey): ?DefaultSettings
    {
        return DefaultSettings::query()
            ->where('default_setting_category', 'limit')
            ->where('default_setting_subcategory', $limitKey)
            ->first();
    }
}
