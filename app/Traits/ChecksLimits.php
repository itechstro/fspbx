<?php

namespace App\Traits;

use App\Models\MobileAppUsers;
use App\Services\DomainLimitsService;
use App\Services\DomainUsageService;

trait ChecksLimits
{
    /**
     * Friendly names for resources.
     */
    protected array $friendlyNames = [
        'users'                 => 'Users',
        'extensions'            => 'Extensions',
        'ring_groups'           => 'Ring Groups',
        'ivr_menus'             => 'Virtual Receptionists',
        'gateways'              => 'Gateways',
        'devices'               => 'Devices',
        'destinations'          => 'Phone Numbers',
        'call_center_queues'    => 'Call Center Queues',
        'mobile_app_users'       => 'Mobile App Users',
        // Add more here as needed
    ];

    /**
     * Enforce domain resource limit on any model.
     *
     * @param string $resourceKey — e.g. 'extensions', 'ring_groups', 'ivr_menus'
     * @param string $modelClass  — fully qualified model class
     * @param string $column      — domain column name (usually domain_uuid)
     * @param string $errorKey    — used for domain error message lookup
     */
    public function enforceLimit(
        $resourceKey,
        $modelClass,
        $column = 'domain_uuid',
        $errorKey = null,
        ?string $domainUuid = null,
        int $seatsRequired = 1,
    ) {
        $domain = $domainUuid ?? session('domain_uuid');

        $limit = get_limit_setting($resourceKey, $domain);
        if ($limit === null) {
            return; // No limit configured
        }

        // Friendly name lookup (fallback to readable version of resourceKey)
        $friendly = $this->friendlyNames[$resourceKey]
            ?? ucfirst(str_replace('_', ' ', $resourceKey));

        // Setting key, e.g. "user_limit_error"
        $errorSettingKey = $errorKey ?? "{$resourceKey}_limit_error";

        // Custom message OR fallback
        $errorText = get_domain_setting($errorSettingKey, $domain)
            ?? "You have reached the maximum number of {$friendly} allowed ({$limit}).";

        if ($resourceKey === 'mobile_app_users') {
            $count = (float) MobileAppUsers::countActiveLicensesForDomain($domain);
        } elseif (app(DomainLimitsService::class)->metric($resourceKey)) {
            $count = app(DomainLimitsService::class)->resolveUsage(
                $resourceKey,
                $domain,
                null,
                app(DomainUsageService::class),
            );
        } else {
            $count = (float) $modelClass::where($column, $domain)->count();
        }

        if (($count + $seatsRequired) > $limit) {
            $errorText = sprintf(
                '%s Current usage: %s. Limit: %s.',
                rtrim($errorText, '.'),
                $this->formatLimitCount($count),
                $this->formatLimitCount($limit),
            );

            return response()->json([
                'errors' => [
                    $resourceKey => [$errorText],
                ],
            ], 403);
        }

        return null;
    }

    protected function formatLimitCount(float $value): string
    {
        if (floor($value) == $value) {
            return (string) (int) $value;
        }

        return (string) $value;
    }
}
