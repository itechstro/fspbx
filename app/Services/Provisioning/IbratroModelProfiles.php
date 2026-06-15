<?php

namespace App\Services\Provisioning;

class IbratroModelProfiles
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

        if (str_contains($template, 'intrade video') || str_ends_with($template, '/video')) {
            return 'video';
        }

        if (str_contains($template, 'intrade advanced') || str_ends_with($template, '/advanced')) {
            return 'advanced';
        }

        if (str_contains($template, 'intrade standard') || str_ends_with($template, '/standard')) {
            return 'standard';
        }

        if (str_contains($template, 'intrade entry') || str_ends_with($template, '/entry')) {
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
        $fullKey = str_starts_with($key, 'ibratro_') ? $key : 'ibratro_' . $key;

        $settingValue = $settings[$fullKey] ?? null;
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
            'ibratro_use_vendor_class_id' => '1',
            'ibratro_audio_codec_sets' => 'G722,PCMU,PCMA,G729,opus,iLBC',
            'ibratro_rtp_port_quantity' => '200',
            'ibratro_h264_packet_mode' => '1',
            'ibratro_video_frame_rate' => '30',
            'ibratro_video_resolution' => '7',
            'ibratro_video_negotiate_dir' => '0',
            'ibratro_dtmf_mode' => '1',
            'ibratro_udp_update_ttl' => '30',
            'ibratro_user_is_phone_line1_only' => '0',
            'ibratro_subscribe_register' => '0',
            'ibratro_media_crypto' => '0',
            'ibratro_media_crypto_tls' => '1',
            'ibratro_populate_mwi' => '0',
            'ibratro_enable_failback' => '1',
            'ibratro_enable_xfer_back' => '1',
            'ibratro_caller_id_type' => '4',
            'ibratro_unregister_on_boot' => '0',
            'ibratro_enable_mac_header' => '0',
            'ibratro_enable_register_mac' => '0',
            'ibratro_greeting' => 'InTrade Video',
            'ibratro_lcd_contrast' => '5',
            'ibratro_enable_energy_saving' => '1',
            'ibratro_display_brightness_active' => '6',
            'ibratro_display_inactivity_time' => '30',
            'ibratro_default_ringtone' => 'Rigel.ogg',
            'ibratro_default_line_ringtone' => 'Default',
            'ibratro_caller_display_type' => '6',
            'ibratro_enable_mwi_tone' => '0',
            'ibratro_menu_password' => '',
            'ibratro_location' => '0',
            'ibratro_lldp_tx_enable' => '1',
            'ibratro_lldp_learn' => '1',
            'ibratro_cdp_enable' => '1',
            'ibratro_dss_long_press_action' => '1',
            'ibratro_auto_handle_video' => '0',
            'ibratro_enable_def_line' => '1',
            'ibratro_softkey_mode' => '0',
            'ibratro_softkey_exit' => '2',
            'ibratro_softkey_desktopsoftkey' => 'history;contact;dnd;menu;',
            'ibratro_softkey_talkingsoftkey' => 'video;xfer;end;conf;hold;new;mute;record;dialpad;',
            'ibratro_softkey_ringingsoftkey' => 'forward;audio;video;reject;',
            'ibratro_softkey_alertingsoftkey' => 'dialpad;xfer;cancel;',
            'ibratro_softkey_conferencesoftkey' => 'conf;dialpad;end;split;hold;mute;exit;',
            'ibratro_softkey_dialerpresoftkey' => 'audio;video;redial;',
            'ibratro_softkey_dialercallsoftkey' => 'audio;video;redial;',
            'ibratro_softkey_dialerxfersoftkey' => 'audio;video;xfer;contact;history;cancel;',
            'ibratro_softkey_dialercfwdsoftkey' => 'contact;history;forward;cancel;',
            'ibratro_softkey_xalertingsoftkey' => 'dialpad;xfer;cancel;',
            'ibratro_softkey_waitingsoftkey' => 'hold;xfer;conf;end;',
            'ibratro_softkey_endingsoftkey' => 'complete;autoRedial;end;redial;',
            'ibratro_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'ibratro_softkey_ringingclick' => 'none;none;none;none;none;',
            'ibratro_softkey_callclick' => 'pcall;ncall;voldown;volup;none;',
            'ibratro_softkey_desktoplongpress' => 'status;none;none;mwi;none;',
            'ibratro_softkey_dialerconfsoftkey' => 'audio;video;cancel;contact;history;redial;',
            'ibratro_softkey_desktopclick' => 'none;none;none;none;none;',
            'ibratro_timeout_to_screensaver' => '7200',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function entryDefaults(): array
    {
        return [
            'ibratro_enable_stun' => '0',
            'ibratro_stun_server' => '',
            'ibratro_stun_port' => '3478',
            'ibratro_country_toneset' => '11',
            'ibratro_audio_codec_sets' => 'PCMU,PCMA,G726-16,G726-24,G726-32,G726-40,G729,iLBC,opus,G722',
            'ibratro_line_audio_codec_map' => 'PCMU,PCMA,G726-32,G729,iLBC,G722',
            'ibratro_rtp_port_quantity' => '1000',
            'ibratro_dtmf_mode' => '3',
            'ibratro_udp_update_ttl' => '30',
            'ibratro_user_is_phone_line1_only' => '0',
            'ibratro_subscribe_register' => '0',
            'ibratro_enable_failback' => '0',
            'ibratro_populate_mwi' => '1',
            'ibratro_unregister_on_boot' => '0',
            'ibratro_enable_mac_header' => '0',
            'ibratro_enable_register_mac' => '0',
            'ibratro_media_crypto' => '0',
            'ibratro_media_crypto_tls' => '0',
            'ibratro_auto_tcp' => '1',
            'ibratro_enable_xfer_back' => '0',
            'ibratro_default_answer_mode' => '2',
            'ibratro_default_dial_mode' => '1',
            'ibratro_enable_def_line' => '1',
            'ibratro_default_ringtone' => 'Type 1',
            'ibratro_default_line_ringtone' => 'default',
            'ibratro_caller_display_type' => '5',
            'ibratro_caller_id_type' => '4',
            'ibratro_greeting' => 'InTrade Entry',
            'ibratro_lcd_contrast' => '5',
            'ibratro_enable_energy_saving' => '4',
            'ibratro_display_brightness_active' => '12',
            'ibratro_display_inactivity_time' => '45',
            'ibratro_display_call_duration' => '1',
            'ibratro_enable_mwi_tone' => '0',
            'ibratro_enable_diffserv' => '1',
            'ibratro_lldp_tx_enable' => '0',
            'ibratro_lldp_learn' => '0',
            'ibratro_cdp_enable' => '0',
            'ibratro_flash_protocol' => '5',
            'ibratro_flash_mode' => '1',
            'ibratro_dss_long_press_action' => '1',
            'ibratro_auto_blf_list' => '1',
            'ibratro_timeout_to_screensaver' => '0',
            'ibratro_softkey_mode' => '0',
            'ibratro_softkey_exit' => '2',
            'ibratro_softkey_desktopsoftkey' => 'history;contact;dnd;menu;',
            'ibratro_softkey_talkingsoftkey' => 'hold;xfer;conf;end;',
            'ibratro_softkey_ringingsoftkey' => 'accept;none;forward;reject;',
            'ibratro_softkey_alertingsoftkey' => 'end;none;none;none;',
            'ibratro_softkey_xalertingsoftkey' => 'end;none;none;xfer;',
            'ibratro_softkey_conferencesoftkey' => 'hold;none;split;end;',
            'ibratro_softkey_waitingsoftkey' => 'xfer;accept;reject;end;',
            'ibratro_softkey_endingsoftkey' => 'repeat;none;none;end;',
            'ibratro_softkey_dialerpresoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_dialercallsoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_dialerxfersoftkey' => 'delete;xfer;send;exit;',
            'ibratro_softkey_dialercfwdsoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_desktopclick' => 'history;status;none;none;none;',
            'ibratro_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'ibratro_softkey_ringingclick' => 'none;none;none;none;none;',
            'ibratro_softkey_callclick' => 'none;none;none;none;none;',
            'ibratro_softkey_desktoplongpress' => 'status;none;none;none;reset;',
            'ibratro_softkey_dialerconfsoftkey' => 'contact;clogs;redial;video;cancel;',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function standardDefaults(): array
    {
        return [
            'ibratro_enable_stun' => '0',
            'ibratro_stun_server' => '',
            'ibratro_stun_port' => '3478',
            'ibratro_country_toneset' => '11',
            'ibratro_audio_codec_sets' => 'G722,PCMU,PCMA,G729,opus,iLBC',
            'ibratro_dtmf_mode' => '3',
            'ibratro_udp_update_ttl' => '30',
            'ibratro_user_is_phone_line1_only' => '0',
            'ibratro_subscribe_register' => '0',
            'ibratro_enable_failback' => '1',
            'ibratro_populate_mwi' => '1',
            'ibratro_unregister_on_boot' => '1',
            'ibratro_enable_mac_header' => '1',
            'ibratro_enable_register_mac' => '1',
            'ibratro_media_crypto' => '0',
            'ibratro_media_crypto_tls' => '0',
            'ibratro_auto_tcp' => '0',
            'ibratro_enable_xfer_back' => '0',
            'ibratro_default_answer_mode' => '1',
            'ibratro_default_dial_mode' => '1',
            'ibratro_enable_def_line' => '0',
            'ibratro_auto_onhook' => '1',
            'ibratro_auto_onhook_time' => '3',
            'ibratro_ring_timeout' => '120',
            'ibratro_call_timeout' => '120',
            'ibratro_video_display_mode' => '0',
            'ibratro_auto_handle_video' => '0',
            'ibratro_default_ringtone' => '2.wav',
            'ibratro_default_line_ringtone' => 'Default',
            'ibratro_caller_display_type' => '5',
            'ibratro_caller_id_type' => '4',
            'ibratro_greeting' => 'InTrade',
            'ibratro_lcd_contrast' => '5',
            'ibratro_enable_energy_saving' => '4',
            'ibratro_display_brightness_active' => '12',
            'ibratro_display_inactivity_time' => '60',
            'ibratro_enable_mwi_tone' => '0',
            'ibratro_menu_password' => '',
            'ibratro_location' => '0',
            'ibratro_enable_diffserv' => '0',
            'ibratro_lldp_tx_enable' => '1',
            'ibratro_lldp_learn' => '1',
            'ibratro_cdp_enable' => '1',
            'ibratro_flash_protocol' => '2',
            'ibratro_flash_mode' => '0',
            'ibratro_dss_long_press_action' => '1',
            'ibratro_dss_timeout_to_home' => '90',
            'ibratro_side_key_pages' => '3',
            'ibratro_auto_blf_list' => '1',
            'ibratro_softkey_mode' => '1',
            'ibratro_softkey_exit' => '2',
            'ibratro_softkey_desktopsoftkey' => 'history;contact;dnd;menu;',
            'ibratro_softkey_talkingsoftkey' => 'hold;xfer;conf;end;',
            'ibratro_softkey_ringingsoftkey' => 'accept;none;forward;reject;',
            'ibratro_softkey_alertingsoftkey' => 'end;none;none;none;',
            'ibratro_softkey_xalertingsoftkey' => 'end;none;none;xfer;',
            'ibratro_softkey_conferencesoftkey' => 'hold;manage;conf;split;end;',
            'ibratro_softkey_waitingsoftkey' => 'xfer;accept;reject;end;',
            'ibratro_softkey_endingsoftkey' => 'redial;none;none;end;',
            'ibratro_softkey_dialerpresoftkey' => 'send;save;delete;end;',
            'ibratro_softkey_dialercallsoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_dialerxfersoftkey' => 'delete;xfer;send;exit;',
            'ibratro_softkey_dialercfwdsoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_calllogsoftkey' => 'exit;option;delete;send;',
            'ibratro_softkey_desktopclick' => 'history;status;none;none;menu;',
            'ibratro_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'ibratro_softkey_ringingclick' => 'none;none;none;none;none;',
            'ibratro_softkey_callclick' => 'none;none;voldown;volup;none;',
            'ibratro_softkey_desktoplongpress' => 'status;none;none;none;reset;',
            'ibratro_softkey_dialerconfsoftkey' => 'contact;clogs;redial;video;cancel;',
            'ibratro_timeout_to_screensaver' => '7200',
            'ibratro_timeout_to_power_saving' => '14400',
            'ibratro_screensaver_type' => '0',
            'ibratro_power_saving' => '1',
            'ibratro_display_language' => 'en;cn;tc;ru;it;fr;de;he;es;cat;eus;gal;tr;hr;slo;cz;nl;ko;ua;pt;pl;ar;jp;kr;fa;kz;',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function advancedDefaults(): array
    {
        return [
            'ibratro_audio_codec_sets' => 'G722,opus,PCMU,PCMA,G729,iLBC',
            'ibratro_rtp_port_quantity' => '1000',
            'ibratro_video_frame_rate' => '25',
            'ibratro_video_resolution' => '6',
            'ibratro_video_negotiate_dir' => '2',
            'ibratro_dtmf_mode' => '3',
            'ibratro_udp_update_ttl' => '15',
            'ibratro_subscribe_register' => '0',
            'ibratro_enable_xfer_back' => '0',
            'ibratro_unregister_on_boot' => '1',
            'ibratro_enable_mac_header' => '1',
            'ibratro_enable_register_mac' => '1',
            'ibratro_populate_mwi' => '1',
            'ibratro_user_is_phone_line1_only' => '1',
            'ibratro_greeting' => 'InTrade Advanced',
            'ibratro_lcd_contrast' => '5',
            'ibratro_enable_energy_saving' => '1',
            'ibratro_display_inactivity_time' => '60',
            'ibratro_caller_display_type' => '5',
            'ibratro_default_ringtone' => '2.wav',
            'ibratro_default_line_ringtone' => 'Default',
            'ibratro_location' => '0',
            'ibratro_lldp_tx_enable' => '1',
            'ibratro_lldp_learn' => '1',
            'ibratro_cdp_enable' => '1',
            'ibratro_dss_long_press_action' => '3',
            'ibratro_dss_timeout_to_home' => '90',
            'ibratro_bluetooth_ring_mode' => '1',
            'ibratro_power_saving' => '1',
            'ibratro_screensaver_type' => '1',
            'ibratro_softkey_mode' => '0',
            'ibratro_softkey_talkingsoftkey' => 'hold;xfer;conf;end;',
            'ibratro_softkey_talkingvideosoftkey' => 'hold;xfer;switch;end;',
            'ibratro_softkey_ringingsoftkey' => 'accept;none;forward;reject;',
            'ibratro_softkey_alertingsoftkey' => 'end;none;none;none;',
            'ibratro_softkey_xalertingsoftkey' => 'end;none;none;xfer;',
            'ibratro_softkey_conferencesoftkey' => 'hold;manage;conf;split;end;',
            'ibratro_softkey_waitingsoftkey' => 'xfer;accept;reject;end;',
            'ibratro_softkey_endingsoftkey' => 'redial;none;none;end;',
            'ibratro_softkey_dialerpresoftkey' => 'audio;video;save;exit;',
            'ibratro_softkey_dialercallsoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_dialerxfersoftkey' => 'delete;xfer;send;exit;',
            'ibratro_softkey_dialercfwdsoftkey' => 'send;2aB;delete;exit;',
            'ibratro_softkey_calllogsoftkey' => 'exit;option;delete;dial;',
            'ibratro_softkey_dailerclick' => 'pline;nline;none;none;none;',
            'ibratro_softkey_ringingclick' => 'none;none;none;none;none;',
            'ibratro_softkey_callclick' => 'none;none;voldown;volup;none;',
            'ibratro_softkey_desktoplongpress' => 'status;none;none;none;reset;',
            'ibratro_softkey_dialerconfsoftkey' => 'contact;clogs;redial;video;cancel;',
            'ibratro_video_display_mode' => '3',
            'ibratro_auto_handle_video' => '1',
        ];
    }
}
