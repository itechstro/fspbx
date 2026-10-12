<?php

namespace App\Listeners\ContactCenter;

use App\Models\CallCenterAgents;
use App\Jobs\ContactCenter\DeleteAgentJob;

class DeleteAgentWhenExtensionIsDeleted
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        // Find agent relation if any
        $agent = CallCenterAgents::where('agent_id', $event->originalAttributes['extension'])
            ->where('domain_uuid', $event->originalAttributes['domain_uuid'])
            ->first();
            
        if ($agent) {
            DeleteAgentJob::dispatch($agent);
        }
    }
}
