<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactCenterAudioConfiguration extends Model
{
    protected $table = 'contact_center_audio_configurations';

    protected $primaryKey = 'contact_center_audio_configuration_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contact_center_audio_configuration_uuid',
        'queue_uuid',
        'domain_uuid',
        'mode',
        'steps',
        'requested_revision',
        'active_revision',
        'previous_revision',
        'status',
        'error',
    ];

    protected $casts = [
        'steps' => 'array',
    ];
}
