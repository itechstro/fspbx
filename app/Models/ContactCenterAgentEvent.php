<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactCenterAgentEvent extends Model
{
    public $timestamps = false;

    protected $table = 'contact_center_agent_events';

    protected $primaryKey = 'contact_center_agent_event_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
