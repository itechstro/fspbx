<?php

namespace App\Console\Commands;

use App\Models\CallTranscription;
use App\Models\Domain;
use App\Services\CallTranscriptionCostService;
use App\Services\DomainUsageService;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTranscriptionCosts extends Command
{
    protected $signature = 'transcription:backfill-costs
        {--domain= : Limit to a domain UUID or name}
        {--force : Recompute rows that already have cost data}
        {--rebuild-ledger : Rebuild domain_usage_ledger AI metrics from backfilled rows}
        {--dry-run : Preview without writing}
        {--skip-openai : Skip summary/translation backfill that requires OpenAI API lookups}';

    protected $description = 'Backfill per-call AI cost fields from stored payloads and OpenAI response IDs';

    public function handle(
        CallTranscriptionCostService $costService,
        DomainUsageService $domainUsageService,
        OpenAIService $openAIService,
    ): int {
        $domainUuid = $this->resolveDomainUuid($this->option('domain'));
        $force = (bool) $this->option('force');
        $recordUsage = ! $this->option('rebuild-ledger');

        if ($this->option('rebuild-ledger') && ! $this->option('dry-run')) {
            DB::table('domain_usage_ledger')
                ->whereIn('metric', [
                    'ai_transcription_seconds',
                    'ai_summary_count',
                    'ai_translation_count',
                    'ai_spend_usd',
                ])
                ->when($domainUuid, fn ($q) => $q->where('domain_uuid', $domainUuid))
                ->delete();
        }

        $transcriptionStats = $this->backfillTranscriptionCosts($costService, $domainUuid, $force, $recordUsage);
        $summaryStats = $this->backfillSummaryCosts($costService, $openAIService, $domainUuid, $force, $recordUsage);
        $translationStats = $this->backfillTranslationCosts($costService, $openAIService, $domainUuid, $force, $recordUsage);

        if ($this->option('rebuild-ledger') && ! $this->option('dry-run')) {
            $this->rebuildLedgerFromRows($domainUsageService, $domainUuid);
        }

        $this->info(sprintf(
            'Backfill complete. Transcription updated %d (skipped %d). Summary updated %d (skipped %d, failed %d). Translation updated %d (skipped %d, failed %d).',
            $transcriptionStats['updated'],
            $transcriptionStats['skipped'],
            $summaryStats['updated'],
            $summaryStats['skipped'],
            $summaryStats['failed'],
            $translationStats['updated'],
            $translationStats['skipped'],
            $translationStats['failed'],
        ));

        return self::SUCCESS;
    }

    protected function backfillTranscriptionCosts(
        CallTranscriptionCostService $costService,
        ?string $domainUuid,
        bool $force,
        bool $recordUsage,
    ): array {
        $query = CallTranscription::query()
            ->where('status', 'completed')
            ->whereNotNull('result_payload');

        if ($domainUuid) {
            $query->where('domain_uuid', $domainUuid);
        }

        if (! $force) {
            $query->where(function ($builder) {
                $builder->whereNull('transcription_cost_usd')
                    ->orWhere('transcription_cost_usd', '<=', 0);
            });
        }

        $updated = 0;
        $skipped = 0;

        foreach ($query->get() as $row) {
            $payload = (array) ($row->result_payload ?? []);
            if (empty($payload['audio_duration']) && empty($payload['text'])) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("DRY RUN transcription: {$row->uuid} duration=" . data_get($payload, 'audio_duration', 'n/a'));
                $updated++;
                continue;
            }

            $costService->applyTranscriptionCompletion($row, $payload, $force, $recordUsage);
            $updated++;
        }

        return compact('updated', 'skipped');
    }

    protected function backfillSummaryCosts(
        CallTranscriptionCostService $costService,
        OpenAIService $openAIService,
        ?string $domainUuid,
        bool $force,
        bool $recordUsage,
    ): array {
        if ($this->option('skip-openai') || ! $openAIService->isConfigured()) {
            if (! $this->option('skip-openai') && ! $openAIService->isConfigured()) {
                $this->warn('OpenAI is not configured; skipping summary cost backfill.');
            }

            return ['updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $query = CallTranscription::query()
            ->where('summary_status', 'completed')
            ->whereNotNull('summary_external_id');

        if ($domainUuid) {
            $query->where('domain_uuid', $domainUuid);
        }

        if (! $force) {
            $query->where(function ($builder) {
                $builder->whereNull('summary_cost_usd')
                    ->orWhere('summary_cost_usd', '<=', 0);
            });
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->get() as $row) {
            if ($this->option('dry-run')) {
                $this->line("DRY RUN summary: {$row->uuid} response={$row->summary_external_id}");
                $updated++;
                continue;
            }

            try {
                $retrieved = $openAIService->retrieveResponseById((string) $row->summary_external_id);
                $usage = (array) ($retrieved['usage'] ?? []);
                if ($usage === []) {
                    $skipped++;
                    continue;
                }

                $costService->applySummaryCompletion(
                    $row,
                    (string) ($retrieved['model'] ?? $row->summary_model ?? 'gpt-5-nano'),
                    $usage,
                    $force,
                    $recordUsage,
                );
                $updated++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Summary backfill failed for {$row->uuid}: {$exception->getMessage()}");
            }
        }

        return compact('updated', 'skipped', 'failed');
    }

    protected function backfillTranslationCosts(
        CallTranscriptionCostService $costService,
        OpenAIService $openAIService,
        ?string $domainUuid,
        bool $force,
        bool $recordUsage,
    ): array {
        if ($this->option('skip-openai') || ! $openAIService->isConfigured()) {
            if (! $this->option('skip-openai') && ! $openAIService->isConfigured()) {
                $this->warn('OpenAI is not configured; skipping translation cost backfill.');
            }

            return ['updated' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $query = CallTranscription::query()
            ->where('translation_status', 'completed')
            ->whereNotNull('translation_external_id');

        if ($domainUuid) {
            $query->where('domain_uuid', $domainUuid);
        }

        if (! $force) {
            $query->where(function ($builder) {
                $builder->whereNull('translation_cost_usd')
                    ->orWhere('translation_cost_usd', '<=', 0);
            });
        }

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->get() as $row) {
            if ($this->option('dry-run')) {
                $this->line("DRY RUN translation: {$row->uuid} response={$row->translation_external_id}");
                $updated++;
                continue;
            }

            try {
                $retrieved = $openAIService->retrieveResponseById((string) $row->translation_external_id);
                $usage = (array) ($retrieved['usage'] ?? []);
                if ($usage === []) {
                    $skipped++;
                    continue;
                }

                $costService->applyTranslationCompletion(
                    $row,
                    (string) ($retrieved['model'] ?? $row->translation_model ?? 'gpt-4.1-mini'),
                    $usage,
                    $force,
                    $recordUsage,
                );
                $updated++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn("Translation backfill failed for {$row->uuid}: {$exception->getMessage()}");
            }
        }

        return compact('updated', 'skipped', 'failed');
    }

    protected function rebuildLedgerFromRows(DomainUsageService $domainUsageService, ?string $domainUuid): void
    {
        $query = CallTranscription::query()->where('status', 'completed');
        if ($domainUuid) {
            $query->where('domain_uuid', $domainUuid);
        }

        foreach ($query->get() as $row) {
            if ((float) ($row->transcription_cost_usd ?? 0) > 0) {
                $domainUsageService->recordTranscriptionUsage(
                    $row->domain_uuid,
                    (int) ($row->transcription_audio_duration_seconds ?? 0),
                    (float) $row->transcription_cost_usd,
                    ['xml_cdr_uuid' => $row->xml_cdr_uuid, 'backfill' => true]
                );
            }

            if ((float) ($row->summary_cost_usd ?? 0) > 0) {
                $domainUsageService->recordSummaryUsage(
                    $row->domain_uuid,
                    (float) $row->summary_cost_usd,
                    ['xml_cdr_uuid' => $row->xml_cdr_uuid, 'backfill' => true]
                );
            }

            if ((float) ($row->translation_cost_usd ?? 0) > 0) {
                $domainUsageService->recordTranslationUsage(
                    $row->domain_uuid,
                    (float) $row->translation_cost_usd,
                    ['xml_cdr_uuid' => $row->xml_cdr_uuid, 'backfill' => true]
                );
            }
        }

        $this->info('Rebuilt domain usage ledger from transcription rows.');
    }

    protected function resolveDomainUuid(?string $domain): ?string
    {
        $domain = trim((string) $domain);
        if ($domain === '') {
            return null;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $domain)) {
            return $domain;
        }

        return Domain::query()
            ->where('domain_name', $domain)
            ->value('domain_uuid');
    }
}
