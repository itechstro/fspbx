<?php

namespace App\Services;

use App\Models\ConferenceProfile;
use App\Models\ConferenceProfileParam;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SrsRecorderDialplanService
{
    public const SETTING_SUBCATEGORY = 'show_recorder_filter';

    public const CONFERENCE_PROFILE_NAME = 'recorder';

    public function __construct(private readonly DialplanService $dialplanService)
    {
    }

    public function syncForDomain(Domain $domain, ?bool $enabled = null): void
    {
        $enabled ??= $this->isRecorderEnabledForDomain($domain->domain_uuid);

        if ($enabled) {
            $this->provisionForDomain($domain);
            return;
        }

        $this->disableForDomain($domain);
    }

    public function isRecorderEnabledForDomain(string $domainUuid): bool
    {
        $value = get_domain_setting(self::SETTING_SUBCATEGORY, $domainUuid);

        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function domainPrefix(string $domainName): string
    {
        $slug = explode('.', $domainName, 2)[0] ?: $domainName;
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug);

        return strtolower($slug);
    }

    public function recorderDestinationNumber(string $domainName): string
    {
        return 'recorder_' . $this->domainPrefix($domainName);
    }

    public function recorderDestinationExpression(string $domainName): string
    {
        return '^' . preg_quote($this->recorderDestinationNumber($domainName), '/') . '$';
    }

    public function recorderCatchDialplanName(string $domainName): string
    {
        return 'recorder_catch_' . $this->domainPrefix($domainName);
    }

    public function provisionForDomain(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $this->upsertSrsRecorderDialplan($domain);
            $this->upsertRecorderCatchDialplan($domain);
            $this->upsertRecorderConferenceProfile();
        });

        $this->clearCaches($domain->domain_name);
    }

    public function disableForDomain(Domain $domain): void
    {
        DB::transaction(function () use ($domain) {
            foreach ($this->recorderDialplansForDomain($domain) as $dialplan) {
                $dialplan->dialplan_enabled = 'false';
                $dialplan->update_date = now();
                $dialplan->update_user = session('user_uuid');
                $dialplan->save();
            }

            if (! $this->anyDomainHasRecorderEnabled()) {
                $this->disableRecorderConferenceProfile();
            }
        });

        $this->clearCaches($domain->domain_name);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Dialplans>
     */
    protected function recorderDialplansForDomain(Domain $domain): \Illuminate\Support\Collection
    {
        $catchName = $this->recorderCatchDialplanName($domain->domain_name);

        return Dialplans::query()
            ->where(function ($query) use ($domain, $catchName) {
                $query->where(function ($inner) use ($domain) {
                    $inner->where('domain_uuid', $domain->domain_uuid)
                        ->where('dialplan_name', 'srs_recorder');
                })->orWhere(function ($inner) use ($catchName) {
                    $inner->where('dialplan_name', $catchName)
                        ->where('dialplan_context', 'public');
                });
            })
            ->get();
    }

    protected function upsertSrsRecorderDialplan(Domain $domain): Dialplans
    {
        $dialplan = Dialplans::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->where('dialplan_name', 'srs_recorder')
            ->first();

        $isNew = ! $dialplan;
        $dialplan ??= new Dialplans();
        $dialplanUuid = $dialplan->dialplan_uuid ?: (string) Str::uuid();

        $details = $this->srsRecorderDetails($domain->domain_name);

        $dialplan->dialplan_uuid = $dialplanUuid;
        $dialplan->dialplan_name = 'srs_recorder';
        $dialplan->dialplan_continue = 'true';

        $dialplan->forceFill([
            'dialplan_uuid' => $dialplanUuid,
            'domain_uuid' => $domain->domain_uuid,
            'dialplan_name' => 'srs_recorder',
            'dialplan_number' => null,
            'dialplan_destination' => 'false',
            'dialplan_context' => 'global',
            'dialplan_continue' => 'true',
            'dialplan_order' => 50,
            'dialplan_enabled' => 'true',
            'dialplan_description' => 'SIPREC recorder conference and CDR handling',
            'dialplan_xml' => $this->dialplanService->buildXml($dialplan, $details),
            'insert_date' => $isNew ? now() : ($dialplan->insert_date ?? now()),
            'insert_user' => $isNew ? session('user_uuid') : $dialplan->insert_user,
            'update_date' => $isNew ? null : now(),
            'update_user' => $isNew ? null : session('user_uuid'),
        ])->save();

        $dialplan->dialplan_details()->delete();
        $this->createDetails($dialplan, $details);

        return $dialplan;
    }

    protected function upsertRecorderCatchDialplan(Domain $domain): Dialplans
    {
        $catchName = $this->recorderCatchDialplanName($domain->domain_name);

        $dialplan = Dialplans::query()
            ->where('dialplan_name', $catchName)
            ->where('dialplan_context', 'public')
            ->first();

        $isNew = ! $dialplan;
        $dialplan ??= new Dialplans();
        $dialplanUuid = $dialplan->dialplan_uuid ?: (string) Str::uuid();

        $details = $this->recorderCatchDetails($domain->domain_name);

        $dialplan->dialplan_uuid = $dialplanUuid;
        $dialplan->dialplan_name = $catchName;
        $dialplan->dialplan_continue = 'false';

        $dialplan->forceFill([
            'dialplan_uuid' => $dialplanUuid,
            'domain_uuid' => null,
            'dialplan_name' => $catchName,
            'dialplan_number' => null,
            'dialplan_destination' => 'false',
            'dialplan_context' => 'public',
            'dialplan_continue' => 'false',
            'dialplan_order' => 45,
            'dialplan_enabled' => 'true',
            'dialplan_description' => 'Route public ' . $this->recorderDestinationNumber($domain->domain_name) . ' calls to ' . $domain->domain_name,
            'dialplan_xml' => $this->dialplanService->buildXml($dialplan, $details),
            'insert_date' => $isNew ? now() : ($dialplan->insert_date ?? now()),
            'insert_user' => $isNew ? session('user_uuid') : $dialplan->insert_user,
            'update_date' => $isNew ? null : now(),
            'update_user' => $isNew ? null : session('user_uuid'),
        ])->save();

        $dialplan->dialplan_details()->delete();
        $this->createDetails($dialplan, $details);

        return $dialplan;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function srsRecorderDetails(string $domainName): array
    {
        $destinationExpression = $this->recorderDestinationExpression($domainName);

        return [
            ['dialplan_detail_tag' => 'condition', 'dialplan_detail_type' => 'destination_number', 'dialplan_detail_data' => $destinationExpression, 'dialplan_detail_break' => 'on-false', 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 0, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'process_cdr=false', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 0, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'rtp_secure_media=optional', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 30, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'conference_enter_sound=silence_stream://1', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 40, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'conference_exit_sound=silence_stream://1', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 50, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'conference_alone_sound=silence_stream://1', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 60, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'answer', 'dialplan_detail_data' => '', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 70, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'domain_name=' . $domainName, 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 80, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'caller_destination=${sip_h_X-Original-Dialed}', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 90, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'room_name=${sip_h_X-Room-Name}', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 100, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'record_append=true', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 110, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'call_direction=recorder', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 120, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'record_path=${recordings_dir}/${domain_name}/archive/${strftime(%Y)}/${strftime(%b)}/${strftime(%d)}', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 130, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'record_name=${room_name}-${sip_h_X-Primary-Session}.mp3', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 140, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'mkdir', 'dialplan_detail_data' => '${record_path}', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 150, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'conference_auto_record=${record_path}/${record_name}', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 0, 'dialplan_detail_order' => 160, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'condition', 'dialplan_detail_type' => '${sip_h_X-Is-New-Call}_${sip_h_X-Stream-ID}', 'dialplan_detail_data' => '^true_1$', 'dialplan_detail_break' => 'never', 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 10, 'dialplan_detail_order' => 10, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'set', 'dialplan_detail_data' => 'process_cdr=true', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => 'true', 'dialplan_detail_group' => 10, 'dialplan_detail_order' => 20, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'condition', 'dialplan_detail_type' => 'destination_number', 'dialplan_detail_data' => $destinationExpression, 'dialplan_detail_break' => 'on-false', 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 20, 'dialplan_detail_order' => 0, 'dialplan_detail_enabled' => 'true'],
            ['dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'conference', 'dialplan_detail_data' => '${room_name}@recorder+flags{nomoh|endconf}', 'dialplan_detail_break' => null, 'dialplan_detail_inline' => null, 'dialplan_detail_group' => 20, 'dialplan_detail_order' => 20, 'dialplan_detail_enabled' => 'true'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recorderCatchDetails(string $domainName): array
    {
        $destination = $this->recorderDestinationNumber($domainName);

        return [
            [
                'dialplan_detail_tag' => 'condition',
                'dialplan_detail_type' => 'destination_number',
                'dialplan_detail_data' => $this->recorderDestinationExpression($domainName),
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => 10,
                'dialplan_detail_enabled' => 'true',
            ],
            [
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => 'export',
                'dialplan_detail_data' => 'call_direction=inbound',
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => 'true',
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => 20,
                'dialplan_detail_enabled' => 'true',
            ],
            [
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => 'transfer',
                'dialplan_detail_data' => $destination . ' XML ' . $domainName,
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => 30,
                'dialplan_detail_enabled' => 'true',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    protected function createDetails(Dialplans $dialplan, array $details): void
    {
        foreach ($details as $detail) {
            DialplanDetails::create([
                'dialplan_detail_uuid' => (string) Str::uuid(),
                'domain_uuid' => $dialplan->domain_uuid,
                'dialplan_uuid' => $dialplan->dialplan_uuid,
                'dialplan_detail_tag' => $detail['dialplan_detail_tag'],
                'dialplan_detail_type' => $detail['dialplan_detail_type'],
                'dialplan_detail_data' => $detail['dialplan_detail_data'],
                'dialplan_detail_break' => $detail['dialplan_detail_break'] ?? null,
                'dialplan_detail_inline' => $detail['dialplan_detail_inline'] ?? null,
                'dialplan_detail_group' => $detail['dialplan_detail_group'],
                'dialplan_detail_order' => $detail['dialplan_detail_order'],
                'dialplan_detail_enabled' => $detail['dialplan_detail_enabled'] ?? 'true',
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }
    }

    protected function upsertRecorderConferenceProfile(): ConferenceProfile
    {
        $profile = ConferenceProfile::query()
            ->where('profile_name', self::CONFERENCE_PROFILE_NAME)
            ->first();

        $isNew = ! $profile;
        $profile ??= new ConferenceProfile();
        $profileUuid = $profile->conference_profile_uuid ?: (string) Str::uuid();

        $profile->forceFill([
            'conference_profile_uuid' => $profileUuid,
            'profile_name' => self::CONFERENCE_PROFILE_NAME,
            'profile_enabled' => 'true',
            'profile_description' => 'SIPREC recorder bridge profile (silence, wideband)',
            'insert_date' => $isNew ? now() : ($profile->insert_date ?? now()),
            'insert_user' => $isNew ? session('user_uuid') : $profile->insert_user,
            'update_date' => $isNew ? null : now(),
            'update_user' => $isNew ? null : session('user_uuid'),
        ])->save();

        ConferenceProfileParam::query()
            ->where('conference_profile_uuid', $profileUuid)
            ->delete();

        foreach ($this->recorderConferenceProfileParams() as $name => $value) {
            ConferenceProfileParam::create([
                'conference_profile_param_uuid' => (string) Str::uuid(),
                'conference_profile_uuid' => $profileUuid,
                'profile_param_name' => $name,
                'profile_param_value' => $value,
                'profile_param_enabled' => 'true',
                'profile_param_description' => null,
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }

        return $profile;
    }

    protected function disableRecorderConferenceProfile(): void
    {
        ConferenceProfile::query()
            ->where('profile_name', self::CONFERENCE_PROFILE_NAME)
            ->update([
                'profile_enabled' => 'false',
                'update_date' => now(),
                'update_user' => session('user_uuid'),
            ]);
    }

    protected function anyDomainHasRecorderEnabled(): bool
    {
        $domains = Domain::query()
            ->where('domain_enabled', 'true')
            ->pluck('domain_uuid');

        foreach ($domains as $domainUuid) {
            if ($this->isRecorderEnabledForDomain($domainUuid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function recorderConferenceProfileParams(): array
    {
        return [
            'domain' => '',
            'rate' => '48000',
            'interval' => '20',
            'energy-level' => '200',
            'comfort-noise' => 'false',
            'auto-gain-level' => '0',
            'caller-controls' => 'default',
            'moderator-controls' => 'moderator',
            'caller-id-name' => '',
            'caller-id-number' => '',
            'moh-sound' => 'silence',
            'enter-sound' => 'silence',
            'exit-sound' => 'silence',
            'alone-sound' => 'silence',
            'muted-sound' => 'silence',
            'unmuted-sound' => 'silence',
            'pin-sound' => 'silence',
            'bad-pin-sound' => 'silence',
            'locked-sound' => 'silence',
            'is-locked-sound' => 'silence',
            'is-unlocked-sound' => 'silence',
            'kicked-sound' => 'silence',
            'min-required-recording-participants' => '1',
        ];
    }

    protected function clearCaches(string $domainName): void
    {
        FusionCache::clear('dialplan:*');
        FusionCache::clear('dialplan:' . $domainName);
        FusionCache::clear('dialplan:public');
        FusionCache::clear('configuration:conference.conf');
        $this->dialplanService->clearDialplanCache('global');
        $this->dialplanService->clearDialplanCache('public');
    }
}
