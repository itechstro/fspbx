<?php

namespace App\Http\Controllers\ContactCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactCenter\SaveAudioSequenceRequest;
use App\Http\Requests\ContactCenter\SaveCallbackSettingsRequest;
use App\Models\CallCenterQueues;
use App\Models\ContactCenterAudioConfiguration;
use App\Models\ContactCenterCallback;
use App\Models\ContactCenterCallbackSetting;
use App\Services\ContactCenter\ContactCenterRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;

class RuntimeController extends Controller
{
    public function __construct(private ContactCenterRuntime $runtime) {}

    public function show(CallCenterQueues $callCenterQueues): JsonResponse
    {
        $this->authorizeQueue($callCenterQueues);

        $settings = ContactCenterCallbackSetting::query()
            ->where('queue_uuid', $callCenterQueues->call_center_queue_uuid)
            ->first();

        $audio = ContactCenterAudioConfiguration::query()
            ->where('queue_uuid', $callCenterQueues->call_center_queue_uuid)
            ->first();

        return response()->json([
            'callbacks' => [
                'enabled' => (bool) ($settings?->enabled ?? false),
                'introduction' => $settings?->introduction,
                'instruction' => $settings?->instruction,
                'offer_after_seconds' => $settings?->offer_after_seconds ?? 90,
                'offer_key' => $settings?->offer_key ?? '9',
                'timezone' => $settings?->timezone ?? (get_local_time_zone($callCenterQueues->domain_uuid) ?: 'UTC'),
                'closing_time' => $settings?->closing_time ? substr((string) $settings->closing_time, 0, 5) : '17:00',
                'max_attempts' => $settings?->max_attempts ?? 3,
                'retry_seconds' => $settings?->retry_seconds ?? 600,
            ],
            'audio' => [
                'mode' => $audio?->mode ?? 'continuous',
                'status' => $audio?->status ?? 'idle',
                'error' => $audio?->error,
                'steps' => $audio?->steps['clips'] ?? [],
            ],
            'history' => $this->runtime->history(
                $callCenterQueues->call_center_queue_uuid,
                $callCenterQueues->domain_uuid
            ),
        ]);
    }

    public function updateCallbacks(SaveCallbackSettingsRequest $request, CallCenterQueues $callCenterQueues): JsonResponse
    {
        $this->authorizeQueue($callCenterQueues);
        $exitKey = (string) $callCenterQueues->queue_cc_exit_keys;
        if ($exitKey !== '' && str_contains($exitKey, (string) $request->validated('offer_key'))) {
            return response()->json([
                'errors' => ['offer_key' => ['Choose a callback key that is not already a queue exit key.']],
            ], 422);
        }

        $setting = $this->runtime->saveCallbackSettings($callCenterQueues, $request->validated());

        return response()->json([
            'messages' => ['success' => ['Callback settings saved.']],
            'callbacks' => $setting,
        ]);
    }

    public function updateAudio(SaveAudioSequenceRequest $request, CallCenterQueues $callCenterQueues): JsonResponse
    {
        $this->authorizeQueue($callCenterQueues);
        $audio = $this->runtime->saveAudio($callCenterQueues, $request->validated());

        if ($audio->status === 'failed') {
            return response()->json([
                'errors' => ['steps' => [$audio->error ?: 'The audio sequence could not be prepared.']],
            ], 422);
        }

        return response()->json([
            'messages' => ['success' => ['Hold audio saved.']],
            'audio' => [
                'mode' => $audio->mode,
                'status' => $audio->status,
                'error' => $audio->error,
                'steps' => $audio->steps['clips'] ?? [],
            ],
        ]);
    }

    public function cancel(CallCenterQueues $callCenterQueues, ContactCenterCallback $callback): JsonResponse
    {
        if (! userCheckPermission('contact_center_settings_edit')) {
            return response()->json(['errors' => ['error' => ['Access denied.']]], 403);
        }

        $this->authorizeQueue($callCenterQueues);
        if ($callback->queue_uuid !== $callCenterQueues->call_center_queue_uuid) {
            return response()->json(['errors' => ['error' => ['Callback not found.']]], 404);
        }

        try {
            $this->runtime->cancel($callback);
        } catch (\Throwable $exception) {
            return response()->json(['errors' => ['error' => [$exception->getMessage()]]], 422);
        }

        return response()->json(['messages' => ['success' => ['Callback cancelled.']]]);
    }

    private function authorizeQueue(CallCenterQueues $queue): void
    {
        if ($queue->domain_uuid !== Session::get('domain_uuid')) {
            abort(404);
        }
    }
}
