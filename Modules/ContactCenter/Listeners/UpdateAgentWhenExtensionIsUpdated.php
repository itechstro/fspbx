<?php

namespace Modules\ContactCenter\Listeners;


use App\Models\FusionCache;
use Illuminate\Bus\Queueable;
use App\Models\CallCenterAgents;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class UpdateAgentWhenExtensionIsUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $agent;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 15;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [(new RateLimitedWithRedis('default'))];
    }

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        // Initialize the $agent variable
        $this->agent = null;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('system')->allow(2)->every(1)->then(function () use ($event) {
            $originalExtension = $event->originalAttributes['extension'];
            $originalCallerId = $event->originalAttributes['effective_caller_id_name'];
            $newExtension = $event->extension['extension'];
            $newCallerId = $event->extension['effective_caller_id_name'];
    
            // Check if there are changes in extension or effective caller ID name
            if ($originalExtension === $newExtension && $originalCallerId === $newCallerId) {
                // No changes, skip execution
                return;
            }

            // Find agent relation if any
            $this->agent = CallCenterAgents::where('agent_id', $event->originalAttributes['extension'])
                ->where('domain_uuid', $event->originalAttributes['domain_uuid'])
                ->first();

            if ($this->agent) {
                $this->agent->agent_name = $event->extension['effective_caller_id_name'];
                $this->agent->agent_contact = str_replace($this->agent->agent_id, $event->extension['extension'], $this->agent->agent_contact);
                $this->agent->agent_id = $event->extension['extension'];
                $this->agent->agent_password = $event->extension['extension'];
                $this->agent->save();

                //clear fusionpbx cache
                FusionCache::clear('configuration:callcenter.conf*');

                // connect to Freeswitch 
                $fp = event_socket_create(
                    config('eventsocket.ip'),
                    config('eventsocket.port'),
                    config('eventsocket.password')
                );

                foreach ($this->agent->queues as $queue) {
                    event_socket_request($fp, sprintf(
                        'bgapi callcenter_config queue reload %s@%s',
                        $queue->queue_extension,
                        $queue->domain->domain_name
                    ));
                }
                fclose($fp);
            } else {
                $this->delete();
                return;
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }
}
