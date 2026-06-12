<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VContact extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_contacts';

    public $timestamps = false;

    protected $primaryKey = 'contact_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_parent_uuid',
        'contact_type',
        'contact_organization',
        'contact_name_prefix',
        'contact_name_given',
        'contact_name_middle',
        'contact_name_family',
        'contact_name_suffix',
        'contact_nickname',
        'contact_title',
        'contact_role',
        'contact_category',
        'contact_url',
        'contact_time_zone',
        'contact_note',
        'cloudplay_ed_id',
        'messages_crm_contact_uuid',
        'last_mod_date',
        'last_mod_user',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    protected $appends = ['display_name'];

    public function phones()
    {
        return $this->hasMany(VContactPhone::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function primaryPhone()
    {
        return $this->hasOne(VContactPhone::class, 'contact_uuid', 'contact_uuid')
            ->whereRaw('contact_phone_uuid = (
                SELECT cp.contact_phone_uuid
                FROM v_contact_phones AS cp
                WHERE cp.contact_uuid = v_contact_phones.contact_uuid
                ORDER BY CASE WHEN COALESCE(cp.phone_primary::text, \'\') IN (\'1\', \'true\') THEN 0 ELSE 1 END,
                         cp.insert_date ASC
                LIMIT 1
            )');
    }

    public function emails()
    {
        return $this->hasMany(VContactEmail::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function addresses()
    {
        return $this->hasMany(VContactAddress::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function notes()
    {
        return $this->hasMany(VContactNote::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function urls()
    {
        return $this->hasMany(VContactUrl::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function times()
    {
        return $this->hasMany(VContactTime::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('time_start');
    }

    public function relations()
    {
        return $this->hasMany(VContactRelation::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function attachments()
    {
        return $this->hasMany(VContactAttachment::class, 'contact_uuid', 'contact_uuid')
            ->orderBy('insert_date');
    }

    public function contactUsers()
    {
        return $this->hasMany(SpeedDialUser::class, 'contact_uuid', 'contact_uuid');
    }

    public function contactGroups()
    {
        return $this->hasMany(VContactGroup::class, 'contact_uuid', 'contact_uuid');
    }

    public function hasCallingCardSettings()
    {
        return $this->hasOne(VContactSetting::class, 'contact_uuid', 'contact_uuid')
            ->where('contact_setting_category', 'calling card')
            ->where('contact_setting_enabled', 'true');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim("{$this->contact_name_given} {$this->contact_name_family}");

        if ($name !== '') {
            return $name;
        }

        return (string) ($this->contact_organization ?? '');
    }
}
