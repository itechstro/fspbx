<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Throwable;

class Update248
{
    private const VERSION = '1.9.0.6';

    public function apply(): bool
    {
        try {
            $updatedDefaults = DefaultSettings::query()
                ->where('default_setting_category', 'mobile_apps')
                ->where('default_setting_subcategory', 'cloudplay_qr_format')
                ->update([
                    'default_setting_enabled' => 'false',
                    'default_setting_description' => 'Deprecated. CloudPLAY QR codes always use the portal token from get-qr-code.',
                ]);

            $updatedDomains = DomainSettings::query()
                ->where('domain_setting_category', 'mobile_apps')
                ->where('domain_setting_subcategory', 'cloudplay_qr_format')
                ->update([
                    'domain_setting_enabled' => 'false',
                    'domain_setting_description' => 'Deprecated. CloudPLAY QR codes always use the portal token from get-qr-code.',
                ]);

            echo "Disabled {$updatedDefaults} default and {$updatedDomains} domain cloudplay_qr_format rows.\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
