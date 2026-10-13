<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactCenterCallback extends Model
{
    protected $table = 'contact_center_callbacks';

    protected $primaryKey = 'contact_center_callback_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contact_center_callback_uuid',
        'domain_uuid',
        'queue_uuid',
        'original_call_uuid',
        'source_node',
        'number',
        'name_path',
        'name_sha256',
        'name_bytes',
        'media_ready_at',
        'joined_epoch',
        'base_score',
        'status',
        'attempts',
        'current_attempt_uuid',
        'next_attempt_at',
        'expires_at',
        'cleanup_requested_at',
        'last_error',
    ];

    protected $casts = [
        'media_ready_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'expires_at' => 'datetime',
        'cleanup_requested_at' => 'datetime',
        'joined_epoch' => 'integer',
        'base_score' => 'integer',
        'attempts' => 'integer',
        'name_bytes' => 'integer',
    ];
}
