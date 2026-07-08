<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassOfService extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'v_class_of_service';

    public $timestamps = false;

    protected $primaryKey = 'class_of_service_uuid';

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
            ClassOfServiceDestination::class,
            'class_of_service_uuid',
            'class_of_service_uuid'
        )->orderBy('destination_order');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extensions::class, 'class_of_service_uuid', 'class_of_service_uuid');
    }
}
