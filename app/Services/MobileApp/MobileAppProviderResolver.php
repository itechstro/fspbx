<?php

namespace App\Services\MobileApp;

use App\Contracts\MobileAppProviderInterface;
use App\Services\CloudPlayApiService;

class MobileAppProviderResolver
{
    public function resolve(?string $provider = null): MobileAppProviderInterface
    {
        $provider ??= get_mobile_app_provider(session('domain_uuid'));

        return match ($provider) {
            'ringotel' => app(RingotelMobileAppProvider::class),
            default => app(CloudPlayApiService::class),
        };
    }
}
