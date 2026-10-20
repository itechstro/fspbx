<?php

namespace Tests\Unit;

use App\Services\Provisioning\TalkPhoneSettings;
use PHPUnit\Framework\TestCase;

class TalkPhoneSettingsTest extends TestCase
{
    public function test_defaults_when_nothing_is_set(): void
    {
        $settings = TalkPhoneSettings::resolve([], []);

        $this->assertSame('WSS', $settings['transport']);
        $this->assertSame(7443, $settings['port']);
        $this->assertSame('', $settings['wsPath']);
        $this->assertSame('SRTP', $settings['mediaTransport']);
        $this->assertSame('*97', $settings['voicemailNumber']);
        $this->assertSame(['opus', 'G722', 'PCMU', 'PCMA'], $settings['codecs']);
        $this->assertFalse($settings['allowBadCertificate']);
        $this->assertSame(600, $settings['registerExpires']);
    }

    public function test_device_settings_override_domain_settings(): void
    {
        $settings = TalkPhoneSettings::resolve(
            [
                'transport' => 'tcp',
                'port' => '5060',
                'codecs' => 'PCMU, opus',
                'voicemail_number' => '',
            ],
            [
                'talkphone_transport' => 'TLS',
                'talkphone_port' => '5061',
                'talkphone_codecs' => 'opus',
                'talkphone_voicemail_number' => '*98',
                'talkphone_ws_path' => 'ws',
            ]
        );

        $this->assertSame('TCP', $settings['transport']);
        $this->assertSame(5060, $settings['port']);
        $this->assertSame(['PCMU', 'opus'], $settings['codecs']);
        $this->assertSame('', $settings['voicemailNumber']);
        $this->assertSame('/ws', $settings['wsPath']);
    }

    public function test_blank_device_values_do_not_hide_domain_settings(): void
    {
        $settings = TalkPhoneSettings::resolve(
            [
                'transport' => '',
                'port' => ' ',
                'codecs' => '',
                'media_transport' => '',
            ],
            [
                'talkphone_transport' => 'TLS',
                'talkphone_port' => '5061',
                'talkphone_codecs' => 'opus,PCMU',
                'talkphone_media_transport' => 'rtp',
            ]
        );

        $this->assertSame('TLS', $settings['transport']);
        $this->assertSame(5061, $settings['port']);
        $this->assertSame(['opus', 'PCMU'], $settings['codecs']);
        $this->assertSame('RTP', $settings['mediaTransport']);
    }

    public function test_domain_settings_apply_when_the_device_has_none(): void
    {
        $settings = TalkPhoneSettings::resolve([], [
            'talkphone_transport' => 'WS',
            'talkphone_media_transport' => 'rtp',
            'talkphone_allow_bad_certificate' => 'true',
            'talkphone_register_expires' => '1200',
        ]);

        $this->assertSame('WS', $settings['transport']);
        $this->assertSame(5066, $settings['port']);
        $this->assertSame('RTP', $settings['mediaTransport']);
        $this->assertTrue($settings['allowBadCertificate']);
        $this->assertSame(1200, $settings['registerExpires']);
    }

    public function test_payload_shape(): void
    {
        $payload = TalkPhoneSettings::payload('Jane Doe', '1001', 'secret', 'pbx.example.com', '0a65297198ba', TalkPhoneSettings::resolve([], []));

        $this->assertSame(1, $payload['v']);
        $this->assertSame('talkphone', $payload['app']);
        $this->assertSame('Jane Doe', $payload['displayName']);
        $this->assertSame('1001', $payload['sipUri']);
        $this->assertSame('secret', $payload['password']);
        $this->assertSame('pbx.example.com', $payload['server']);
        $this->assertSame('fspbx', $payload['pbxType']);
        $this->assertSame(['opus', 'G722', 'PCMU', 'PCMA'], $payload['codecs']);
        $this->assertSame('0a65297198ba', $payload['mac']);
    }

    public function test_generated_mac_is_a_local_unicast_address(): void
    {
        $mac = TalkPhoneSettings::generateMac();

        $this->assertSame(12, strlen($mac));
        $this->assertSame($mac, TalkPhoneSettings::normalizeMac($mac));
        $octet = hexdec(substr($mac, 0, 2));
        $this->assertSame(0x02, $octet & 0x03);
        $this->assertNull(TalkPhoneSettings::normalizeMac(''));
        $this->assertSame('00a85a00fa91', TalkPhoneSettings::normalizeMac('00:A8:5A:00:FA:91'));
    }

    public function test_disabled_device_settings_are_ignored(): void
    {
        $map = TalkPhoneSettings::mapDeviceSettings([
            (object) [
                'device_setting_subcategory' => 'transport',
                'device_setting_value' => 'TLS',
                'device_setting_enabled' => 'false',
            ],
            (object) [
                'device_setting_subcategory' => 'port',
                'device_setting_value' => '5061',
                'device_setting_enabled' => 'true',
            ],
        ]);

        $settings = TalkPhoneSettings::resolve($map, []);

        $this->assertSame('WSS', $settings['transport']);
        $this->assertSame(5061, $settings['port']);
    }
}
