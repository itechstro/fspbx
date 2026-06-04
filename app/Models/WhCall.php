<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Spatie\WebhookClient\Models\WebhookCall;

class WhCall extends WebhookCall
{

    use \App\Models\Traits\TraitUuid;

    protected $table = "webhook_calls";

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $appends = [
        'created_at_formatted',
    ];

    public function getCreatedAtFormattedAttribute()
    {
        if (!$this->created_at) {
            return null;
        }
        return format_domain_datetime($this->created_at, session('domain_uuid'));
    }

}