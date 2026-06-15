<?php

namespace App\Models;

use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpeedDialPhone extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_contact_phones";

    public $timestamps = false;

    protected $primaryKey = 'contact_phone_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'phone_type_voice',
        'phone_number',
        'phone_speed_dial',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];

    protected $appends = ['phone_number_formatted', 'phone_list_display'];

    /**
     * Accessor: Get phone number formatted
     */
    public function getPhoneNumberFormattedAttribute()
    {
        $number = trim((string) ($this->phone_number ?? ''));

        if ($number === '') {
            return null;
        }

        return formatPhoneNumber($number, 'US', PhoneNumberFormat::NATIONAL);
    }

    public function getPhoneListDisplayAttribute(): ?string
    {
        $number = trim((string) ($this->phone_number ?? ''));
        $extension = trim((string) ($this->phone_extension ?? ''));

        if ($number !== '') {
            return $this->phone_number_formatted ?: $number;
        }

        if ($extension !== '') {
            return 'extension: ' . $extension;
        }

        return null;
    }

    /**
     * Get the Device Lines objects associated with this device.
     *  returns Eloquent Object
     */
    public function speedDial()
    {
        return $this->hasOne(SpeedDial::class, 'contact_uuid', 'contact_uuid');
    }
}
