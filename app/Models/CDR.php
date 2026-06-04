<?php

namespace App\Models;

use Carbon\Carbon;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CDR extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_xml_cdr";

    public $timestamps = false;

    protected $primaryKey = 'xml_cdr_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_name',
    ];

    protected $appends = [
        'created_at_formatted',
        'caller_id_number_formatted',
        'caller_destination_formatted',
        'destination_number_formatted',
        'caller_id_name_formatted',
        'start_date',
        'start_time',
        'duration_formatted',
        'billsec_formatted',
        'waitsec_formatted',
        'call_disposition',
        'cc_result',
        // Add more as needed...
    ];

    public function getCreatedAtFormattedAttribute()
    {
        if (!$this->created_at || !$this->domain_uuid) return null;

        return format_domain_datetime($this->created_at, $this->domain_uuid);
    }

    public function getCallerIdNumberFormattedAttribute()
    {
        return $this->caller_id_number ? $this->formatCdrPhoneNumber($this->caller_id_number) : null;
    }

    public function getCallerIdNameFormattedAttribute()
    {
        // If the "name" looks like a US +1 number, normalize it
        if (preg_match('/^(?:\+?1)?[2-9]\d{9}$/', $this->caller_id_name)) {
            return $this->formatCdrPhoneNumber($this->caller_id_name);
        }

        // Otherwise return as-is (because it's likely a real name)
        return $this->caller_id_name;
    }

    public function getCallerDestinationFormattedAttribute()
    {
        if ($this->caller_destination === null || $this->caller_destination === '') {
            return null;
        }

        return $this->formatCdrPhoneNumber($this->caller_destination);
    }

    public function getDestinationNumberFormattedAttribute()
    {
        if ($this->destination_number === null || $this->destination_number === '') {
            return null;
        }

        return $this->formatCdrPhoneNumber($this->destination_number);
    }

    public function getStartDateAttribute()
    {
        if (!$this->start_epoch || !$this->domain_uuid) return null;

        return format_domain_timestamp((int) $this->start_epoch, $this->domain_uuid, 'date');
    }

    public function getStartTimeAttribute()
    {
        if (!$this->start_epoch || !$this->domain_uuid) return null;

        return format_domain_timestamp((int) $this->start_epoch, $this->domain_uuid, 'time');
    }

    public function getDurationFormattedAttribute()
    {
        return $this->duration ? $this->getFormattedDuration($this->duration) : null;
    }

    public function getBillsecFormattedAttribute()
    {
        return $this->billsec ? $this->getFormattedDuration($this->billsec) : null;
    }

    public function getWaitsecFormattedAttribute()
    {
        if ($this->start_epoch && $this->answer_epoch) {
            return $this->getFormattedDuration($this->answer_epoch - $this->start_epoch);
        }
        return null;
    }

    public function getCallDispositionAttribute()
    {
        $dispositions = [
            'send_bye'    => 'The recipient hung up.',
            'recv_bye'    => 'The caller hung up.',
            'send_refuse' => 'The call was refused by the recipient (e.g., busy or unavailable).',
            'recv_refuse' => 'The call was refused by the recipient (e.g., busy or unavailable).',
            'send_cancel' => 'The call was canceled before it was answered.',
            'recv_cancel' => 'The call was canceled before it was answered.',
        ];

        if ($this->sip_hangup_disposition && $this->direction) {
            return $dispositions[$this->sip_hangup_disposition] ?? 'Unknown disposition.';
        }

        // When `sip_hangup_disposition` is null and `hangup_cause` is "ORIGINATOR_CANCEL",
        // but only if `call_disposition` hasn't been set yet
        if (is_null($this->sip_hangup_disposition) && $this->hangup_cause == "ORIGINATOR_CANCEL") {
            return 'The call was canceled before it was answered.';
        }

        // When `sip_hangup_disposition` is null and `hangup_cause` is "LOSE_RACE",
        // but only if `call_disposition` hasn't been set yet
        if (is_null($this->sip_hangup_disposition) && $this->hangup_cause == "LOSE_RACE") {
            return 'The call was answered somewhere else.';
        }

        return null;
    }


    public function getCcResultAttribute()
    {
        if ($this->cc_cause == 'answered') {
            return 'Answered';
        }

        if ($this->cc_cause == 'cancel') {
            switch ($this->cc_cancel_reason) {
                case 'NONE':
                    return "No specific reason";
                case 'NO_AGENT_TIMEOUT':
                    return "No agents in queue";
                case 'BREAK_OUT':
                    return "Abandoned";
                case 'EXIT_WITH_KEY':
                    return "The caller pressed the exit key";
                case 'TIMEOUT':
                    return "Queue timeout reached";
            }
        }
        return null;
    }

    public function getStatusAttribute($value)
    {
        // 1. Missed call condition
        $status = $value;

        if ($this->voicemail_message == false && $this->missed_call == true && $this->hangup_cause == "NORMAL_CLEARING") {
            $status = "missed call";
        }

        // 2. Abandoned call upgrades missed call
        if (
            isset($this->cc_cancel_reason) &&
            isset($this->cc_cause) &&
            $status === "missed call" &&
            $this->cc_cancel_reason == "BREAK_OUT" &&
            $this->cc_cause == "cancel"
        ) {
            $status = "abandoned";
        }

        return $status;
    }

    public function getFormattedDuration($value)
    {
        // Calculate hours, minutes, and seconds
        $hours = floor($value / 3600);
        $minutes = floor(($value % 3600) / 60);
        $seconds = $value % 60;

        // Format each component to be two digits with leading zeros if necessary
        $formattedHours = str_pad($hours, 2, "0", STR_PAD_LEFT);
        $formattedMinutes = str_pad($minutes, 2, "0", STR_PAD_LEFT);
        $formattedSeconds = str_pad($seconds, 2, "0", STR_PAD_LEFT);

        // Concatenate the formatted components
        $formattedDuration = $formattedHours . ':' . $formattedMinutes . ':' . $formattedSeconds;

        return $formattedDuration;
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    public function archive_recording()
    {
        return $this->hasOne(ArchiveRecording::class, 'xml_cdr_uuid', 'xml_cdr_uuid');
    }

    /**
     * Get domain that this model belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get domain that this model belongs to 
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'extension_uuid', 'extension_uuid');
    }

    public function callTranscription()
    {
        return $this->hasOne(CallTranscription::class, 'xml_cdr_uuid', 'xml_cdr_uuid');
    }

    public function formatPhoneNumber($value)
    {
        return $this->formatCdrPhoneNumber($value);
    }

    private function phoneCountryCode(): string
    {
        if (!$this->domain_uuid) {
            return 'US';
        }

        return get_domain_setting('country', $this->domain_uuid) ?? 'US';
    }

    private function formatCdrPhoneNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return formatPhoneNumber($value, $this->phoneCountryCode(), PhoneNumberFormat::NATIONAL);
    }

    public function relatedQueueCalls()
    {
        return $this->hasMany(CDR::class, 'cc_member_session_uuid', 'xml_cdr_uuid');
    }

    public function relatedRingGroupCalls()
    {
        return $this->hasMany(CDR::class, 'originating_leg_uuid', 'xml_cdr_uuid');
    }
}
