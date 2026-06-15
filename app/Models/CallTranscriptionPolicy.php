<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallTranscriptionPolicy extends Model
{

    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'call_transcription_policy';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'enabled',
        'auto_transcribe',
        'auto_summarize',
        'auto_transcribe_recorder',
        'auto_summarize_recorder',
        'auto_translate',
        'auto_translate_recorder',
        'provider_uuid',
        'email_transcription',
        'email_transcription_recorder',
        'email_translation',
        'email_translation_recorder',
        'email',
        'email_recorder',
        'translation_language',
    ];

    public function provider()
    {
        return $this->belongsTo(CallTranscriptionProvider::class, 'provider_uuid', 'uuid');
    }
}
