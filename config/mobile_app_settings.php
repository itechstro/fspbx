<?php

return [
    'scopes' => [
        'shared' => [
            'dont_send_user_credentials',
            'apple_store_link',
            'google_play_link',
            'mac_link',
            'windows_link',
            'mobile_app_conn_protocol',
            'mobile_app_proxy',
        ],
        'cloudplay' => [
            'cloudplay_api_url',
            'cloudplay_admin_username',
            'cloudplay_admin_password',
        ],
    ],

    'hidden_subcategories' => [
        'mobile_app_provider',
        'ringotel_api_token',
        'organization_region',
        'package',
        'connection_port',
        'dont_verify_server_certificate',
        'disable_srtp',
        'multitenant_mode',
        'allow_call_recording',
        'max_registrations',
        'registration_ttl',
        'voicemail_extension',
        'pbx_features',
        'g722_enabled',
        'codec_priority',
        'allow_block_contacts',
        'sms_mode',
        'custom_web_pages',
        'show_call_settings',
        'allow_state_change',
        'allow_video_calls',
        'allow_internal_chat',
    ],

    'labels' => [
        'cloudplay_api_url' => 'CloudPLAY API URL',
        'cloudplay_admin_username' => 'CloudPLAY Admin Username',
        'cloudplay_admin_password' => 'CloudPLAY Admin Password',
        'mobile_app_conn_protocol' => 'SIP Protocol',
        'mobile_app_proxy' => 'SIP Outbound Proxy',
        'dont_send_user_credentials' => 'Secure User Credentials',
    ],

    'descriptions' => [
        'cloudplay_api_url' => 'CloudPLAY provisioning API base URL.',
        'cloudplay_admin_username' => 'CloudPLAY admin username for creating and listing customers.',
        'cloudplay_admin_password' => 'CloudPLAY admin password for the provisioning API.',
        'dont_send_user_credentials' => 'When enabled, welcome emails use a one-time password link instead of plain-text credentials.',
        'apple_store_link' => 'App Store download link included in mobile app credential emails.',
        'google_play_link' => 'Google Play download link included in mobile app credential emails.',
        'mac_link' => 'macOS desktop app download link for credential emails.',
        'windows_link' => 'Windows desktop app download link for credential emails.',
        'mobile_app_conn_protocol' => 'Default SIP transport for CloudPLAY user provisioning (udp, tcp, tls, or wss).',
        'mobile_app_proxy' => 'Default outbound SIP proxy for CloudPLAY user provisioning.',
    ],
];
