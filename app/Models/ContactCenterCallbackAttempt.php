<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactCenterCallbackAttempt extends Model
{
    protected $table = 'contact_center_callback_attempts';

    protected $primaryKey = 'contact_center_callback_attempt_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
