<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VContactEmail extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_contact_emails';

    public $timestamps = false;

    protected $primaryKey = 'contact_email_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'email_label',
        'email_primary',
        'email_address',
        'email_description',
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
