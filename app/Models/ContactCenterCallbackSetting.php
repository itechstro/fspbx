<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactCenterCallbackSetting extends Model
{
    public $timestamps = false;

    protected $table = 'contact_center_callback_settings';

    protected $primaryKey = 'contact_center_callback_setting_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contact_center_callback_setting_uuid',
        'queue_uuid',
        'domain_uuid',
        'enabled',
        'introduction',
        'instruction',
        'offer_after_seconds',
        'offer_key',
        'timezone',
        'closing_time',
        'max_attempts',
        'retry_seconds',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'offer_after_seconds' => 'integer',
        'max_attempts' => 'integer',
        'retry_seconds' => 'integer',
    ];
}
