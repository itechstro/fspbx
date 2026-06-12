<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VContactSetting extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'v_contact_settings';

    public $timestamps = false;

    protected $primaryKey = 'contact_setting_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'contact_setting_category',
        'contact_setting_subcategory',
        'contact_setting_name',
        'contact_setting_value',
        'contact_setting_order',
        'contact_setting_enabled',
        'contact_setting_description',
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
