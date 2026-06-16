<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallTranscription extends Model
{
    use HasFactory, TraitUuid;
    
    protected $table = 'call_transcriptions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'request_payload'   => 'array',
        'response_payload'  => 'array',
        'result_payload'    => 'array',
        'summary_payload'        => 'array',
        'translation_payload'    => 'array',
        'requested_at'      => 'datetime',
        'completed_at'      => 'datetime',
        'summary_requested_at'   => 'datetime',
        'summary_completed_at'   => 'datetime',
        'translation_requested_at' => 'datetime',
        'translation_completed_at' => 'datetime',
        'notification_email_sent_at' => 'datetime',
        'transcription_cost_usd' => 'decimal:6',
        'summary_cost_usd' => 'decimal:6',
        'translation_cost_usd' => 'decimal:6',
        'total_ai_cost_usd' => 'decimal:6',
    ];

    protected $fillable = [
        'uuid','xml_cdr_uuid','domain_uuid','provider_key','external_id','status',
        'error_message','request_payload','response_payload','result_payload',
        'summary_external_id', 'summary_provider', 'summary_status', 'summary_error', 'summary_payload', 'summary_requested_at', 'summary_completed_at',
        'summary_model', 'summary_input_tokens', 'summary_output_tokens', 'summary_total_tokens', 'summary_cost_usd',
        'translation_external_id', 'translation_status', 'translation_error', 'translation_payload', 'translation_requested_at', 'translation_completed_at', 'translation_target_language',
        'translation_model', 'translation_input_tokens', 'translation_output_tokens', 'translation_total_tokens', 'translation_cost_usd',
        'transcription_audio_duration_seconds', 'transcription_speech_model', 'transcription_cost_usd', 'total_ai_cost_usd',
        'notification_email_sent_at',
        'requested_at','completed_at',
    ];

    public function cdr(): BelongsTo
    {
        return $this->belongsTo(CDR::class, 'xml_cdr_uuid', 'xml_cdr_uuid');
    }
}
