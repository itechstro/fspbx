<?php

namespace App\Services\Provisioning;

use App\Models\Devices;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\MobileAppUsers;
use App\Models\Voicemails;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TalkPhoneProvisionService
{
    public function payloadForUuid(string $deviceUuid): array
    {
        $mobileApp = MobileAppUsers::query()
            ->where('mobile_app_user_uuid', $deviceUuid)
            ->where('provider', 'talkphone')
            ->first();
        if ($mobileApp) {
            return $this->payloadForMobileApp($mobileApp);
        }

        $device = Devices::query()->where('device_uuid', $deviceUuid)->first();
        if (! $device) {
            throw new TalkPhoneProvisionException('TalkPhone device not found.');
        }

        return $this->payload($device);
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForMobileApp(MobileAppUsers $user): array
    {
        if ((int) $user->status !== 1) {
            throw new TalkPhoneProvisionException('TalkPhone is not enabled for this extension.');
        }

        $extension = Extensions::query()->whereKey($user->extension_uuid)->first();
        if (! $extension) {
            throw new TalkPhoneProvisionException('The extension for this TalkPhone was not found.');
        }

        $sipUri = trim((string) $extension->extension);
        $password = trim((string) $extension->password);
        $displayName = trim((string) $extension->effective_caller_id_name);
        $server = (string) Domain::query()
            ->where('domain_uuid', $user->domain_uuid)
            ->value('domain_name');
        $mac = TalkPhoneSettings::normalizeMac((string) $user->device_mac);

        if ($sipUri === '' || $password === '' || $server === '' || $mac === null) {
            throw new TalkPhoneProvisionException(
                'The extension needs a number, SIP password, server, and MAC address.'
            );
        }

        $stored = is_array($user->settings) ? $user->settings : [];
        $settings = TalkPhoneSettings::resolve($stored, $this->domainSettings((string) $user->domain_uuid));

        return TalkPhoneSettings::payload($displayName, $sipUri, $password, $server, $mac, $settings);
    }

    public function provisionUrlForMobileApp(MobileAppUsers $user): string
    {
        return url('/prov/talkphone/'.$user->mobile_app_user_uuid.'.json');
    }

    /**
     * @return list<string>
     */
    public function recipientEmailsForExtension(Extensions $extension): array
    {
        $raw = Voicemails::query()
            ->where('domain_uuid', $extension->domain_uuid)
            ->where('voicemail_id', $extension->extension)
            ->value('voicemail_mail_to');

        return $this->emailList($raw);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Devices $device): array
    {
        $this->assertTalkPhone($device);

        $line = $device->lines()
            ->orderBy('line_number')
            ->get()
            ->first(fn ($line) => $this->lineEnabled($line->enabled ?? 'true'));

        if (! $line) {
            throw new TalkPhoneProvisionException('Assign an extension line before provisioning.');
        }

        $extensionNumber = trim((string) ($line->auth_id ?: $line->user_id));
        $extension = $extensionNumber === '' ? null : Extensions::query()
            ->where('domain_uuid', $device->domain_uuid)
            ->where('extension', $extensionNumber)
            ->first();

        $sipUri = trim((string) ($line->user_id ?: $line->auth_id ?: $extension?->extension));
        // The extension password is what FreeSWITCH authenticates. The line
        // copy is only a fallback for a line that is not a local extension.
        $password = trim((string) ($extension?->password ?? ''));
        if ($password === '') {
            $password = (string) ($line->password ?? '');
        }
        $displayName = trim((string) ($line->display_name ?: $extension?->effective_caller_id_name ?: ''));
        $server = trim((string) ($line->server_address ?: ''));
        if ($server === '') {
            $server = (string) Domain::query()
                ->where('domain_uuid', $device->domain_uuid)
                ->value('domain_name');
        }

        if ($sipUri === '' || $password === '' || $server === '') {
            throw new TalkPhoneProvisionException(
                'The line needs an extension, SIP password, and server address.'
            );
        }

        $settings = TalkPhoneSettings::resolve(
            TalkPhoneSettings::mapDeviceSettings($device->settings()->get()),
            $this->domainSettings((string) $device->domain_uuid)
        );

        $mac = TalkPhoneSettings::normalizeMac((string) $device->device_address);
        if ($mac === null) {
            throw new TalkPhoneProvisionException(
                'Save a MAC address on this device before provisioning. TalkPhone uses it to download the phone book.'
            );
        }

        return TalkPhoneSettings::payload($displayName, $sipUri, $password, $server, $mac, $settings);
    }

    public function provisionUrl(Devices $device): string
    {
        return url('/prov/talkphone/'.$device->device_uuid.'.json');
    }

    public function allocateMac(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $mac = TalkPhoneSettings::generateMac();
            $taken = Devices::query()->where('device_address', $mac)->exists()
                || MobileAppUsers::query()->where('device_mac', $mac)->exists();
            if (! $taken) {
                return $mac;
            }
        }

        return TalkPhoneSettings::generateMac();
    }

    public function qrPng(string $contents): string
    {
        return (string) QrCode::format('png')->size(280)->margin(1)->generate($contents);
    }

    /**
     * @return list<string>
     */
    public function recipientEmails(Devices $device): array
    {
        $this->assertTalkPhone($device);

        $line = $device->lines()->orderBy('line_number')->first();
        $extensionNumber = trim((string) ($line?->auth_id ?: $line?->user_id));
        if ($extensionNumber === '') {
            return [];
        }

        $raw = Voicemails::query()
            ->where('domain_uuid', $device->domain_uuid)
            ->where('voicemail_id', $extensionNumber)
            ->value('voicemail_mail_to');

        return $this->emailList($raw);
    }

    /**
     * @return list<string>
     */
    private function emailList(mixed $raw): array
    {
        $emails = [];
        foreach (preg_split('/[;,]/', (string) $raw) ?: [] as $part) {
            $email = trim($part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<string, mixed>
     */
    private function domainSettings(string $domainUuid): array
    {
        $settings = [];
        foreach (TalkPhoneSettings::DEVICE_KEYS as $key) {
            $name = 'talkphone_'.$key;
            $value = get_domain_setting($name, $domainUuid);
            if ($value !== null) {
                $settings[$name] = $value;
            }
        }

        return $settings;
    }

    private function assertTalkPhone(Devices $device): void
    {
        if (strtolower((string) $device->device_vendor) !== 'talkphone') {
            throw new TalkPhoneProvisionException('This device is not a TalkPhone.');
        }
        if (! $this->lineEnabled($device->device_enabled)) {
            throw new TalkPhoneProvisionException('This TalkPhone device is disabled.');
        }
    }

    private function lineEnabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized === '' || in_array($normalized, ['true', '1', 't'], true);
    }
}
