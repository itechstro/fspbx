<?php

namespace App\Services\MobileApp;

use App\Contracts\MobileAppProviderInterface;
use App\Services\CloudPlayApiService;

class MobileAppProviderResolver
{
    public function resolve(?string $provider = null): MobileAppProviderInterface
    {
        return app(CloudPlayApiService::class);
    }
}
