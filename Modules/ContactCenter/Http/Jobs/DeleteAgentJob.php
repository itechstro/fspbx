<?php

namespace Modules\ContactCenter\Http\Jobs;

use App\Models\FusionCache;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;

class DeleteAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $agent;

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
    public $timeout = 300;

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
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($agent)
    {
        $this->agent = $agent;
    }

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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Redis::throttle('system')->allow(2)->every(1)->then(function () {

            // Connect to freeswitch
            $fp = event_socket_create(
                config('eventsocket.ip'),
                config('eventsocket.port'),
                config('eventsocket.password')
            );

            //clear fusionpbx cache
            FusionCache::clear('configuration:callcenter.conf*');

            //Delete agent from the queue and reload
            foreach ($this->agent->queues as $queue) {
                $queue->agents()->detach($this->agent->call_center_agent_uuid);
                event_socket_request($fp, sprintf(
                    'api callcenter_config tier del %s@%s %s',
                    $queue->queue_extension,
                    $this->agent->domain->domain_name,
                    $this->agent->call_center_agent_uuid
                ));

                event_socket_request($fp, sprintf(
                    'api callcenter_config queue reload %s@%s',
                    $queue->queue_extension,
                    $this->agent->domain->domain_name,
                ));
            }

            fclose($fp);

            //Delete agent from database
            $this->agent->delete();

        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }
}
