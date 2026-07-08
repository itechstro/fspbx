<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallPermission extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'v_call_permissions';

    public $timestamps = false;

    protected $primaryKey = 'call_permission_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(
            CallPermissionDestination::class,
            'call_permission_uuid',
            'call_permission_uuid'
        )->orderBy('destination_order');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extensions::class, 'call_permission_uuid', 'call_permission_uuid');
    }
}
