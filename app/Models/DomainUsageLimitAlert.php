<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DomainUsageLimitAlert extends Model
{
    use HasUuids;

    protected $table = 'domain_usage_limit_alerts';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'period',
        'limit_key',
        'alert_level',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
