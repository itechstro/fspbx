<?php

namespace App\Services;

use App\Models\CallCenterQueues;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ContactCenterQueueService
{
    public function __construct(
        private BasicQueueService $basicQueueService,
    ) {}

    public function createShellQueue(): CallCenterQueues
    {
        $model = new CallCenterQueues();
        $extension = $model->generateUniqueSequenceNumber();

        if ($extension === null) {
            throw new RuntimeException('No queue extensions are available in the 9400-9449 range.');
        }

        return $this->basicQueueService->saveQueue([
            'queue_name' => 'New Contact Center',
            'queue_extension' => $extension,
            'queue_strategy' => 'ring-all',
            'queue_greeting' => null,
            'queue_moh_sound' => 'local_stream://default',
            'queue_max_wait_time' => 0,
            'queue_max_wait_time_with_no_agent' => 90,
            'queue_max_wait_time_with_no_agent_time_reached' => '5',
            'queue_tier_rules_apply' => 'false',
            'queue_time_base_score' => 'system',
            'queue_description' => null,
            'tiers' => [],
        ]);
    }

    public function saveFromContactCenterSettings(array $attributes, CallCenterQueues $queue): CallCenterQueues
    {
        return $this->basicQueueService->saveQueue(
            $this->mapSettingsToPayload($attributes),
            $queue,
        );
    }

    public function deleteQueue(CallCenterQueues $queue): void
    {
        $this->basicQueueService->deleteQueues(collect([$queue]));
    }

    private function mapSettingsToPayload(array $attributes): array
    {
        $domainName = session('domain_name');

        $payload = [
            'queue_name' => $attributes['queue_name'],
            'queue_extension' => (string) $attributes['queue_extension'],
            'queue_greeting' => $attributes['queue_greeting'] ?? null,
            'queue_description' => $attributes['queue_description'] ?? null,
            'queue_strategy' => $attributes['queue_strategy'],
            'queue_max_wait_time' => $attributes['queue_max_wait_time'] ?? 0,
            'queue_max_wait_time_with_no_agent' => $attributes['queue_max_wait_time_with_no_agent'] ?? 90,
            'queue_max_wait_time_with_no_agent_time_reached' => $attributes['queue_max_wait_time_with_no_agent_time_reached'] ?? '5',
            'queue_cc_exit_keys' => $attributes['queue_cc_exit_keys'] ?? null,
            'queue_timeout_action' => $attributes['queue_timeout_action'] ?? null,
            'queue_time_base_score' => $attributes['queue_time_base_score'] ?? 'system',
            'queue_time_base_score_sec' => $attributes['queue_time_base_score_sec'] ?? null,
            'queue_tier_rules_apply' => $attributes['queue_tier_rules_apply'] ?? 'false',
            'queue_tier_rule_wait_second' => $attributes['queue_tier_rule_wait_second'] ?? null,
            'queue_tier_rule_wait_multiply_level' => $attributes['queue_tier_rule_wait_multiply_level'] ?? 'false',
            'queue_tier_rule_no_agent_no_wait' => $attributes['queue_tier_rule_no_agent_no_wait'] ?? 'false',
            'queue_email_address' => $attributes['queue_email_address'] ?? null,
            'queue_discard_abandoned_after' => $attributes['queue_discard_abandoned_after'] ?? null,
            'queue_abandoned_resume_allowed' => $attributes['queue_abandoned_resume_allowed'] ?? 'false',
            'queue_cid_prefix' => $attributes['queue_cid_prefix'] ?? null,
            'queue_announce_frequency' => $attributes['queue_announce_frequency'] ?? null,
            'queue_announce_position' => $attributes['queue_announce_position'] ?? 'false',
            'queue_moh_sound' => $this->resolveMohSound($attributes['queue_moh_sound'] ?? null, $domainName),
            'queue_announce_sound' => $this->resolveRecordingPath($attributes['queue_announce_sound'] ?? null, $domainName),
            'queue_record_template' => $this->resolveRecordTemplate($attributes['queue_record_template'] ?? 'false', $domainName),
            'tiers' => $this->normalizeTiers($attributes['tiers'] ?? []),
        ];

        if (! empty($attributes['timeout_action'])) {
            $payload['timeout_action'] = $attributes['timeout_action'];
            $payload['timeout_target'] = $attributes['timeout_target'] ?? null;
        }

        return $payload;
    }

    private function resolveMohSound(?string $mohSound, string $domainName): string
    {
        if ($mohSound === null || $mohSound === '' || $mohSound === 'null') {
            return 'local_stream://default';
        }

        if (
            str_starts_with($mohSound, 'local_stream://')
            || str_starts_with($mohSound, '${')
            || str_contains($mohSound, '/music/')
        ) {
            return $mohSound;
        }

        return Storage::disk('recordings')->path($domainName . '/' . $mohSound);
    }

    private function resolveRecordingPath(?string $filename, string $domainName): ?string
    {
        if ($filename === null || $filename === '' || $filename === 'null') {
            return null;
        }

        if (str_starts_with($filename, '/')) {
            return $filename;
        }

        return Storage::disk('recordings')->path($domainName . '/' . $filename);
    }

    private function resolveRecordTemplate(string $enabled, string $domainName): string
    {
        if ($enabled !== 'true') {
            return '';
        }

        return Storage::disk('recordings')->path(
            $domainName . '/archive/${strftime(%Y)}/${strftime(%b)}/${strftime(%d)}/${uuid}.${record_ext}'
        );
    }

    private function normalizeTiers(array $tiers): array
    {
        if ($tiers === []) {
            return [];
        }

        $first = reset($tiers);

        if (is_array($first) && array_key_exists('call_center_agent_uuid', $first)) {
            return array_values($tiers);
        }

        $normalized = [];

        foreach ($tiers as $agentUuid => $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $uuid = is_string($agentUuid) && Str::isUuid($agentUuid)
                ? $agentUuid
                : ($tier['call_center_agent_uuid'] ?? null);

            if (! $uuid) {
                continue;
            }

            $normalized[] = [
                'call_center_agent_uuid' => $uuid,
                'tier_level' => (int) ($tier['level'] ?? $tier['tier_level'] ?? 1),
                'tier_position' => (int) ($tier['position'] ?? $tier['tier_position'] ?? 1),
            ];
        }

        return $normalized;
    }
}
