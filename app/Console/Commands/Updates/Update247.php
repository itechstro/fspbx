<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Throwable;

class Update247
{
    private const VERSION = '1.9.0.5';

    public function apply(): bool
    {
        try {
            DefaultSettings::query()->firstOrCreate(
                [
                    'default_setting_category' => 'mobile_apps',
                    'default_setting_subcategory' => 'cloudplay_send_qr_code',
                ],
                [
                    'default_setting_name' => 'boolean',
                    'default_setting_value' => 'false',
                    'default_setting_enabled' => 'true',
                    'default_setting_description' => 'Ask CloudPLAY to email QR credentials on user create, update, and reset.',
                ],
            );

            echo "Ensured mobile_apps.cloudplay_send_qr_code default setting exists.\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
