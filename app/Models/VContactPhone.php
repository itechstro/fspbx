<?php

namespace App\Models;

class VContactPhone extends SpeedDialPhone
{
    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'phone_label',
        'phone_type_voice',
        'phone_type_fax',
        'phone_type_video',
        'phone_type_text',
        'phone_speed_dial',
        'phone_country_code',
        'phone_number',
        'phone_extension',
        'phone_primary',
        'phone_description',
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
