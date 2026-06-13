<?php

namespace App\Services\Provisioning;

class IbratroProvisionSettings
{
    /**
     * Default Settings rows for category provision / subcategory ibratro_*.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return array_merge(
            self::generalSettings(),
            self::networkSettings(),
            self::mediaSettings(),
            self::sipGlobalSettings(),
            self::sipLineSettings(),
            self::callFeatureSettings(),
            self::phoneSettings(),
            self::directorySettings(),
            self::dssKeySettings(),
            self::autoprovisionSettings(),
            self::qosSettings(),
            self::uiSettings(),
            self::perLineRingtoneSettings(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function generalSettings(): array
    {
        return [
            self::text('ibratro_provision_url', '', 'Base provisioning URL. Leave blank to use https://{domain}/prov'),
            self::text('ibratro_enable_stun', '1', 'Enable STUN. Set to 0 to leave STUN server blank'),
            self::text('ibratro_stun_server', 'stun.l.google.com', 'STUN server hostname'),
            self::text('ibratro_stun_port', '19302', 'STUN server port'),
            self::text('ibratro_greeting', 'InTrade', 'LCD title shown on the phone'),
            self::text('ibratro_country_toneset', '13', 'Regional tone set index'),
            self::text('ibratro_video_codec', 'H264', 'Preferred video codec'),
            self::text('ibratro_menu_password', '123', 'Phone menu password'),
            self::text('ibratro_wifi_enable', '0', 'Enable Wi-Fi. 0=disabled, 1=enabled', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function networkSettings(): array
    {
        return [
            self::text('ibratro_enable_dhcp', '1', 'Use DHCP for network configuration'),
            self::text('ibratro_enable_bridge_mode', '1', 'Enable bridge mode between LAN and PC ports'),
            self::text('ibratro_use_vendor_class_id', '0', 'Send vendor class ID in DHCP requests'),
            self::text('ibratro_mtu', '1500', 'Network MTU size', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function mediaSettings(): array
    {
        return [
            self::text('ibratro_rtp_initial_port', '10000', 'RTP initial port'),
            self::text('ibratro_rtp_port_quantity', '1000', 'RTP port range size'),
            self::text('ibratro_rtp_keep_alive', '1', 'Send RTP keep-alive packets'),
            self::text('ibratro_audio_codec_sets', 'G722,opus,PCMU,PCMA,G729,iLBC', 'Preferred audio codec list'),
            self::text('ibratro_line_audio_codec_map', '', 'Per-line audio codec map. Leave blank to match audio codec sets', false),
            self::text('ibratro_dtmf_payload_type', '101', 'DTMF RTP payload type'),
            self::text('ibratro_opus_payload_type', '107', 'Opus RTP payload type'),
            self::text('ibratro_video_frame_rate', '25', 'Video frame rate (fps)'),
            self::text('ibratro_video_bit_rate', '2000000', 'Video bit rate in bps'),
            self::text('ibratro_video_resolution', '6', 'Video resolution index'),
            self::text('ibratro_video_negotiate_dir', '2', 'Video negotiate direction'),
            self::text('ibratro_h264_packet_mode', '', 'H264 packet mode. Leave blank to omit from config', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function sipGlobalSettings(): array
    {
        return [
            self::text('ibratro_stun_refresh_time', '50', 'STUN refresh interval in seconds'),
            self::text('ibratro_sip_wait_stun_time', '800', 'Milliseconds to wait for STUN before SIP'),
            self::text('ibratro_reg_fail_interval', '32', 'Registration retry interval after failure in seconds'),
            self::text('ibratro_enable_rfc4475', '1', 'Enable RFC4475 support'),
            self::text('ibratro_strict_ua_match', '1', 'Require strict User-Agent matching'),
            self::text('ibratro_notify_reboot', '1', 'Allow NOTIFY to trigger reboot'),
            self::text('ibratro_failback_interval', '1800', 'Proxy failback interval in seconds', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function sipLineSettings(): array
    {
        return [
            self::text('ibratro_nat_udp_update', '2', 'NAT UDP keepalive mode'),
            self::text('ibratro_udp_update_ttl', '15', 'NAT UDP keepalive TTL in seconds'),
            self::text('ibratro_dtmf_mode', '3', 'DTMF transmission mode'),
            self::text('ibratro_enable_rport', '1', 'Enable SIP rport'),
            self::text('ibratro_tls_version', '2', 'TLS version for SIP transport'),
            self::text('ibratro_unregister_on_boot', '0', 'Unregister SIP lines on boot'),
            self::text('ibratro_enable_mac_header', '0', 'Send MAC address in SIP headers'),
            self::text('ibratro_enable_register_mac', '0', 'Include MAC address in REGISTER'),
            self::text('ibratro_default_line_ringtone', 'Default', 'Fallback per-line ringtone when no line override is set'),
            self::text('ibratro_media_crypto', '0', 'Media encryption mode (SRTP) for UDP/TCP lines'),
            self::text('ibratro_media_crypto_tls', '1', 'Media encryption mode (SRTP) for TLS lines'),
            self::text('ibratro_caller_id_type', '4', 'Per-line caller ID display type'),
            self::text('ibratro_populate_mwi', '1', 'Populate MWI number on SIP lines. 0 leaves MWI blank'),
            self::text('ibratro_enable_failback', '1', 'Enable SIP proxy failback'),
            self::text('ibratro_auto_tcp', '0', 'Enable SIP Auto TCP on lines', false),
            self::text('ibratro_enable_xfer_back', '1', 'Enable transfer recall (XferBack) on SIP lines', false),
            self::text('ibratro_missed_call_log', '1', 'Log missed calls'),
            self::text('ibratro_blf_dialog_match', '1', 'BLF dialog matching mode'),
            self::text('ibratro_enable_deal_180', '1', 'Handle 180 Ringing responses'),
            self::text('ibratro_strict_proxy', '1', 'Strict proxy handling'),
            self::text('ibratro_rfc_ver', '1', 'SIP RFC version'),
            self::text('ibratro_user_is_phone_line1_only', '1', 'Set UserIsPhone on line 1 only'),
            self::text('ibratro_subscribe_register', '1', 'Subscribe to registration events'),
            self::text('ibratro_req_with_port', '1', 'Include port in SIP requests'),
            self::text('ibratro_update_reg_expire', '1', 'Update registration expiry from server'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function callFeatureSettings(): array
    {
        return [
            self::text('ibratro_enable_multiline', '1', 'Enable multiline operation'),
            self::text('ibratro_call_waiting', '1', 'Enable call waiting'),
            self::text('ibratro_call_transfer', '1', 'Enable call transfer'),
            self::text('ibratro_call_conference', '1', 'Enable conference calls'),
            self::text('ibratro_enable_intercom', '1', 'Enable intercom'),
            self::text('ibratro_default_ext_line', '1', 'Default external line index'),
            self::text('ibratro_enable_sel_line', '1', 'Enable line selection'),
            self::text('ibratro_enable_def_line', '0', 'Enable default line selection', false),
            self::text('ibratro_default_answer_mode', '2', 'Default answer mode for calls'),
            self::text('ibratro_default_dial_mode', '2', 'Default dial mode for calls'),
            self::text('ibratro_auto_onhook', '1', 'Auto on-hook after remote hangup', false),
            self::text('ibratro_auto_onhook_time', '3', 'Auto on-hook delay in seconds', false),
            self::text('ibratro_ring_timeout', '120', 'Ring timeout in seconds', false),
            self::text('ibratro_call_timeout', '120', 'Call timeout in seconds', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function phoneSettings(): array
    {
        return [
            self::text('ibratro_line_display_format', '$name@$protocol$instance', 'Line label format on LCD'),
            self::text('ibratro_enable_call_history', '1', 'Enable call history'),
            self::text('ibratro_sip_notify_xml', '1', 'Apply XML config from SIP NOTIFY'),
            self::text('ibratro_xml_update_interval', '30', 'XML phonebook refresh interval in minutes'),
            self::text('ibratro_default_language', 'en', 'Default phone UI language'),
            self::text('ibratro_lcd_contrast', '5', 'LCD contrast level', false),
            self::text('ibratro_enable_energy_saving', '1', 'Enable energy saving mode', false),
            self::text('ibratro_default_ringtone', 'Type 2', 'Default ringtone'),
            self::text('ibratro_default_ringtone_ext', 'Type 1', 'Expansion module ringtone 1', false),
            self::text('ibratro_default_ringtone_ext2', 'Type 4', 'Expansion module ringtone 2', false),
            self::text('ibratro_display_brightness_active', '12', 'LCD brightness while active'),
            self::text('ibratro_display_brightness_inactive', '4', 'LCD brightness while idle'),
            self::text('ibratro_display_inactivity_time', '45', 'Backlight off time in seconds'),
            self::text('ibratro_caller_display_type', '5', 'Caller ID display style', false),
            self::text('ibratro_enable_mwi_tone', '0', 'Play tone for message waiting indicator', false),
            self::text('ibratro_display_call_duration', '1', 'Show call duration on display', false),
            self::text('ibratro_keylock_enable', '0', 'Enable keypad lock', false),
            self::text('ibratro_emergency_call', '110', 'Emergency dial number', false),
            self::text('ibratro_enable_sntp', '1', 'Enable SNTP time sync'),
            self::text('ibratro_time_zone', '32', 'Time zone index'),
            self::text('ibratro_time_zone_name', 'UTC+8', 'Time zone label'),
            self::text('ibratro_enable_dst', '0', 'Enable daylight saving time'),
            self::text('ibratro_dst_fixed_type', '0', 'DST fixed type'),
            self::text('ibratro_location', '4', 'DST location index'),
            self::text('ibratro_dst_minute_offset', '60', 'DST offset in minutes'),
            self::text('ibratro_time_display', '0', 'Time display style'),
            self::text('ibratro_date_display', '6', 'Date display style'),
            self::text('ibratro_date_separator', '0', 'Date separator style'),
            self::text('ibratro_softkey_exit', '2', 'Softkey exit style'),
            self::text('ibratro_softkey_desktopsoftkey', 'history;contact;dnd;menu;', 'Desktop softkeys'),
            self::text('ibratro_softkey_talkingsoftkey', 'hold;xfer;conf;end;', 'In-call softkeys'),
            self::text('ibratro_softkey_ringingsoftkey', 'accept;none;forward;reject;', 'Ringing softkeys'),
            self::text('ibratro_softkey_desktopclick', 'history;status;none;none;none;', 'Desktop click actions'),
            self::text('ibratro_softkey_mode', '0', 'Softkey mode', false),
            self::text('ibratro_softkey_talkingvideosoftkey', 'hold;xfer;switch;end;', 'In-call video softkeys', false),
            self::text('ibratro_softkey_dialerpresoftkey', 'audio;video;save;exit;', 'Dialer pre-call softkeys', false),
            self::text('ibratro_softkey_dialercallsoftkey', 'send;2aB;delete;exit;', 'Dialer call softkeys', false),
            self::text('ibratro_softkey_alertingsoftkey', 'dialpad;xfer;cancel;', 'Alerting softkeys', false),
            self::text('ibratro_softkey_conferencesoftkey', 'conf;dialpad;end;split;hold;mute;exit;', 'Conference softkeys', false),
            self::text('ibratro_softkey_dialerxfersoftkey', 'audio;video;xfer;contact;history;cancel;', 'Dialer transfer softkeys', false),
            self::text('ibratro_softkey_dialercfwdsoftkey', 'contact;history;forward;cancel;', 'Dialer call forward softkeys', false),
            self::text('ibratro_video_display_mode', '3', 'Video display mode on calls', false),
            self::text('ibratro_auto_handle_video', '1', 'Auto-handle incoming video calls', false),
            self::text('ibratro_firmware_config', '', 'Firmware config file name', false),
            self::text('ibratro_enable_auto_upgrade', '0', 'Enable automatic firmware upgrade', false),
            self::text('ibratro_firmware_upgrade_server_1', '', 'Firmware upgrade server 1', false),
            self::text('ibratro_firmware_upgrade_server_2', '', 'Firmware upgrade server 2', false),
            self::text('ibratro_firmware_upgrade_interval', '24', 'Firmware upgrade check interval in hours', false),
            self::text('ibratro_syslog_enable', '0', 'Send phone logs to syslog', false),
            self::text('ibratro_syslog_server', '0.0.0.0', 'Syslog server address', false),
            self::text('ibratro_syslog_server_port', '514', 'Syslog server port', false),
            self::text('ibratro_app_icon_display', '1,1,1,1', 'App icon display flags', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function directorySettings(): array
    {
        $settings = [
            self::text('ibratro_directory_contacts', 'users', 'Contact filter for XML phonebook slot 1'),
            self::text('ibratro_directory_name_1', '', 'Phonebook slot 1 label. Leave blank to use domain name'),
            self::text('ibratro_directory_sipline_1', '1', 'SIP line for phonebook slot 1'),
            self::text('ibratro_directory_bind_line_1', '1', 'Bind line for phonebook slot 1'),
        ];

        for ($slot = 2; $slot <= 5; $slot++) {
            $defaults = match ($slot) {
                2 => ['extensions', 'Extensions'],
                3 => ['groups', 'Groups'],
                4 => ['all', 'All'],
                default => ['', ''],
            };

            $settings[] = self::text(
                'ibratro_directory_contacts_' . $slot,
                $defaults[0],
                'Contact filter for XML phonebook slot ' . $slot,
                $slot <= 4,
            );
            $settings[] = self::text(
                'ibratro_directory_name_' . $slot,
                $defaults[1],
                'Phonebook slot ' . $slot . ' label',
                $slot <= 4,
            );
            $settings[] = self::text(
                'ibratro_directory_url_' . $slot,
                '',
                'Custom URL for phonebook slot ' . $slot . '. Leave blank to auto-build from contacts filter',
                false,
            );
            $settings[] = self::text(
                'ibratro_directory_sipline_' . $slot,
                '1',
                'SIP line for phonebook slot ' . $slot,
                false,
            );
            $settings[] = self::text(
                'ibratro_directory_bind_line_' . $slot,
                '1',
                'Bind line for phonebook slot ' . $slot,
                false,
            );
        }

        $settings[] = self::text(
            'ibratro_directory_url',
            '',
            'Custom URL for phonebook slot 1. Leave blank to auto-build from contacts filter',
            false,
        );

        return $settings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function dssKeySettings(): array
    {
        return [
            self::text('ibratro_select_dsskey_action', '0', 'Default DSS key action'),
            self::text('ibratro_memory_key_to_bxfer', '3', 'Memory key blind transfer mode'),
            self::text('ibratro_dss_home_page', '0', 'DSS home page index'),
            self::text('ibratro_dss_long_press_action', '1', 'DSS long-press action'),
            self::text('ibratro_auto_blf_list', '0', 'Auto BLF list on DSS keys', false),
            self::text('ibratro_dss_timeout_to_home', '90', 'Seconds before DSS returns to home page', false),
            self::text('ibratro_side_key_pages', '1', 'Number of programmable side key pages', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function autoprovisionSettings(): array
    {
        return [
            self::text('ibratro_download_common_conf', '1', 'Download common config on provision'),
            self::text('ibratro_flash_protocol', '2', 'Autoprovision protocol'),
            self::text('ibratro_flash_mode', '0', 'Autoprovision flash mode'),
            self::text('ibratro_flash_interval', '1', 'Autoprovision check interval in hours'),
            self::text('ibratro_pnp_enable', '1', 'Enable Plug and Play discovery'),
            self::text('ibratro_pnp_ip', '224.0.1.75', 'PNP multicast address'),
            self::text('ibratro_pnp_port', '5060', 'PNP port'),
            self::text('ibratro_dhcp_option', '66', 'DHCP option used for provisioning URL', false),
            self::text('ibratro_dhcp_renew_upgrade', '1', 'Check for upgrades on DHCP renew', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function qosSettings(): array
    {
        return [
            self::text('ibratro_enable_vlan', '0', 'Enable VLAN tagging', false),
            self::text('ibratro_lan_port_vlan', '256', 'LAN port VLAN ID', false),
            self::text('ibratro_pc_port_vlan', '254', 'PC port VLAN ID', false),
            self::text('ibratro_qos_sip', '0', 'SIP QoS priority', false),
            self::text('ibratro_qos_rtp_voice', '0', 'Voice RTP QoS priority', false),
            self::text('ibratro_qos_rtp_video', '0', 'Video RTP QoS priority', false),
            self::text('ibratro_enable_diffserv', '0', 'Enable DiffServ QoS', false),
            self::text('ibratro_dscp_sip', '46', 'SIP DSCP value', false),
            self::text('ibratro_dscp_rtp_voice', '46', 'Voice RTP DSCP value', false),
            self::text('ibratro_dscp_rtp_video', '34', 'Video RTP DSCP value', false),
            self::text('ibratro_lldp_tx_enable', '0', 'Transmit LLDP packets', false),
            self::text('ibratro_lldp_refresh', '60', 'LLDP refresh interval in seconds', false),
            self::text('ibratro_lldp_learn', '0', 'LLDP learn policy', false),
            self::text('ibratro_enable_pvid', '1', 'Enable PVID on VLAN ports', false),
            self::text('ibratro_cdp_enable', '1', 'Enable CDP', false),
            self::text('ibratro_cdp_refresh', '60', 'CDP refresh interval in seconds', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function uiSettings(): array
    {
        return [
            self::text('ibratro_bluetooth_enabled', '1', 'Enable Bluetooth'),
            self::text('ibratro_bluetooth_ring_mode', '1', 'Bluetooth ring mode', false),
            self::text('ibratro_power_saving', '1', 'Enable power saving', false),
            self::text('ibratro_screensaver_type', '1', 'Screensaver type', false),
            self::text('ibratro_timeout_to_screensaver', '7200', 'Seconds before screensaver', false),
            self::text('ibratro_timeout_to_power_saving', '7200', 'Seconds before power saving', false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function perLineRingtoneSettings(): array
    {
        $settings = [];

        for ($line = 1; $line <= 20; $line++) {
            $settings[] = self::text(
                'ibratro_ringtone_line' . $line,
                '',
                'Per-line ringtone for SIP line ' . $line,
                false,
            );
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(
        string $subcategory,
        string $value,
        string $description,
        bool $enabled = true,
    ): array {
        return [
            'default_setting_category' => 'provision',
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => 'text',
            'default_setting_value' => $value,
            'default_setting_enabled' => $enabled,
            'default_setting_description' => $description,
        ];
    }
}
