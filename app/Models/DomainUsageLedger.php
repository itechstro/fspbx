<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DomainUsageLedger extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'domain_usage_ledger';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'domain_uuid',
        'period',
        'metric',
        'amount',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'metadata' => 'array',
    ];
}
