<?php

namespace App\Console\Commands\Updates;

use App\Models\Domain;
use App\Models\FusionCache;
use App\Services\SrsRecorderDialplanService;
use Illuminate\Support\Facades\File;
use Throwable;

class Update273
{
    private const VERSION = '1.9.3.14';

    private const RUNTIME_DIRECTORY_LUA = '/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/directory/directory.lua';

    public function apply(): bool
    {
        try {
            $this->deployDirectoryLua();
            $this->provisionSiprecUsers();

            FusionCache::clear('directory:*');

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function deployDirectoryLua(): void
    {
        $source = base_path('resources/freeswitch_scripts/app/xml_handler/resources/scripts/directory/directory.lua');

        if (! File::exists($source)) {
            echo "WARNING: {$source} not found. Skipping directory.lua deploy.\n";

            return;
        }

        if (! File::exists(self::RUNTIME_DIRECTORY_LUA) && ! File::isDirectory(dirname(self::RUNTIME_DIRECTORY_LUA))) {
            echo 'WARNING: ' . dirname(self::RUNTIME_DIRECTORY_LUA) . " not found. Skipping directory.lua deploy.\n";

            return;
        }

        File::ensureDirectoryExists(dirname(self::RUNTIME_DIRECTORY_LUA));
        File::copy($source, self::RUNTIME_DIRECTORY_LUA);
        echo 'Deployed SIPREC-safe directory cache lookup to ' . self::RUNTIME_DIRECTORY_LUA . ".\n";
    }

    private function provisionSiprecUsers(): void
    {
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
                echo "Provisioned srs_recorder dialplan and siprec user for {$domain->domain_name}.\n";
            });

        echo "Provisioned recorder resources for {$synced} domain(s).\n";
    }
}
