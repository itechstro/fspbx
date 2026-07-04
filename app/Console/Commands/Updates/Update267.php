<?php

namespace App\Console\Commands\Updates;

use App\Models\Domain;
use App\Services\SrsRecorderDialplanService;
use Throwable;

class Update267
{
    private const VERSION = '1.9.3.8';

    public function apply(): bool
    {
        try {
            $service = app(SrsRecorderDialplanService::class);
            $synced = 0;

            Domain::query()
                ->where('domain_enabled', 'true')
                ->orderBy('domain_name')
                ->each(function (Domain $domain) use ($service, &$synced) {
                    if (! $service->isRecorderEnabledForDomain($domain->domain_uuid)) {
                        return;
                    }

                    $service->provisionForDomain($domain);
                    $synced++;
                    echo "Re-provisioned srs_recorder dialplan for {$domain->domain_name}.\n";
                });

            echo "Re-provisioned srs_recorder dialplans for {$synced} domain(s).\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
