<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VContactRelation extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_contact_relations';

    public $timestamps = false;

    protected $primaryKey = 'contact_relation_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'relation_label',
        'relation_contact_uuid',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function contact()
    {
        return $this->belongsTo(VContact::class, 'contact_uuid', 'contact_uuid');
    }

    public function relatedContact()
    {
        return $this->belongsTo(VContact::class, 'relation_contact_uuid', 'contact_uuid');
    }
}
