<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class Activity extends SpatieActivity implements ActivityContract
{
    use HasFactory, \App\Models\Traits\TraitUuid, \App\Models\Traits\ResolvesDomain;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            unset($model->created_at_formatted);
            if (!$model->domain_uuid) {
                $model->domain_uuid = self::getRuntimeDomainUuid() ?? session('domain_uuid');
            }

        });

        static::retrieved(function ($model) {
            if ($model->created_at) {
                $model->created_at_formatted = format_domain_datetime(
                    $model->created_at,
                    $model->domain_uuid ?? session('domain_uuid')
                );
            }
            // $model->destroy_route = route('devices.destroy', $model);

            return $model;
        });
    }

    /**
     * Get domain that this model belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }


}
