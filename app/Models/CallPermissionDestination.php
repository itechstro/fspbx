<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallPermissionDestination extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'v_call_permission_destinations';

    public $timestamps = false;

    protected $primaryKey = 'call_permission_destination_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function callPermission(): BelongsTo
    {
        return $this->belongsTo(CallPermission::class, 'call_permission_uuid', 'call_permission_uuid');
    }
}
