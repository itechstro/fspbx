@php
    use App\Services\Provisioning\IbratroModelProfiles;

    $settings = is_array($settings ?? null) ? $settings : [];
    $line = is_array($line ?? null) ? $line : [];
    $profile = (string) ($modelProfile ?? '');
    $resolve = static fn (string $key, string $default = '') => IbratroModelProfiles::resolve($profile, $settings, $key, $default);

    $enabled = ! empty($line['password']);
    $transport = (string) ($line['transport_code'] ?? '0');
    $ringtoneKey = 'ibratro_ringtone_line' . $index;
    $ringtone = trim((string) ($settings[$ringtoneKey] ?? ''));
    $defaultRingtone = $resolve('default_line_ringtone', 'Default');
    $userIsPhone = $resolve('user_is_phone_line1_only', '1') === '1'
        ? ($index === 1 ? '1' : '0')
        : '0';
    $mediaCrypto = $transport === '3'
        ? $resolve('media_crypto_tls', '1')
        : $resolve('media_crypto', '0');
    $enableFailback = $resolve(
        'enable_failback',
        ! empty($line['outbound_proxy_secondary']) ? '1' : '0',
    );
    $populateMwi = $resolve('populate_mwi', '1') === '1';
    $lineAudioCodec = $resolve('line_audio_codec_map', '');
    if ($lineAudioCodec === '') {
        $lineAudioCodec = $audioCodecMap ?? 'G722,opus,PCMU,PCMA,G729,iLBC';
    }
@endphp
        <line index="{{ $index }}">
            <PhoneNumber>{{ $line['user_id'] ?? '' }}</PhoneNumber>
            <DisplayName>{{ $line['display_name'] ?? '' }}</DisplayName>
            <RegisterAddr>{{ $line['server_address'] ?? '' }}</RegisterAddr>
            <RegisterPort>{{ $line['sip_port'] ?? '5060' }}</RegisterPort>
            <RegisterUser>{{ $line['auth_id'] ?? '' }}</RegisterUser>
            <RegisterPswd>{{ $line['password'] ?? '' }}</RegisterPswd>
            <RegisterTTL>{{ $line['register_expires'] ?? '3600' }}</RegisterTTL>
            <EnableReg>{{ $enabled ? '1' : '0' }}</EnableReg>
            <ProxyAddr>{{ $line['outbound_proxy_primary'] ?? '' }}</ProxyAddr>
            <ProxyPort>{{ $line['sip_port'] ?? '5060' }}</ProxyPort>
            <BakProxyAddr>{{ $line['outbound_proxy_secondary'] ?? '' }}</BakProxyAddr>
            <EnableFailback>{{ $enableFailback }}</EnableFailback>
            <FailbackInterval>{{ $resolve('failback_interval', '1800') }}</FailbackInterval>
            <RingType>{{ $ringtone !== '' ? $ringtone : $defaultRingtone }}</RingType>
            <Transport>{{ $transport }}</Transport>
@if ($resolve('auto_tcp', '0') === '1')
            <AutoTCP>1</AutoTCP>
@endif
            <EnableRport>{{ $resolve('enable_rport', '1') }}</EnableRport>
            <NATUDPUpdate>{{ $resolve('nat_udp_update', '2') }}</NATUDPUpdate>
            <UDPUpdateTTL>{{ $resolve('udp_update_ttl', '15') }}</UDPUpdateTTL>
            <DTMFMode>{{ $resolve('dtmf_mode', '3') }}</DTMFMode>
            <Subscribe>{{ ($enabled && $resolve('subscribe_register', '1') === '1') ? '1' : '0' }}</Subscribe>
            <SubExpire>{{ $line['register_expires'] ?? '3600' }}</SubExpire>
            <MWINum>{{ ($enabled && $populateMwi) ? ($voicemail_number ?? '*97') : '' }}</MWINum>
            <MissedCallLog>{{ $resolve('missed_call_log', '1') }}</MissedCallLog>
            <VoiceCodecMap>{{ $lineAudioCodec }}</VoiceCodecMap>
            @if (!empty($videoEnabled) && ($includeLineVideoCodec ?? true))
            <VideoCodecMap>{{ $resolve('video_codec', 'H264') }}</VideoCodecMap>
            @endif
            <MediaCrypto>{{ $mediaCrypto }}</MediaCrypto>
            <ReqWithPort>{{ $resolve('req_with_port', '1') }}</ReqWithPort>
            <UpdateRegExpire>{{ $resolve('update_reg_expire', '1') }}</UpdateRegExpire>
            <UnregisterOnBoot>{{ ($useUnregisterOnBoot ?? false) ? '1' : '0' }}</UnregisterOnBoot>
            <EnableMACHeader>{{ ($useMacHeaders ?? false) ? '1' : '0' }}</EnableMACHeader>
            <EnableRegisterMAC>{{ ($useMacHeaders ?? false) ? '1' : '0' }}</EnableRegisterMAC>
            <BLFDialogMatch>{{ $resolve('blf_dialog_match', '1') }}</BLFDialogMatch>
            <EnableDeal180>{{ $resolve('enable_deal_180', '1') }}</EnableDeal180>
            <EnableXferBack>{{ $resolve('enable_xfer_back', '1') }}</EnableXferBack>
            <UserIsPhone>{{ $userIsPhone }}</UserIsPhone>
            <StrictProxy>{{ $resolve('strict_proxy', '1') }}</StrictProxy>
            <RFCVer>{{ $resolve('rfc_ver', '1') }}</RFCVer>
            <TLSVersion>{{ $resolve('tls_version', '2') }}</TLSVersion>
            <RegFailInterval>{{ $resolve('reg_fail_interval', '32') }}</RegFailInterval>
            <CallerIdType>{{ $resolve('caller_id_type', '4') }}</CallerIdType>
        </line>
