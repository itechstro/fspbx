<?php

namespace Modules\ContactCenter\Http\Requests;

use App\Rules\UniqueExtension;
use App\Services\BasicQueueService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return userCheckPermission('contact_center_settings_edit');
    }

    public function rules(): array
    {
        return [
          'queue_name' => [
              'required',
              'string',
              'max:100'
          ],
          'queue_extension' => [
              'required',
              'numeric',
              'max:10000',
              new UniqueExtension($this->queueUuid()),
          ],
          'queue_greeting' => [
            'nullable',
            Rule::exists('App\Models\Recordings', 'recording_filename')
              ->where('domain_uuid', Session::get('domain_uuid')),
          ],
          'queue_description' => [
              'nullable',
              'string',
              'max:100'
          ],
          'tiers.*.level' => [
              'nullable',
              'numeric',
              'max:9'
          ],
          'tiers.*.position' => [
              'nullable',
              'numeric',
              'max:9'
          ],
          'tiers.*.call_center_agent_uuid' => [
              'nullable',
              'uuid',
          ],
          'tiers.*.tier_level' => [
              'nullable',
              'numeric',
              'max:9',
          ],
          'tiers.*.tier_position' => [
              'nullable',
              'numeric',
              'max:9',
          ],
          'timeout_action' => [
              'nullable',
              'string',
              'max:255',
          ],
          'timeout_target' => [
              'nullable',
              'string',
              'max:1024',
          ],
          'queue_strategy' => [
            'required',
            'in:ring-all,longest-idle-agent,round-robin,top-down,agent-with-least-talk-time,agent-with-fewest-calls,sequentially-by-agent-order,random'
          ],
          'queue_max_wait_time' => [
              'nullable',
              'numeric',
              'max:86400'
          ],
          'queue_max_wait_time_with_no_agent' => [
            'nullable',
            'numeric',
            'max:86400'
          ],
          'queue_cc_exit_keys' => [
              'nullable',
              'numeric',
              'max:10'
          ],
          'timeout_category' => [
              'string',
              'in:disabled,ringgroup,extensions,timeconditions,voicemails,others'
          ],
          'queue_timeout_action' => [
            'nullable',
            'string'
          ],
          'queue_announce_sound' => [
            'nullable',
              Rule::exists('App\Models\Recordings', 'recording_filename')
                ->where('domain_uuid', Session::get('domain_uuid')),
          ],
          'queue_moh_sound' => [
            'nullable',
            'string'
          ],
          'queue_announce_frequency' => [
              'nullable',
              'numeric',
              'max:86400'
          ],
          'queue_announce_position' => 'in:true,false',
          'queue_time_base_score' => [
            'required',
            'in:queue,system'
          ],
          'queue_time_base_score_sec' => [
              'nullable',
              'numeric',
              'max:86400'
          ],
          'queue_tier_rules_apply' => 'in:true,false',
          'queue_tier_rule_wait_second' => [
              'nullable',
              'numeric',
              'max:86400'
          ],
          'queue_tier_rule_wait_multiply_level' => 'in:true,false',
          'queue_tier_rule_no_agent_no_wait' => 'in:true,false',
          'queue_discard_abandoned_after' => [
              'nullable',
              'numeric',
              'max:86400'
          ],
          'queue_email_address' => [
            'nullable',
            'email'
          ],
          'queue_abandoned_resume_allowed' => 'in:true,false',
          'queue_cid_prefix' => [
            'nullable',
            'regex:/^[A-z0-9-_\s]+$/u',
          ],
          'queue_record_template' => 'in:true,false',
          'queue_max_wait_time_with_no_agent_time_reached' => [
            'required',
            'in:5'
          ],
        ];
    }

    public function messages(): array
    {
        return [
            'queue_name.required' => 'The name is required',
            'queue_extension.required' => 'The extension is required',
            'queue_extension.numeric' => 'The extension should be numeric',
            'queue_extension.unique' => 'This extension is already used'
        ];
    }

    protected function queueUuid(): ?string
    {
        $queue = $this->route('callCenterQueues');

        return is_object($queue)
            ? $queue->call_center_queue_uuid
            : $queue;
    }

    public function prepareForValidation(): void
    {
        $queueGreeting = $this->get('queue_greeting') === 'disabled' ? null : $this->get('queue_greeting');
        $queueAnnounceSound = $this->get('queue_announce_sound') === 'null' ? null : $this->get('queue_announce_sound');

        $queueTimeoutAction = null;

        if ($this->filled('timeout_action')) {
            $queueTimeoutAction = app(BasicQueueService::class)->buildQueueTimeoutAction(
                $this->only(['timeout_action', 'timeout_target']),
                Session::get('domain_name'),
            );
        } else {
            switch ($this->get('timeout_category')) {
                case 'ringgroup':
                    $queueTimeoutAction = 'transfer:' . $this->get('timeout_action_ringgroup');
                    break;
                case 'dialplans':
                    $queueTimeoutAction = 'transfer:' . $this->get('timeout_action_dialplans');
                    break;
                case 'extensions':
                    $queueTimeoutAction = 'transfer:' . $this->get('timeout_action_extensions');
                    break;
                case 'timeconditions':
                    $queueTimeoutAction = 'transfer:' . $this->get('timeout_action_timeconditions');
                    break;
                case 'voicemails':
                    $queueTimeoutAction = 'transfer:' . $this->get('timeout_action_voicemails');
                    break;
                case 'others':
                    $queueTimeoutAction = $this->get('timeout_action_others');
                    if ($queueTimeoutAction != 'hangup:') {
                        $queueTimeoutAction = 'transfer:' . $queueTimeoutAction;
                    }
                    break;
            }
        }

        $this->merge([
            'call_center_queue_uuid' => $this->queueUuid(),
            'queue_greeting' => $queueGreeting,
            'queue_announce_sound' => $queueAnnounceSound,
            'queue_timeout_action' => $queueTimeoutAction,
            'queue_time_base_score' => $this->get('queue_time_base_score', 'system'),
            'queue_max_wait_time_with_no_agent_time_reached' => $this->get('queue_max_wait_time_with_no_agent_time_reached', '5'),
            'queue_tier_rules_apply' => $this->get('queue_tier_rules_apply', 'false'),
            'queue_tier_rule_wait_multiply_level' => $this->get('queue_tier_rule_wait_multiply_level', 'false'),
            'queue_tier_rule_no_agent_no_wait' => $this->get('queue_tier_rule_no_agent_no_wait', 'false'),
            'queue_announce_position' => $this->get('queue_announce_position', 'false'),
            'queue_abandoned_resume_allowed' => $this->get('queue_abandoned_resume_allowed', 'false'),
            'queue_record_template' => $this->get('queue_record_template', 'false'),
        ]);
    }
}
