<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecorderAnalyticsReportSchedule extends Model
{
    use HasFactory, Traits\TraitUuid;

    protected $table = 'recorder_analytics_report_schedules';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'enabled',
        'include_executive_summary',
        'search',
        'emails',
        'frequency',
        'send_time',
        'day_of_week',
        'day_of_month',
        'last_sent_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'include_executive_summary' => 'boolean',
        'emails' => 'array',
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'last_sent_at' => 'datetime',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
