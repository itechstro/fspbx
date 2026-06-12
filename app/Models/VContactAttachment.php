<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VContactAttachment extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_contact_attachments';

    public $timestamps = false;

    protected $primaryKey = 'contact_attachment_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'attachment_primary',
        'attachment_filename',
        'attachment_content',
        'attachment_description',
        'attachment_uploaded_date',
        'attachment_uploaded_user_uuid',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    protected $hidden = [
        'attachment_content',
    ];

    public function contact()
    {
        return $this->belongsTo(VContact::class, 'contact_uuid', 'contact_uuid');
    }
}
