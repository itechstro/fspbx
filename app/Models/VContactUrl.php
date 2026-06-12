<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VContactUrl extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_contact_urls';

    public $timestamps = false;

    protected $primaryKey = 'contact_url_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'url_type',
        'url_label',
        'url_primary',
        'url_address',
        'url_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function contact()
    {
        return $this->belongsTo(VContact::class, 'contact_uuid', 'contact_uuid');
    }
}
