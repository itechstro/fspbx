<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Services\SrsRecorderDialplanService;
use Throwable;

class Update272
{
    private const VERSION = '1.9.3.13';

    private const SETTING_DESCRIPTION = 'Enable the Recorder module and provision srs_recorder dialplans, the siprec SIP user, and the recorder conference profile.';

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
                    echo "Provisioned srs_recorder dialplan and siprec user for {$domain->domain_name}.\n";
                });

            DefaultSettings::query()
                ->where('default_setting_category', 'xml_cdr')
                ->where('default_setting_subcategory', 'enable_recorder')
                ->update([
                    'default_setting_description' => self::SETTING_DESCRIPTION,
                ]);

            echo "Provisioned recorder resources for {$synced} domain(s).\n";
            echo 'Updated xml_cdr.enable_recorder default setting description.'."\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
