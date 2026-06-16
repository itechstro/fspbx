<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecorderAnalyticsExecutiveSummaryRun extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'recorder_analytics_executive_summary_runs';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'domain_uuid',
        'period_start',
        'period_end',
        'model',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'source',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'estimated_cost_usd' => 'decimal:6',
    ];
}
