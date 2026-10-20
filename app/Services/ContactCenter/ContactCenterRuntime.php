<?php

namespace App\Services\ContactCenter;

use App\Models\CallCenterAgents;
use App\Models\CallCenterQueues;
use App\Models\ContactCenterAgentEvent;
use App\Models\ContactCenterAudioConfiguration;
use App\Models\ContactCenterCallback;
use App\Models\ContactCenterCallbackAttempt;
use App\Models\ContactCenterCallbackSetting;
use App\Models\Domain;
use App\Services\BasicQueueService;
use App\Services\FreeswitchEslService;
use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ContactCenterRuntime
{
    public function usesQueueScript(CallCenterQueues $queue): bool
    {
        if (! Schema::hasTable('contact_center_callback_settings')) {
            return false;
        }

        return ContactCenterCallbackSetting::query()
            ->where('queue_uuid', $queue->call_center_queue_uuid)
            ->where('enabled', true)
            ->exists();
    }

    public function participates(CallCenterQueues $queue): bool
    {
        if (Schema::hasTable('contact_center_callback_settings')
            && ContactCenterCallbackSetting::query()->where('queue_uuid', $queue->call_center_queue_uuid)->exists()) {
            return true;
        }

        return Schema::hasTable('contact_center_audio_configurations')
            && ContactCenterAudioConfiguration::query()->where('queue_uuid', $queue->call_center_queue_uuid)->exists();
    }

    public function sequenceManagesAudio(CallCenterQueues $queue): bool
    {
        if (! Schema::hasTable('contact_center_audio_configurations')) {
            return false;
        }

        return ContactCenterAudioConfiguration::query()
            ->where('queue_uuid', $queue->call_center_queue_uuid)
            ->where('mode', 'sequence')
            ->where('status', 'ready')
            ->exists();
    }

    public function recordAgentSample(string $domainUuid, ?string $queueUuid, array $agent, bool $active = true): void
    {
        if (! $active || ! Schema::hasTable('contact_center_agent_events')) {
            return;
        }

        $agentUuid = (string) ($agent['name'] ?? '');
        if ($agentUuid === '') {
            return;
        }

        $status = (string) ($agent['status'] ?? '');
        $activity = (string) ($agent['activity'] ?? '');
        $signature = $status.'|'.$activity.'|'.(int) ($agent['ready_time'] ?? 0);
        $cacheKey = 'cc-agent-sample:'.$agentUuid;
        $previous = Cache::get($cacheKey);
        Cache::put($cacheKey, $signature, 3600);
        if (! is_string($previous) || $previous === $signature) {
            return;
        }

        if (! Cache::has('cc-agent-events-pruned')) {
            ContactCenterAgentEvent::query()->where('created_at', '<', now()->subDays(90))->delete();
            Cache::put('cc-agent-events-pruned', true, 3600);
        }

        [$previousStatus] = array_pad(explode('|', $previous, 3), 3, null);
        $ready = (int) ($agent['ready_time'] ?? 0);

        ContactCenterAgentEvent::query()->create([
            'contact_center_agent_event_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'agent_uuid' => $agentUuid,
            'queue_uuid' => $queueUuid,
            'event_identity' => (string) Str::uuid(),
            'event_us' => (int) round(microtime(true) * 1000000),
            'event_type' => 'status_change',
            'previous_status' => $previousStatus,
            'status' => $status,
            'reason' => $activity !== '' ? $activity : null,
            'cooldown_end' => $ready > time() ? $ready : null,
            'created_at' => now(),
        ]);
    }

    public function agentHistory(string $domainUuid)
    {
        if (! Schema::hasTable('contact_center_agent_events')) {
            return collect();
        }

        return ContactCenterAgentEvent::query()
            ->leftJoin('v_call_center_agents as agents', 'agents.call_center_agent_uuid', '=', 'contact_center_agent_events.agent_uuid')
            ->where('contact_center_agent_events.domain_uuid', $domainUuid)
            ->orderByDesc('contact_center_agent_events.event_us')
            ->limit(40)
            ->get([
                'contact_center_agent_events.contact_center_agent_event_uuid',
                'contact_center_agent_events.status',
                'contact_center_agent_events.previous_status',
                'contact_center_agent_events.reason',
                'contact_center_agent_events.created_at',
                'agents.agent_name',
            ]);
    }

    public function saveCallbackSettings(CallCenterQueues $queue, array $input): ContactCenterCallbackSetting
    {
        $setting = ContactCenterCallbackSetting::query()->firstOrNew([
            'queue_uuid' => $queue->call_center_queue_uuid,
        ]);

        if (! $setting->exists) {
            $setting->contact_center_callback_setting_uuid = (string) Str::uuid();
        }

        $wasEnabled = (bool) $setting->enabled;
        $domainName = Domain::query()->whereKey($queue->domain_uuid)->value('domain_name');

        $setting->fill([
            'domain_uuid' => $queue->domain_uuid,
            'enabled' => (bool) $input['enabled'],
            'introduction' => $this->recordingPath($domainName, $input['introduction'] ?? null),
            'instruction' => $this->recordingPath($domainName, $input['instruction'] ?? null),
            'offer_after_seconds' => (int) $input['offer_after_seconds'],
            'offer_key' => (string) $input['offer_key'],
            'timezone' => (string) $input['timezone'],
            'closing_time' => (string) $input['closing_time'],
            'max_attempts' => (int) $input['max_attempts'],
            'retry_seconds' => (int) $input['retry_seconds'],
        ]);
        $setting->save();

        if ($wasEnabled && ! $setting->enabled) {
            ContactCenterCallback::query()
                ->where('queue_uuid', $queue->call_center_queue_uuid)
                ->whereIn('status', ['waiting', 'dialing_agent'])
                ->update([
                    'status' => 'expired',
                    'last_error' => 'Callbacks were turned off.',
                ]);
        }

        $this->recordChange($queue, 'callback_settings');
        $this->refreshQueue($queue);

        return $setting;
    }

    public function saveAudio(CallCenterQueues $queue, array $input): ContactCenterAudioConfiguration
    {
        $config = ContactCenterAudioConfiguration::query()->firstOrNew([
            'queue_uuid' => $queue->call_center_queue_uuid,
        ]);

        if (! $config->exists) {
            $config->contact_center_audio_configuration_uuid = (string) Str::uuid();
        }

        $mode = $input['mode'] === 'sequence' ? 'sequence' : 'continuous';
        $steps = array_values($input['steps'] ?? []);
        $callbackSteps = count(array_filter($steps, fn ($step) => ($step['type'] ?? '') === 'callback'));
        if ($callbackSteps > 1) {
            throw new RuntimeException(__('A sequence can include one callback offer.'));
        }
        $previousMoh = $config->steps['previous_moh'] ?? $queue->queue_moh_sound;

        if ($mode === 'continuous') {
            $restore = $config->steps['previous_moh'] ?? null;
            $config->fill([
                'domain_uuid' => $queue->domain_uuid,
                'mode' => 'continuous',
                'steps' => ['clips' => [], 'previous_moh' => $restore],
                'status' => 'idle',
                'error' => null,
            ]);
            $config->save();

            if (is_string($restore) && $restore !== '') {
                $this->applyMoh($queue, $restore);
            }

            return $config;
        }

        $config->fill([
            'domain_uuid' => $queue->domain_uuid,
            'mode' => 'sequence',
            'steps' => ['clips' => $steps, 'previous_moh' => $previousMoh],
            'status' => 'preparing',
            'error' => null,
            'requested_revision' => (string) Str::uuid(),
        ]);
        $config->save();

        try {
            $prepared = $this->prepareSequence($queue, $steps);
            $config->steps = [
                'clips' => $prepared['steps'],
                'previous_moh' => $previousMoh,
            ];
            $config->status = 'ready';
            $config->active_revision = $config->requested_revision;
            $config->error = null;
            $config->save();
            $this->applyMoh($queue, $prepared['path']);
        } catch (\Throwable $exception) {
            $config->status = 'failed';
            $config->error = $exception->getMessage();
            $config->save();
        }

        $this->recordChange($queue, 'audio_sequence');

        return $config->fresh();
    }

    public function history(string $queueUuid, string $domainUuid)
    {
        return ContactCenterCallback::query()
            ->where('domain_uuid', $domainUuid)
            ->where('queue_uuid', $queueUuid)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function cancel(ContactCenterCallback $callback): void
    {
        if (! in_array($callback->status, ['waiting', 'dialing_agent'], true)) {
            throw new RuntimeException('This callback can no longer be cancelled.');
        }

        $callback->status = 'cancelled';
        $callback->last_error = null;
        $callback->save();
    }

    public function processDue(): int
    {
        if (! Schema::hasTable('contact_center_callbacks')) {
            return 0;
        }

        if (! app(ActiveNodeResolver::class)->isActive()) {
            return 0;
        }

        $this->releaseStaleDials();
        $this->expireFinished();
        ContactCenterCallback::query()
            ->where('status', 'calling')
            ->where('updated_at', '<', now()->subMinutes(3))
            ->update([
                'status' => 'needs_review',
                'last_error' => 'The callback call did not finish.',
            ]);

        $started = 0;
        $due = ContactCenterCallback::query()
            ->where('status', 'waiting')
            ->where('next_attempt_at', '<=', now())
            ->where('expires_at', '>', now())
            ->orderBy('next_attempt_at')
            ->limit(10)
            ->get();

        foreach ($due as $callback) {
            if ($this->startCallback($callback)) {
                $started++;
            }
        }

        return $started;
    }

    private function startCallback(ContactCenterCallback $callback): bool
    {
        $agent = $this->availableAgent($callback);
        if (! $agent) {
            $callback->next_attempt_at = now()->addSeconds(30);
            $callback->last_error = 'No agent is available.';
            $callback->save();

            return false;
        }

        $domainName = Domain::query()->whereKey($callback->domain_uuid)->value('domain_name');
        if (! $domainName) {
            $callback->status = 'needs_review';
            $callback->last_error = 'The account address is missing.';
            $callback->save();

            return false;
        }

        $attemptId = (string) Str::uuid();
        $callback->status = 'dialing_agent';
        $callback->current_attempt_uuid = $attemptId;
        $callback->last_error = null;
        $callback->save();

        ContactCenterCallbackAttempt::query()->create([
            'contact_center_callback_attempt_uuid' => $attemptId,
            'callback_uuid' => $callback->contact_center_callback_uuid,
            'execution_uuid' => (string) Str::uuid(),
            'node_id' => substr((string) gethostname(), 0, 32),
            'generation' => 1,
            'status' => 'preparing',
            'waiter_uuid' => (string) Str::uuid(),
            'customer_uuid' => (string) Str::uuid(),
            'agent_uuid' => $agent->call_center_agent_uuid,
            'token_hash' => hash('sha256', $callback->contact_center_callback_uuid),
            'core_uuid' => (string) Str::uuid(),
        ]);

        $number = preg_replace('/\D+/', '', (string) $callback->number) ?: '0';
        $command = sprintf(
            "bgapi originate {origination_caller_id_name='Callback - %s',origination_caller_id_number=%s,originate_timeout=25,domain_name=%s}%s &lua(lua/contact_center_callback_agent.lua %s)",
            $number,
            $number,
            $domainName,
            $agent->agent_contact,
            $callback->contact_center_callback_uuid
        );

        $result = app(FreeswitchEslService::class)->executeCommand($command);
        $body = is_string($result) ? $result : '';
        if ($body === '' || str_contains($body, '-ERR')) {
            $callback->status = 'waiting';
            $callback->next_attempt_at = now()->addSeconds(60);
            $callback->last_error = $body !== '' ? $body : 'FreeSWITCH did not accept the callback call.';
            $callback->save();

            return false;
        }

        return true;
    }

    private function availableAgent(ContactCenterCallback $callback): ?CallCenterAgents
    {
        $agentUuids = DB::table('v_call_center_tiers')
            ->where('call_center_queue_uuid', $callback->queue_uuid)
            ->pluck('call_center_agent_uuid');

        return CallCenterAgents::query()
            ->where('domain_uuid', $callback->domain_uuid)
            ->whereIn('call_center_agent_uuid', $agentUuids)
            ->whereIn('agent_status', ['Available', 'Available (On Demand)'])
            ->whereNotNull('agent_contact')
            ->orderBy('agent_name')
            ->first();
    }

    private function releaseStaleDials(): void
    {
        ContactCenterCallback::query()
            ->where('status', 'dialing_agent')
            ->where('updated_at', '<', now()->subSeconds(50))
            ->update([
                'status' => 'waiting',
                'next_attempt_at' => now()->addSeconds(30),
                'last_error' => 'The agent did not answer.',
            ]);
    }

    private function expireFinished(): void
    {
        ContactCenterCallback::query()
            ->whereIn('status', ['waiting', 'dialing_agent'])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
                'last_error' => 'The daily callback cutoff passed.',
            ]);
    }

    private function prepareSequence(CallCenterQueues $queue, array $steps): array
    {
        $domainName = Domain::query()->whereKey($queue->domain_uuid)->value('domain_name');
        $directory = Storage::disk('recordings')->path($domainName.'/contact-center-audio/'.$queue->call_center_queue_uuid);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the hold-audio directory.');
        }

        $list = '';
        $preparedSteps = [];
        $offset = 0;

        foreach ($steps as $step) {
            $type = $step['type'] ?? 'clip';
            if ($type !== 'clip') {
                $preparedSteps[] = [
                    'type' => $type,
                    'offset_seconds' => $offset,
                ];
                continue;
            }

            $source = $this->clipSource($domainName, (string) ($step['recording'] ?? ''));
            $limit = isset($step['duration_seconds']) && $step['duration_seconds'] !== ''
                ? (int) $step['duration_seconds']
                : null;
            $duration = $this->durationSeconds($source);
            $playFor = $limit && $limit > 0 ? min($limit, $duration) : $duration;
            $piece = $directory.'/'.Str::uuid().'.wav';
            $command = ['ffmpeg', '-y', '-i', $source, '-ac', '1', '-ar', '8000', '-t', (string) max(1, $playFor), $piece];
            $result = Process::timeout(120)->run($command);
            if (! $result->successful() || ! is_file($piece)) {
                throw new RuntimeException('Unable to prepare a hold-audio clip.');
            }

            $list .= "file '".str_replace("'", "'\\''", $piece)."'\n";
            $preparedSteps[] = [
                'type' => 'clip',
                'recording' => $step['recording'],
                'duration_seconds' => (int) $playFor,
                'offset_seconds' => $offset,
            ];
            $offset += (int) $playFor;
        }

        if ($list === '') {
            throw new RuntimeException('Add at least one audio clip.');
        }

        $concatList = $directory.'/list.txt';
        file_put_contents($concatList, $list);
        $output = $directory.'/sequence.wav';
        $joined = Process::timeout(120)->run([
            'ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $concatList, '-c', 'copy', $output,
        ]);
        if (! $joined->successful() || ! is_file($output)) {
            throw new RuntimeException('Unable to join the hold-audio sequence.');
        }

        return ['path' => $output, 'steps' => $preparedSteps];
    }

    private function recordingPath(?string $domainName, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_starts_with($value, '/') && is_file($value)) {
            return $value;
        }

        if ($domainName) {
            $path = Storage::disk('recordings')->path($domainName.'/'.ltrim($value, '/'));
            if (is_file($path)) {
                return $path;
            }
        }

        return $value;
    }

    private function clipSource(string $domainName, string $recording): string
    {
        $path = Storage::disk('recordings')->path($domainName.'/'.ltrim($recording, '/'));
        if (! is_file($path)) {
            throw new RuntimeException('Recording '.$recording.' was not found.');
        }

        return $path;
    }

    private function durationSeconds(string $path): int
    {
        $result = Process::timeout(30)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $path,
        ]);
        $seconds = (int) ceil((float) trim($result->output()));

        return max(1, $seconds);
    }

    private function applyMoh(CallCenterQueues $queue, string $moh): void
    {
        $queue->queue_moh_sound = $moh;
        $queue->save();
        $this->refreshQueue($queue);
    }

    private function refreshQueue(CallCenterQueues $queue): void
    {
        app(BasicQueueService::class)->refreshDialplan($queue);
    }

    private function recordChange(CallCenterQueues $queue, string $kind): void
    {
        if (! Schema::hasTable('contact_center_changes')) {
            return;
        }

        DB::table('contact_center_changes')->insert([
            'contact_center_change_uuid' => (string) Str::uuid(),
            'source_node' => substr((string) gethostname(), 0, 32),
            'domain_uuid' => $queue->domain_uuid,
            'kind' => $kind,
            'entity_uuid' => $queue->call_center_queue_uuid,
            'previous' => null,
            'created_at' => now(),
        ]);
    }
}
