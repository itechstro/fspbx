<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VContactAddress extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_contact_addresses';

    public $timestamps = false;

    protected $primaryKey = 'contact_address_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'address_type',
        'address_label',
        'address_primary',
        'address_street',
        'address_extended',
        'address_community',
        'address_locality',
        'address_region',
        'address_postal_code',
        'address_country',
        'address_description',
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
