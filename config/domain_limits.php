<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant license limits (category "limit" in settings tables)
    |--------------------------------------------------------------------------
    |
    | usage_type:
    | - count: live resource count for the domain (not period-scoped)
    | - ledger: monthly meter from domain_usage_ledger (or outbound CDR)
    |
    */
    'metrics' => [
        'extensions' => [
            'group' => 'Tenant resources',
            'display' => 'Extensions',
            'unit' => 'extensions',
            'usage_type' => 'count',
            'model' => \App\Models\Extensions::class,
        ],
        'users' => [
            'group' => 'Tenant resources',
            'display' => 'Users',
            'unit' => 'users',
            'usage_type' => 'count',
            'model' => \App\Models\User::class,
        ],
        'devices' => [
            'group' => 'Tenant resources',
            'display' => 'Devices',
            'unit' => 'devices',
            'usage_type' => 'count',
            'model' => \App\Models\Devices::class,
        ],
        'destinations' => [
            'group' => 'Tenant resources',
            'display' => 'Phone numbers',
            'unit' => 'numbers',
            'usage_type' => 'count',
            'model' => \App\Models\Destinations::class,
        ],
        'ring_groups' => [
            'group' => 'Tenant resources',
            'display' => 'Ring groups',
            'unit' => 'ring groups',
            'usage_type' => 'count',
            'model' => \App\Models\RingGroups::class,
        ],
        'ivr_menus' => [
            'group' => 'Tenant resources',
            'display' => 'Virtual receptionists',
            'unit' => 'IVR menus',
            'usage_type' => 'count',
            'model' => \App\Models\IvrMenus::class,
        ],
        'gateways' => [
            'group' => 'Tenant resources',
            'display' => 'Gateways',
            'unit' => 'gateways',
            'usage_type' => 'count',
            'model' => \App\Models\Gateways::class,
        ],
        'call_center_queues' => [
            'group' => 'Tenant resources',
            'display' => 'Call center queues',
            'unit' => 'queues',
            'usage_type' => 'count',
            'model' => \App\Models\CallCenterQueues::class,
        ],
        'mobile_app_users' => [
            'group' => 'Tenant resources',
            'display' => 'Mobile app users',
            'unit' => 'users',
            'usage_type' => 'count',
            'model' => \App\Models\MobileAppUsers::class,
        ],
        'ai_transcription_minutes' => [
            'group' => 'AI services (monthly)',
            'display' => 'AI transcription minutes',
            'unit' => 'minutes',
            'usage_type' => 'ledger',
            'ledger_metric' => 'ai_transcription_seconds',
            'scale' => 60,
        ],
        'ai_summary_requests' => [
            'group' => 'AI services (monthly)',
            'display' => 'AI summary requests',
            'unit' => 'requests',
            'usage_type' => 'ledger',
            'ledger_metric' => 'ai_summary_count',
            'scale' => 1,
        ],
        'ai_translation_requests' => [
            'group' => 'AI services (monthly)',
            'display' => 'AI translation requests',
            'unit' => 'requests',
            'usage_type' => 'ledger',
            'ledger_metric' => 'ai_translation_count',
            'scale' => 1,
        ],
        'ai_executive_summary_requests' => [
            'group' => 'AI services (monthly)',
            'display' => 'AI executive summary requests',
            'unit' => 'requests',
            'usage_type' => 'ledger',
            'ledger_metric' => 'ai_executive_summary_count',
            'scale' => 1,
        ],
        'ai_spend_usd_monthly' => [
            'group' => 'AI services (monthly)',
            'display' => 'Estimated AI spend (USD)',
            'unit' => 'usd',
            'usage_type' => 'ledger',
            'ledger_metric' => 'ai_spend_usd',
            'scale' => 1,
        ],
        'outbound_minutes_monthly' => [
            'group' => 'Calling (monthly)',
            'display' => 'Outbound call minutes',
            'unit' => 'minutes',
            'usage_type' => 'ledger',
            'ledger_metric' => 'outbound_seconds',
            'scale' => 60,
        ],
    ],
];
