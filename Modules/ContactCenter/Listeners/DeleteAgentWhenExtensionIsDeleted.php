<?php

namespace Modules\ContactCenter\Listeners;

use App\Models\CallCenterAgents;
use Modules\ContactCenter\Http\Jobs\DeleteAgentJob;

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
