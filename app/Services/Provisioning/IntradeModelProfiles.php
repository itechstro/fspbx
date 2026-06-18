<?php

namespace App\Services\Provisioning;

class IntradeModelProfiles
{
    /**
     * @return array<string, string>
     */
    public static function defaults(string $profile): array
    {
        return match ($profile) {
            'entry' => self::entryDefaults(),
            'standard' => self::standardDefaults(),
            'video' => self::videoDefaults(),
            'advanced' => self::advancedDefaults(),
            default => [],
        };
    }

    public static function profileForTemplate(?string $template): string
    {
        $template = strtolower((string) $template);

        if (str_ends_with($template, '/video') || str_contains($template, 'intrade video')) {
            return 'video';
        }

        if (str_ends_with($template, '/advanced') || str_contains($template, 'intrade advanced')) {
            return 'advanced';
        }

        if (str_ends_with($template, '/standard') || str_contains($template, 'intrade standard')) {
            return 'standard';
        }

        if (str_ends_with($template, '/entry') || str_contains($template, 'intrade entry')) {
            return 'entry';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function mergeProfileDefaults(string $profile, array $settings): array
    {
        if ($profile === '') {
            return $settings;
        }

        foreach (self::defaults($profile) as $key => $value) {
            if (! isset($settings[$key]) || $settings[$key] === '' || $settings[$key] === null) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    public static function resolve(string $profile, array $settings, string $key, string $fallback = ''): string
    {
        $fullKey = str_starts_with($key, 'intrade_') ? $key : 'intrade_' . $key;

        $legacyKey = 'ibratro_' . (str_starts_with($key, 'intrade_') ? substr($key, 8) : $key);
        $settingValue = $settings[$fullKey] ?? $settings[$legacyKey] ?? null;
        if ($settingValue !== null && $settingValue !== '') {
            return (string) $settingValue;
        }

        $profileDefaults = self::defaults($profile);
        if (array_key_exists($fullKey, $profileDefaults)) {
            return (string) $profileDefaults[$fullKey];
        }

        return $fallback;
    }

    /**
     * @return array<string, string>
     */
    private static function videoDefaults(): array
    {
        return [
            'intrade_use_vendor_class_id' => '1',
            'intrade_audio_codec_sets' => 'G722,PCMU,PCMA,G729,opus,iLBC',
            'intrade_rtp_port_quantity' => '200',
            'intrade_h264_packet_mode' => '1',
            'intrade_video_frame_rate' => '30',
            'intrade_video_resolution' => '7',
            'intrade_video_negotiate_dir' => '0',
            'intrade_dtmf_mode' => '1',
            'intrade_udp_update_ttl' => '30',
            'intrade_user_is_phone_line1_only' => '0',
            'intrade_subscribe_register' => '0',
            'intrade_media_crypto' => '0',
            'intrade_media_crypto_tls' => '1',
            'intrade_populate_mwi' => '0',
            'intrade_enable_failback' => '1',
            'intrade_enable_xfer_back' => '1',
            'intrade_caller_id_type' => '4',
            'intrade_unregister_on_boot' => '0',
            'intrade_enable_mac_header' => '0',
            'intrade_enable_register_mac' => '0',
            'intrade_greeting' => 'InTrade Video',
            'intrade_lcd_contrast' => '5',
            'intrade_enable_energy_saving' => '1',
            'intrade_display_brightness_active' => '6',
            'intrade_display_inactivity_time' => '30',
            'intrade_default_ringtone' => 'Rigel.ogg',
            'intrade_default_line_ringtone' => 'Default',
            'intrade_caller_display_type' => '6',
            'intrade_enable_mwi_tone' => '0',
            'intrade_menu_password' => '',
            'intrade_location' => '0',
            'intrade_lldp_tx_enable' => '1',
            'intrade_lldp_learn' => '1',
            'intrade_cdp_enable' => '1',
            'intrade_dss_long_press_action' => '1',
            'intrade_auto_handle_video' => '0',
            'intrade_enable_def_line' => '1',
            'intrade_softkey_mode' => '0',
            'intrade_softkey_exit' => '2',
            'intrade_softkey_desktopsoftkey' => 'history;contact;dnd;menu;',
            'intrade_softkey_talkingsoftkey' => 'video;xfer;end;conf;hold;new;mute;record;dialpad;',
            'intrade_softkey_ringingsoftkey' => 'forward;audio;video;reject;',
            'intrade_softkey_alertingsoftkey' => 'dialpad;xfer;cancel;',
            'intrade_softkey_conferencesoftkey' => 'conf;dialpad;end;split;hold;mute;exit;',
            'intrade_softkey_dialerpresoftkey' => 'audio;video;redial;',
            'intrade_softkey_dialercallsoftkey' => 'audio;video;redial;',
            'intrade_softkey_dialerxfersoftkey' => 'audio;video;xfer;contact;history;cancel;',
            'intrade_softkey_dialercfwdsoftkey' => 'contact;history;forward;cancel;',
            'intrade_softkey_xalertingsoftkey' => 'dialpad;xfer;cancel;',
            'intrade_softkey_waitingsoftkey' => 'hold;xfer;conf;end;',
            'intrade_softkey_endingsoftkey' => 'complete;autoRedial;end;redial;',
            'intrade_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'intrade_softkey_ringingclick' => 'none;none;none;none;none;',
            'intrade_softkey_callclick' => 'pcall;ncall;voldown;volup;none;',
            'intrade_softkey_desktoplongpress' => 'status;none;none;mwi;none;',
            'intrade_softkey_dialerconfsoftkey' => 'audio;video;cancel;contact;history;redial;',
            'intrade_softkey_desktopclick' => 'none;none;none;none;none;',
            'intrade_timeout_to_screensaver' => '7200',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function entryDefaults(): array
    {
        return [
            'intrade_enable_stun' => '0',
            'intrade_stun_server' => '',
            'intrade_stun_port' => '3478',
            'intrade_country_toneset' => '11',
            'intrade_audio_codec_sets' => 'PCMU,PCMA,G726-16,G726-24,G726-32,G726-40,G729,iLBC,opus,G722',
            'intrade_line_audio_codec_map' => 'PCMU,PCMA,G726-32,G729,iLBC,G722',
            'intrade_rtp_port_quantity' => '1000',
            'intrade_dtmf_mode' => '3',
            'intrade_udp_update_ttl' => '30',
            'intrade_user_is_phone_line1_only' => '0',
            'intrade_subscribe_register' => '0',
            'intrade_enable_failback' => '0',
            'intrade_populate_mwi' => '1',
            'intrade_unregister_on_boot' => '0',
            'intrade_enable_mac_header' => '0',
            'intrade_enable_register_mac' => '0',
            'intrade_media_crypto' => '0',
            'intrade_media_crypto_tls' => '0',
            'intrade_auto_tcp' => '1',
            'intrade_enable_xfer_back' => '0',
            'intrade_default_answer_mode' => '2',
            'intrade_default_dial_mode' => '1',
            'intrade_enable_def_line' => '1',
            'intrade_default_ringtone' => 'Type 1',
            'intrade_default_line_ringtone' => 'default',
            'intrade_caller_display_type' => '5',
            'intrade_caller_id_type' => '4',
            'intrade_greeting' => 'InTrade Entry',
            'intrade_lcd_contrast' => '5',
            'intrade_enable_energy_saving' => '4',
            'intrade_display_brightness_active' => '12',
            'intrade_display_inactivity_time' => '45',
            'intrade_display_call_duration' => '1',
            'intrade_enable_mwi_tone' => '0',
            'intrade_enable_diffserv' => '1',
            'intrade_lldp_tx_enable' => '0',
            'intrade_lldp_learn' => '0',
            'intrade_cdp_enable' => '0',
            'intrade_flash_protocol' => '5',
            'intrade_flash_mode' => '1',
            'intrade_dss_long_press_action' => '1',
            'intrade_auto_blf_list' => '1',
            'intrade_timeout_to_screensaver' => '0',
            'intrade_softkey_mode' => '0',
            'intrade_softkey_exit' => '2',
            'intrade_softkey_desktopsoftkey' => 'history;contact;dnd;menu;',
            'intrade_softkey_talkingsoftkey' => 'hold;xfer;conf;end;',
            'intrade_softkey_ringingsoftkey' => 'accept;none;forward;reject;',
            'intrade_softkey_alertingsoftkey' => 'end;none;none;none;',
            'intrade_softkey_xalertingsoftkey' => 'end;none;none;xfer;',
            'intrade_softkey_conferencesoftkey' => 'hold;none;split;end;',
            'intrade_softkey_waitingsoftkey' => 'xfer;accept;reject;end;',
            'intrade_softkey_endingsoftkey' => 'repeat;none;none;end;',
            'intrade_softkey_dialerpresoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_dialercallsoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_dialerxfersoftkey' => 'delete;xfer;send;exit;',
            'intrade_softkey_dialercfwdsoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_desktopclick' => 'history;status;none;none;none;',
            'intrade_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'intrade_softkey_ringingclick' => 'none;none;none;none;none;',
            'intrade_softkey_callclick' => 'none;none;none;none;none;',
            'intrade_softkey_desktoplongpress' => 'status;none;none;none;reset;',
            'intrade_softkey_dialerconfsoftkey' => 'contact;clogs;redial;video;cancel;',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function standardDefaults(): array
    {
        return [
            'intrade_enable_stun' => '0',
            'intrade_stun_server' => '',
            'intrade_stun_port' => '3478',
            'intrade_country_toneset' => '11',
            'intrade_audio_codec_sets' => 'G722,PCMU,PCMA,G729,opus,iLBC',
            'intrade_dtmf_mode' => '3',
            'intrade_udp_update_ttl' => '30',
            'intrade_user_is_phone_line1_only' => '0',
            'intrade_subscribe_register' => '0',
            'intrade_enable_failback' => '1',
            'intrade_populate_mwi' => '1',
            'intrade_unregister_on_boot' => '1',
            'intrade_enable_mac_header' => '1',
            'intrade_enable_register_mac' => '1',
            'intrade_media_crypto' => '0',
            'intrade_media_crypto_tls' => '0',
            'intrade_auto_tcp' => '0',
            'intrade_enable_xfer_back' => '0',
            'intrade_default_answer_mode' => '1',
            'intrade_default_dial_mode' => '1',
            'intrade_enable_def_line' => '0',
            'intrade_auto_onhook' => '1',
            'intrade_auto_onhook_time' => '3',
            'intrade_ring_timeout' => '120',
            'intrade_call_timeout' => '120',
            'intrade_video_display_mode' => '0',
            'intrade_auto_handle_video' => '0',
            'intrade_default_ringtone' => '2.wav',
            'intrade_default_line_ringtone' => 'Default',
            'intrade_caller_display_type' => '5',
            'intrade_caller_id_type' => '4',
            'intrade_greeting' => 'InTrade',
            'intrade_lcd_contrast' => '5',
            'intrade_enable_energy_saving' => '4',
            'intrade_display_brightness_active' => '12',
            'intrade_display_inactivity_time' => '60',
            'intrade_enable_mwi_tone' => '0',
            'intrade_menu_password' => '',
            'intrade_location' => '0',
            'intrade_enable_diffserv' => '0',
            'intrade_lldp_tx_enable' => '1',
            'intrade_lldp_learn' => '1',
            'intrade_cdp_enable' => '1',
            'intrade_flash_protocol' => '2',
            'intrade_flash_mode' => '0',
            'intrade_dss_long_press_action' => '1',
            'intrade_dss_timeout_to_home' => '90',
            'intrade_side_key_pages' => '3',
            'intrade_auto_blf_list' => '1',
            'intrade_softkey_mode' => '1',
            'intrade_softkey_exit' => '2',
            'intrade_softkey_desktopsoftkey' => 'history;contact;dnd;menu;',
            'intrade_softkey_talkingsoftkey' => 'hold;xfer;conf;end;',
            'intrade_softkey_ringingsoftkey' => 'accept;none;forward;reject;',
            'intrade_softkey_alertingsoftkey' => 'end;none;none;none;',
            'intrade_softkey_xalertingsoftkey' => 'end;none;none;xfer;',
            'intrade_softkey_conferencesoftkey' => 'hold;manage;conf;split;end;',
            'intrade_softkey_waitingsoftkey' => 'xfer;accept;reject;end;',
            'intrade_softkey_endingsoftkey' => 'redial;none;none;end;',
            'intrade_softkey_dialerpresoftkey' => 'send;save;delete;end;',
            'intrade_softkey_dialercallsoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_dialerxfersoftkey' => 'delete;xfer;send;exit;',
            'intrade_softkey_dialercfwdsoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_calllogsoftkey' => 'exit;option;delete;send;',
            'intrade_softkey_desktopclick' => 'history;status;none;none;menu;',
            'intrade_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'intrade_softkey_ringingclick' => 'none;none;none;none;none;',
            'intrade_softkey_callclick' => 'none;none;voldown;volup;none;',
            'intrade_softkey_desktoplongpress' => 'status;none;none;none;reset;',
            'intrade_softkey_dialerconfsoftkey' => 'contact;clogs;redial;video;cancel;',
            'intrade_timeout_to_screensaver' => '7200',
            'intrade_timeout_to_power_saving' => '14400',
            'intrade_screensaver_type' => '0',
            'intrade_power_saving' => '1',
            'intrade_display_language' => 'en;cn;tc;ru;it;fr;de;he;es;cat;eus;gal;tr;hr;slo;cz;nl;ko;ua;pt;pl;ar;jp;kr;fa;kz;',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function advancedDefaults(): array
    {
        return [
            'intrade_audio_codec_sets' => 'G722,opus,PCMU,PCMA,G729,iLBC',
            'intrade_rtp_port_quantity' => '1000',
            'intrade_video_frame_rate' => '25',
            'intrade_video_resolution' => '6',
            'intrade_video_negotiate_dir' => '2',
            'intrade_dtmf_mode' => '3',
            'intrade_udp_update_ttl' => '15',
            'intrade_subscribe_register' => '0',
            'intrade_enable_xfer_back' => '0',
            'intrade_unregister_on_boot' => '1',
            'intrade_enable_mac_header' => '1',
            'intrade_enable_register_mac' => '1',
            'intrade_populate_mwi' => '1',
            'intrade_user_is_phone_line1_only' => '1',
            'intrade_greeting' => 'InTrade Advanced',
            'intrade_lcd_contrast' => '5',
            'intrade_enable_energy_saving' => '1',
            'intrade_display_inactivity_time' => '60',
            'intrade_caller_display_type' => '5',
            'intrade_default_ringtone' => '2.wav',
            'intrade_default_line_ringtone' => 'Default',
            'intrade_location' => '0',
            'intrade_lldp_tx_enable' => '1',
            'intrade_lldp_learn' => '1',
            'intrade_cdp_enable' => '1',
            'intrade_flash_protocol' => '5',
            'intrade_flash_mode' => '1',
            'intrade_dss_long_press_action' => '3',
            'intrade_dss_timeout_to_home' => '90',
            'intrade_bluetooth_ring_mode' => '1',
            'intrade_power_saving' => '1',
            'intrade_screensaver_type' => '1',
            'intrade_softkey_mode' => '0',
            'intrade_softkey_talkingsoftkey' => 'hold;xfer;conf;end;',
            'intrade_softkey_talkingvideosoftkey' => 'hold;xfer;switch;end;',
            'intrade_softkey_ringingsoftkey' => 'accept;none;forward;reject;',
            'intrade_softkey_alertingsoftkey' => 'end;none;none;none;',
            'intrade_softkey_xalertingsoftkey' => 'end;none;none;xfer;',
            'intrade_softkey_conferencesoftkey' => 'hold;manage;conf;split;end;',
            'intrade_softkey_waitingsoftkey' => 'xfer;accept;reject;end;',
            'intrade_softkey_endingsoftkey' => 'redial;none;none;end;',
            'intrade_softkey_dialerpresoftkey' => 'audio;video;save;exit;',
            'intrade_softkey_dialercallsoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_dialerxfersoftkey' => 'delete;xfer;send;exit;',
            'intrade_softkey_dialercfwdsoftkey' => 'send;2aB;delete;exit;',
            'intrade_softkey_calllogsoftkey' => 'exit;option;delete;dial;',
            'intrade_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'intrade_softkey_ringingclick' => 'none;none;none;none;none;',
            'intrade_softkey_callclick' => 'none;none;voldown;volup;none;',
            'intrade_softkey_desktoplongpress' => 'status;none;none;none;reset;',
            'intrade_softkey_dialerconfsoftkey' => 'contact;clogs;redial;video;cancel;',
            'intrade_video_display_mode' => '3',
            'intrade_auto_handle_video' => '1',
        ];
    }
}
