@php
    use App\Services\Provisioning\IbratroModelProfiles;

    $settings = is_array($settings ?? null) ? $settings : [];
    $profile = (string) ($modelProfile ?? '');
    $resolve = static fn (string $key, string $default = '') => IbratroModelProfiles::resolve($profile, $settings, $key, $default);

    $useMacHeaders = array_key_exists('macHeaders', get_defined_vars())
        ? (bool) $macHeaders
        : ($resolve('enable_mac_header', '0') === '1');
    $useUnregisterOnBoot = array_key_exists('unregisterOnBoot', get_defined_vars())
        ? (bool) $unregisterOnBoot
        : ($resolve('unregister_on_boot', '0') === '1');
    $resolvedAudioCodecMap = $audioCodecMap ?? $resolve('audio_codec_sets', 'G722,opus,PCMU,PCMA,G729,iLBC');
    $resolvedRtpInitialPort = $rtpInitialPort ?? $resolve('rtp_initial_port', '10000');
    $resolvedRtpPortQuantity = $rtpPortQuantity ?? $resolve('rtp_port_quantity', '1000');
    $resolvedVideoResolution = $videoResolution ?? $resolve('video_resolution', '6');
    $resolvedDssLongPress = $resolve('dss_long_press_action', '3');
    $talkingSoftkey = $resolve('softkey_talkingsoftkey', 'hold;xfer;conf;end;');
    $ringingSoftkey = $resolve('softkey_ringingsoftkey', 'accept;none;forward;reject;');
@endphp
<?xml version="1.0" encoding="UTF-8"?>
<sysConf>
    <Version>2.0000000000</Version>
    <net>
        <EnableDHCP>{{ $resolve('enable_dhcp', '1') }}</EnableDHCP>
        <DHCPAutoDNS>1</DHCPAutoDNS>
        <DHCPOption100-101>1</DHCPOption100-101>
        <UseVendorClassID>{{ $resolve('use_vendor_class_id', '0') }}</UseVendorClassID>
        <VendorClassID>{{ $modelLabel }}</VendorClassID>
        <UseVendor6ClassID>0</UseVendor6ClassID>
        <Vendor6ClassID>{{ $modelLabel }}</Vendor6ClassID>
        <PrimaryDNS>{{ $dns_server_primary ?? '8.8.8.8' }}</PrimaryDNS>
        <SecondaryDNS>{{ $dns_server_secondary ?? '4.4.4.4' }}</SecondaryDNS>
        <EnableBridgeMode>{{ $resolve('enable_bridge_mode', '1') }}</EnableBridgeMode>
@if ($resolve('mtu', '') !== '')
        <MTU>{{ $resolve('mtu') }}</MTU>
@endif
@if ($resolve('wifi_enable', '0') === '1')
        <WiFiEnable>1</WiFiEnable>
@endif
    </net>
    <mm>
        <DtmfPayloadType>{{ $resolve('dtmf_payload_type', '101') }}</DtmfPayloadType>
        <OpusPayloadType>{{ $resolve('opus_payload_type', '107') }}</OpusPayloadType>
        <RTPInitialPort>{{ $resolvedRtpInitialPort }}</RTPInitialPort>
        <RTPPortQuantity>{{ $resolvedRtpPortQuantity }}</RTPPortQuantity>
        <RTPKeepAlive>{{ $resolve('rtp_keep_alive', '1') }}</RTPKeepAlive>
        <SelectYourTone>{{ $resolve('country_toneset', '13') }}</SelectYourTone>
@if (!empty($videoEnabled) && $resolve('h264_packet_mode', '') !== '')
        <H264PacketMode>{{ $resolve('h264_packet_mode') }}</H264PacketMode>
@endif
        <capability>
            <AudioCodecSets>{{ $resolvedAudioCodecMap }}</AudioCodecSets>
@if (!empty($videoEnabled))
            <VideoCodecSets>{{ $resolve('video_codec', 'H264') }}</VideoCodecSets>
            <VideoFrameRate>{{ $resolve('video_frame_rate', '25') }}</VideoFrameRate>
            <VideoBitRate>{{ $resolve('video_bit_rate', '2000000') }}</VideoBitRate>
            <VideoResolution>{{ $resolvedVideoResolution }}</VideoResolution>
            <VideoNegotiateDir>{{ $resolve('video_negotiate_dir', '2') }}</VideoNegotiateDir>
@endif
        </capability>
    </mm>
    <sip>
        <SIPPort>{{ $sip_port ?? '5060' }}</SIPPort>
@if ($resolve('enable_stun', '1') === '0')
        <STUNServer></STUNServer>
        <STUNPort>{{ $resolve('stun_port', '3478') }}</STUNPort>
@else
        <STUNServer>{{ $resolve('stun_server', 'stun.l.google.com') }}</STUNServer>
        <STUNPort>{{ $resolve('stun_port', '19302') }}</STUNPort>
@endif
        <STUNRefreshTime>{{ $resolve('stun_refresh_time', '50') }}</STUNRefreshTime>
        <SIPWaitStunTime>{{ $resolve('sip_wait_stun_time', '800') }}</SIPWaitStunTime>
        <EnableRFC4475>{{ $resolve('enable_rfc4475', '1') }}</EnableRFC4475>
        <StrictUAMatch>{{ $resolve('strict_ua_match', '1') }}</StrictUAMatch>
        <NotifyReboot>{{ $resolve('notify_reboot', '1') }}</NotifyReboot>
@for ($n = 1; $n <= (int) ($maxLines ?? 12); $n++)
@include('provisioning.ibratro.partials.sip-line', [
    'index' => $n,
    'line' => $account[$n] ?? [],
    'settings' => $settings,
    'modelProfile' => $profile,
    'videoEnabled' => ! empty($videoEnabled),
    'includeLineVideoCodec' => $includeLineVideoCodec ?? true,
    'audioCodecMap' => $resolvedAudioCodecMap,
    'useMacHeaders' => $useMacHeaders,
    'useUnregisterOnBoot' => $useUnregisterOnBoot,
    'voicemail_number' => $voicemail_number ?? '*97',
])
@endfor
    </sip>
    <call>
        <port index="1">
            <EnableMultiLine>{{ $resolve('enable_multiline', '1') }}</EnableMultiLine>
            <CallWaiting>{{ $resolve('call_waiting', '1') }}</CallWaiting>
            <CallTransfer>{{ $resolve('call_transfer', '1') }}</CallTransfer>
            <CallConference>{{ $resolve('call_conference', '1') }}</CallConference>
            <EnableIntercom>{{ $resolve('enable_intercom', '1') }}</EnableIntercom>
            <DefaultExtLine>{{ $resolve('default_ext_line', '1') }}</DefaultExtLine>
            <EnableSelLine>{{ $resolve('enable_sel_line', '1') }}</EnableSelLine>
            <DefaultAnsMode>{{ $resolve('default_answer_mode', '2') }}</DefaultAnsMode>
            <DefaultDialMode>{{ $resolve('default_dial_mode', '2') }}</DefaultDialMode>
@if ($profile === 'entry' || $profile === 'video')
            <EnableDefLine>{{ $resolve('enable_def_line', '1') }}</EnableDefLine>
@endif
@if (!empty($videoEnabled))
@if ($profile === 'video')
            <AutoHandleVideo>{{ $resolve('auto_handle_video', '0') }}</AutoHandleVideo>
            <EnableDefLine>{{ $resolve('enable_def_line', '1') }}</EnableDefLine>
@else
            <VideoDisplayMode>{{ $resolve('video_display_mode', '3') }}</VideoDisplayMode>
            <AutoHandleVideo>{{ $resolve('auto_handle_video', '1') }}</AutoHandleVideo>
@endif
@endif
@if ($resolve('auto_onhook', '') !== '')
            <AutoOnhook>{{ $resolve('auto_onhook') }}</AutoOnhook>
            <AutoOnhookTime>{{ $resolve('auto_onhook_time', '3') }}</AutoOnhookTime>
@endif
@if ($resolve('ring_timeout', '') !== '')
            <RingTimeout>{{ $resolve('ring_timeout') }}</RingTimeout>
@endif
@if ($resolve('call_timeout', '') !== '')
            <CallTimeout>{{ $resolve('call_timeout') }}</CallTimeout>
@endif
        </port>
    </call>
    <phone>
        <MenuPassword>{{ $resolve('menu_password', '') }}</MenuPassword>
        <LineDisplayFormat>{{ $resolve('line_display_format', '$name@$protocol$instance') }}</LineDisplayFormat>
        <EnableCallHistory>{{ $resolve('enable_call_history', '1') }}</EnableCallHistory>
        <SIPNotifyXML>{{ $resolve('sip_notify_xml', '1') }}</SIPNotifyXML>
        <XMLUpdateInterval>{{ $resolve('xml_update_interval', '30') }}</XMLUpdateInterval>
@if ($profile === 'video')
        <CallerDisplayT>{{ $resolve('caller_display_type', '6') }}</CallerDisplayT>
        <EnableMWITone>{{ $resolve('enable_mwi_tone', '0') }}</EnableMWITone>
@else
        <CallerDisplayType>{{ $resolve('caller_display_type', '5') }}</CallerDisplayType>
@if ($profile === 'entry')
        <EnableMWITone>{{ $resolve('enable_mwi_tone', '0') }}</EnableMWITone>
@endif
@endif
        <display>
            <LCDTitle>{{ $resolve('greeting', 'InTrade') }}</LCDTitle>
@if ($profile !== 'video' && $profile !== 'entry')
            <PhoneModel>{{ $modelLabel }}</PhoneModel>
@endif
            <LCDConstrast>{{ $resolve('lcd_contrast', '5') }}</LCDConstrast>
            <LCDLuminanceLevel>{{ $resolve('display_brightness_active', '12') }}</LCDLuminanceLevel>
            <EnableEnergysaving>{{ $resolve('enable_energy_saving', '1') }}</EnableEnergysaving>
            <BacklightOffTime>{{ $resolve('display_inactivity_time', '45') }}</BacklightOffTime>
            <DefaultLanguage>{{ $resolve('default_language', 'en') }}</DefaultLanguage>
@if ($resolve('display_call_duration', '') !== '')
            <DisplayCallDuration>{{ $resolve('display_call_duration') }}</DisplayCallDuration>
@endif
        </display>
        <volume>
            <RingType>{{ $resolve('default_ringtone', 'Type 2') }}</RingType>
@if ($profile !== 'video' && $profile !== 'entry' && $resolve('enable_mwi_tone', '') !== '')
            <EnableMWITone>{{ $resolve('enable_mwi_tone') }}</EnableMWITone>
@endif
        </volume>
        <date>
            <EnableSNTP>{{ $resolve('enable_sntp', '1') }}</EnableSNTP>
            <SNTPServer>{{ $settings['ntp_server_primary'] ?? '0.pool.ntp.org' }}</SNTPServer>
            <SecondSNTPServer>{{ $settings['ntp_server_secondary'] ?? 'time.nist.gov' }}</SecondSNTPServer>
            <TimeZone>{{ $resolve('time_zone', '32') }}</TimeZone>
            <TimeZoneName>{{ $resolve('time_zone_name', 'UTC+8') }}</TimeZoneName>
            <DSTType>{{ $resolve('enable_dst', '0') }}</DSTType>
            <DSTLocation>{{ $resolve('location', '0') }}</DSTLocation>
            <DSTRuleMode>{{ $resolve('dst_fixed_type', '0') }}</DSTRuleMode>
            <DSTMinOffset>{{ $resolve('dst_minute_offset', '60') }}</DSTMinOffset>
        </date>
        <timeDisplay>
            <EnableTimeDisplay>0</EnableTimeDisplay>
            <TimeDisplayStyle>{{ $resolve('time_display', '0') }}</TimeDisplayStyle>
            <DateDisplayStyle>{{ $resolve('date_display', '6') }}</DateDisplayStyle>
            <DateSeparator>{{ $resolve('date_separator', '0') }}</DateSeparator>
        </timeDisplay>
@if ($profile === 'video')
        <softKeyConfig>
            <SoftkeyMode>{{ $resolve('softkey_mode', '0') }}</SoftkeyMode>
            <SoftKeyExitStyle>{{ $resolve('softkey_exit', '2') }}</SoftKeyExitStyle>
            <DesktopSoftkey>{{ $resolve('softkey_desktopsoftkey', 'history;contact;dnd;menu;') }}</DesktopSoftkey>
            <TalkingSoftkey>{{ $talkingSoftkey }}</TalkingSoftkey>
            <RingingSoftkey>{{ $ringingSoftkey }}</RingingSoftkey>
            <AlertingSoftkey>{{ $resolve('softkey_alertingsoftkey', 'dialpad;xfer;cancel;') }}</AlertingSoftkey>
            <XAlertingSoftkey>{{ $resolve('softkey_xalertingsoftkey', 'dialpad;xfer;cancel;') }}</XAlertingSoftkey>
            <ConferenceSoftkey>{{ $resolve('softkey_conferencesoftkey', 'conf;dialpad;end;split;hold;mute;exit;') }}</ConferenceSoftkey>
            <WaitingSoftkey>{{ $resolve('softkey_waitingsoftkey', 'hold;xfer;conf;end;') }}</WaitingSoftkey>
            <EndingSoftkey>{{ $resolve('softkey_endingsoftkey', 'complete;autoRedial;end;redial;') }}</EndingSoftkey>
            <DialerPreSoftkey>{{ $resolve('softkey_dialerpresoftkey', 'audio;video;redial;') }}</DialerPreSoftkey>
            <DialerCallSoftkey>{{ $resolve('softkey_dialercallsoftkey', 'audio;video;redial;') }}</DialerCallSoftkey>
            <DialerXferSoftkey>{{ $resolve('softkey_dialerxfersoftkey', 'audio;video;xfer;contact;history;cancel;') }}</DialerXferSoftkey>
            <DialerCfwdSoftkey>{{ $resolve('softkey_dialercfwdsoftkey', 'contact;history;forward;cancel;') }}</DialerCfwdSoftkey>
            <DesktopClick>{{ $resolve('softkey_desktopclick', 'none;none;none;none;none;') }}</DesktopClick>
            <DailerClick>{{ $resolve('softkey_dailerclick', 'pline;nline;none;none;none;') }}</DailerClick>
            <RingingClick>{{ $resolve('softkey_ringingclick', 'none;none;none;none;none;') }}</RingingClick>
            <CallClick>{{ $resolve('softkey_callclick', 'pcall;ncall;voldown;volup;none;') }}</CallClick>
            <DesktopLongPress>{{ $resolve('softkey_desktoplongpress', 'status;none;none;mwi;none;') }}</DesktopLongPress>
            <DialerConfSoftkey>{{ $resolve('softkey_dialerconfsoftkey', 'audio;video;cancel;contact;history;redial;') }}</DialerConfSoftkey>
        </softKeyConfig>
@else
        <softkey>
            <SoftkeyMode>{{ $resolve('softkey_mode', '0') }}</SoftkeyMode>
            <SoftKeyExitStyle>{{ $resolve('softkey_exit', $profile === 'standard' ? '1' : '2') }}</SoftKeyExitStyle>
            <DesktopSoftkey>{{ $resolve('softkey_desktopsoftkey', 'history;contact;dnd;menu;') }}</DesktopSoftkey>
@if ($profile === 'entry')
            <TalkingSoftkey>{{ $talkingSoftkey }}</TalkingSoftkey>
@elseif ($profile === 'advanced')
            <TalkingAudioSoftkey>{{ $talkingSoftkey }}</TalkingAudioSoftkey>
@endif
@if ($profile === 'entry')
            <RingingSoftkey>{{ $ringingSoftkey }}</RingingSoftkey>
            <AlertingSoftkey>{{ $resolve('softkey_alertingsoftkey', 'end;none;none;none;') }}</AlertingSoftkey>
            <XAlertingSoftkey>{{ $resolve('softkey_xalertingsoftkey', 'end;none;none;xfer;') }}</XAlertingSoftkey>
            <ConferenceSoftkey>{{ $resolve('softkey_conferencesoftkey', 'hold;none;split;end;') }}</ConferenceSoftkey>
            <WaitingSoftkey>{{ $resolve('softkey_waitingsoftkey', 'xfer;accept;reject;end;') }}</WaitingSoftkey>
            <EndingSoftkey>{{ $resolve('softkey_endingsoftkey', 'repeat;none;none;end;') }}</EndingSoftkey>
            <DialerPreSoftkey>{{ $resolve('softkey_dialerpresoftkey', 'send;2aB;delete;exit;') }}</DialerPreSoftkey>
            <DialerCallSoftkey>{{ $resolve('softkey_dialercallsoftkey', 'send;2aB;delete;exit;') }}</DialerCallSoftkey>
            <DialerXferSoftkey>{{ $resolve('softkey_dialerxfersoftkey', 'delete;xfer;send;exit;') }}</DialerXferSoftkey>
            <DialerCfwdSoftkey>{{ $resolve('softkey_dialercfwdsoftkey', 'send;2aB;delete;exit;') }}</DialerCfwdSoftkey>
            <DesktopClick>{{ $resolve('softkey_desktopclick', 'history;status;none;none;none;') }}</DesktopClick>
            <DailerClick>{{ $resolve('softkey_dailerclick', 'pline;nline;none;none;none;') }}</DailerClick>
            <RingingClick>{{ $resolve('softkey_ringingclick', 'none;none;none;none;none;') }}</RingingClick>
            <CallClick>{{ $resolve('softkey_callclick', 'none;none;none;none;none;') }}</CallClick>
            <DesktopLongPress>{{ $resolve('softkey_desktoplongpress', 'status;none;none;none;reset;') }}</DesktopLongPress>
            <DialerConfSoftkey>{{ $resolve('softkey_dialerconfsoftkey', 'contact;clogs;redial;video;cancel;') }}</DialerConfSoftkey>
@elseif ($profile === 'standard')
            <TalkingSoftkey>{{ $talkingSoftkey }}</TalkingSoftkey>
            <RingingSoftkey>{{ $ringingSoftkey }}</RingingSoftkey>
            <AlertingSoftkey>{{ $resolve('softkey_alertingsoftkey', 'end;none;none;none;') }}</AlertingSoftkey>
            <XAlertingSoftkey>{{ $resolve('softkey_xalertingsoftkey', 'end;none;none;none;') }}</XAlertingSoftkey>
            <ConferenceSoftkey>{{ $resolve('softkey_conferencesoftkey', 'hold;none;split;end;') }}</ConferenceSoftkey>
            <WaitingSoftkey>{{ $resolve('softkey_waitingsoftkey', 'hold;xfer;conf;end;') }}</WaitingSoftkey>
            <EndingSoftkey>{{ $resolve('softkey_endingsoftkey', 'repeat;none;none;end;') }}</EndingSoftkey>
            <DialerPreSoftkey>{{ $resolve('softkey_dialerpresoftkey', 'send;2aB;delete;exit;') }}</DialerPreSoftkey>
            <DialerCallSoftkey>{{ $resolve('softkey_dialercallsoftkey', 'repeat;2aB;delete;exit;') }}</DialerCallSoftkey>
            <DialerXferSoftkey>{{ $resolve('softkey_dialerxfersoftkey', 'repeat;2aB;delete;exit;') }}</DialerXferSoftkey>
            <DialerCfwdSoftkey>{{ $resolve('softkey_dialercfwdsoftkey', 'repeat;2aB;delete;exit;') }}</DialerCfwdSoftkey>
            <DesktopClick>{{ $resolve('softkey_desktopclick', 'history;status;none;none;none;') }}</DesktopClick>
            <DailerClick>{{ $resolve('softkey_dailerclick', 'none;none;none;none;none;') }}</DailerClick>
            <CallClick>{{ $resolve('softkey_callclick', 'none;none;voldown;volup;none;') }}</CallClick>
            <DesktopLongPress>{{ $resolve('softkey_desktoplongpress', 'status;none;none;none;reset;') }}</DesktopLongPress>
            <DialerConfSoftkey>{{ $resolve('softkey_dialerconfsoftkey', 'contact;clogs;redial;video;cancel;') }}</DialerConfSoftkey>
@elseif ($profile === 'advanced')
            <TalkingVideoSoftkey>{{ $resolve('softkey_talkingvideosoftkey', 'hold;xfer;switch;end;') }}</TalkingVideoSoftkey>
            <RingingSoftkey>{{ $ringingSoftkey }}</RingingSoftkey>
            <AlertingSoftkey>{{ $resolve('softkey_alertingsoftkey', 'end;none;none;none;') }}</AlertingSoftkey>
            <XAlertingSoftkey>{{ $resolve('softkey_xalertingsoftkey', 'end;none;none;xfer;') }}</XAlertingSoftkey>
            <ConferenceSoftkey>{{ $resolve('softkey_conferencesoftkey', 'hold;manage;conf;split;end;') }}</ConferenceSoftkey>
            <WaitingSoftkey>{{ $resolve('softkey_waitingsoftkey', 'xfer;accept;reject;end;') }}</WaitingSoftkey>
            <EndingSoftkey>{{ $resolve('softkey_endingsoftkey', 'redial;none;none;end;') }}</EndingSoftkey>
            <DialerPreSoftkey>{{ $resolve('softkey_dialerpresoftkey', 'audio;video;save;exit;') }}</DialerPreSoftkey>
            <DialerCallSoftkey>{{ $resolve('softkey_dialercallsoftkey', 'send;2aB;delete;exit;') }}</DialerCallSoftkey>
            <DialerXferSoftkey>{{ $resolve('softkey_dialerxfersoftkey', 'delete;xfer;send;exit;') }}</DialerXferSoftkey>
            <DialerCfwdSoftkey>{{ $resolve('softkey_dialercfwdsoftkey', 'send;2aB;delete;exit;') }}</DialerCfwdSoftkey>
            <CallLogSoftkey>{{ $resolve('softkey_calllogsoftkey', 'exit;option;delete;dial;') }}</CallLogSoftkey>
            <DesktopClick>{{ $resolve('softkey_desktopclick', 'history;status;none;none;none;') }}</DesktopClick>
            <DailerClick>{{ $resolve('softkey_dailerclick', 'pline;nline;none;none;none;') }}</DailerClick>
            <RingingClick>{{ $resolve('softkey_ringingclick', 'none;none;none;none;none;') }}</RingingClick>
            <CallClick>{{ $resolve('softkey_callclick', 'none;none;voldown;volup;none;') }}</CallClick>
            <DesktopLongPress>{{ $resolve('softkey_desktoplongpress', 'status;none;none;none;reset;') }}</DesktopLongPress>
            <DialerConfSoftkey>{{ $resolve('softkey_dialerconfsoftkey', 'contact;clogs;redial;video;cancel;') }}</DialerConfSoftkey>
@else
@if (!empty($videoEnabled))
            <TalkingVideoSoftkey>{{ $resolve('softkey_talkingvideosoftkey', 'hold;xfer;switch;end;') }}</TalkingVideoSoftkey>
            <DialerPreSoftkey>{{ $resolve('softkey_dialerpresoftkey', 'audio;video;save;exit;') }}</DialerPreSoftkey>
            <DialerCallSoftkey>{{ $resolve('softkey_dialercallsoftkey', 'send;2aB;delete;exit;') }}</DialerCallSoftkey>
@endif
            <RingingSoftkey>{{ $ringingSoftkey }}</RingingSoftkey>
            <DesktopClick>{{ $resolve('softkey_desktopclick', 'history;status;none;none;none;') }}</DesktopClick>
@endif
        </softkey>
@endif
@include('provisioning.ibratro.partials.xml-contacts', [
    'settings' => $settings,
    'domain_name' => $domain_name ?? '',
    'http_auth_username' => $http_auth_username ?? '',
    'http_auth_password' => $http_auth_password ?? '',
])
    </phone>
@include('provisioning.ibratro.partials.dss-keys', [
    'keys' => $keys ?? [],
    'keyLayout' => $keyLayout ?? 'advanced',
    'funcKeyPages' => $funcKeyPages ?? 4,
    'keysPerPage' => $keysPerPage ?? 29,
    'sideKeyPages' => $sideKeyPages ?? $resolve('side_key_pages', '1'),
    'dssLongPressAction' => $resolvedDssLongPress,
])
    <ap>
        <DefaultUsername>{{ $http_auth_username ?? '' }}</DefaultUsername>
        <DefaultPassword>{{ $http_auth_password ?? '' }}</DefaultPassword>
        <DownloadCommonConf>{{ $resolve('download_common_conf', '1') }}</DownloadCommonConf>
        <FlashServerIP>{{ $ibratro_provision_url ?: ('https://' . ($domain_name ?? '') . '/prov') }}</FlashServerIP>
        <FlashProtocol>{{ $resolve('flash_protocol', '2') }}</FlashProtocol>
        <FlashMode>{{ $resolve('flash_mode', '0') }}</FlashMode>
        <FlashInterval>{{ $resolve('flash_interval', '1') }}</FlashInterval>
@if ($resolve('firmware_config', '') !== '')
        <FirmwareConfig>{{ $resolve('firmware_config') }}</FirmwareConfig>
@endif
@if ($resolve('enable_auto_upgrade', '0') === '1')
        <EnableAutoUpgrade>1</EnableAutoUpgrade>
        <UpgradeServer1>{{ $resolve('firmware_upgrade_server_1', '') }}</UpgradeServer1>
        <UpgradeServer2>{{ $resolve('firmware_upgrade_server_2', '') }}</UpgradeServer2>
        <UpgradeInterval>{{ $resolve('firmware_upgrade_interval', '24') }}</UpgradeInterval>
@endif
        <pnp>
            <PNPEnable>{{ $resolve('pnp_enable', '1') }}</PNPEnable>
            <PNPIP>{{ $resolve('pnp_ip', '224.0.1.75') }}</PNPIP>
            <PNPPort>{{ $resolve('pnp_port', '5060') }}</PNPPort>
        </pnp>
@if ($resolve('dhcp_option', '') !== '')
        <DHCPOption>{{ $resolve('dhcp_option') }}</DHCPOption>
@endif
@if ($resolve('dhcp_renew_upgrade', '') !== '')
        <DHCPRenewUpgrade>{{ $resolve('dhcp_renew_upgrade') }}</DHCPRenewUpgrade>
@endif
    </ap>
    <qos>
        <EnableVLAN>{{ $resolve('enable_vlan', '0') }}</EnableVLAN>
@if ($resolve('enable_vlan', '0') === '1')
        <LANPortVLAN>{{ $resolve('lan_port_vlan', '256') }}</LANPortVLAN>
        <PCPortVLAN>{{ $resolve('pc_port_vlan', '254') }}</PCPortVLAN>
        <EnablePVID>{{ $resolve('enable_pvid', '1') }}</EnablePVID>
@endif
        <LLDPTransmit>{{ $resolve('lldp_tx_enable', '0') }}</LLDPTransmit>
        <LLDPRefreshTime>{{ $resolve('lldp_refresh', '60') }}</LLDPRefreshTime>
        <LLDPLearnPolicy>{{ $resolve('lldp_learn', '0') }}</LLDPLearnPolicy>
@if ($resolve('cdp_enable', '') !== '')
        <CDPEnable>{{ $resolve('cdp_enable') }}</CDPEnable>
        <CDPRefreshTime>{{ $resolve('cdp_refresh', '60') }}</CDPRefreshTime>
@endif
        <QOSSIP>{{ $resolve('qos_sip', '0') }}</QOSSIP>
        <QOSRTPVoice>{{ $resolve('qos_rtp_voice', '0') }}</QOSRTPVoice>
        <QOSRTPVideo>{{ $resolve('qos_rtp_video', '0') }}</QOSRTPVideo>
        <EnableDiffServ>{{ $resolve('enable_diffserv', '0') }}</EnableDiffServ>
        <DSCPSIP>{{ $resolve('dscp_sip', '46') }}</DSCPSIP>
        <DSCPRTPVoice>{{ $resolve('dscp_rtp_voice', '46') }}</DSCPRTPVoice>
        <DSCPRTPVideo>{{ $resolve('dscp_rtp_video', '34') }}</DSCPRTPVideo>
    </qos>
@if ($resolve('syslog_enable', '0') === '1')
    <log>
        <EnableSyslog>1</EnableSyslog>
        <SyslogServer>{{ $resolve('syslog_server', '0.0.0.0') }}</SyslogServer>
        <SyslogServerPort>{{ $resolve('syslog_server_port', '514') }}</SyslogServerPort>
    </log>
@endif
@if ($profile === 'video')
    <uiMainTainConfig>
        <TimeoutToScreensaver>{{ $resolve('timeout_to_screensaver', '7200') }}</TimeoutToScreensaver>
        <MissedCallPopup>0</MissedCallPopup>
        <MWIPopup>0</MWIPopup>
        <DeviceConnectPopup>0</DeviceConnectPopup>
        <SMSPopup>0</SMSPopup>
        <OtherPopup>1</OtherPopup>
        <DisplayProvisionprompt>0</DisplayProvisionprompt>
    </uiMainTainConfig>
@elseif ($profile === 'entry')
    <UIconfig>
        <DisplayLanguage>{{ $resolve('display_language', 'en;cn;tc;ru;it;fr;de;he;es;cat;eus;gal;tr;hr;slo;cz;nl;ko;ua;pt;pl;ar;jp;kr;') }}</DisplayLanguage>
        <Currentmaterialversion>0x01000000</Currentmaterialversion>
    </UIconfig>
    <background>
        <TimeoutToScreensaver>{{ $resolve('timeout_to_screensaver', '0') }}</TimeoutToScreensaver>
        <MissedCallPopup>1</MissedCallPopup>
        <MWIPopup>1</MWIPopup>
        <DeviceConnectPopup>1</DeviceConnectPopup>
        <SMSPopup>1</SMSPopup>
        <OtherPopup>1</OtherPopup>
        <DisplayProvisionprompt>0</DisplayProvisionprompt>
        <PowerSaving>1</PowerSaving>
        <TimeoutToPowerSaving>{{ $resolve('timeout_to_power_saving', '360') }}</TimeoutToPowerSaving>
    </background>
@else
    <UIconfig>
        <BluetoothEnabled>{{ $resolve('bluetooth_enabled', '1') }}</BluetoothEnabled>
        <BluetoothAdapterName>{{ $modelLabel }}</BluetoothAdapterName>
        <BluetoothRingMode>{{ $resolve('bluetooth_ring_mode', '1') }}</BluetoothRingMode>
    </UIconfig>
    <background>
        <TimeoutToScreensaver>{{ $resolve('timeout_to_screensaver', '7200') }}</TimeoutToScreensaver>
        <PowerSaving>{{ $resolve('power_saving', '1') }}</PowerSaving>
        <TimeoutToPowerSaving>{{ $resolve('timeout_to_power_saving', '7200') }}</TimeoutToPowerSaving>
        <ScreensaverType>{{ $resolve('screensaver_type', '1') }}</ScreensaverType>
@if ($resolve('app_icon_display', '') !== '')
        <AppIconDisplay>{{ $resolve('app_icon_display') }}</AppIconDisplay>
@endif
    </background>
@endif
</sysConf>
