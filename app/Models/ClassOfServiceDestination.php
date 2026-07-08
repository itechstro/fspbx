<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassOfServiceDestination extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'v_class_of_service_destinations';

    public $timestamps = false;

    protected $primaryKey = 'class_of_service_destination_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function classOfService(): BelongsTo
    {
        return $this->belongsTo(ClassOfService::class, 'class_of_service_uuid', 'class_of_service_uuid');
    }
}
