<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Throwable;

class Update193
{
    private const VERSION = '1.8.8.1';

    public function apply(): bool
    {
        try {
            $this->seedMobileAppProviderSettings();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedMobileAppProviderSettings(): void
    {
        $settings = [
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'mobile_app_provider',
                'default_setting_name' => 'text',
                'default_setting_value' => 'ringotel',
                'default_setting_enabled' => true,
                'default_setting_description' => 'Mobile app provider: ringotel or cloudplay',
            ],
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'cloudplay_api_url',
                'default_setting_name' => 'text',
                'default_setting_value' => 'https://vgate.cloudplay.cloud:8091/v1.58.5/api',
                'default_setting_enabled' => true,
                'default_setting_description' => 'CloudPLAY provisioning API base URL',
            ],
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'cloudplay_admin_username',
                'default_setting_name' => 'text',
                'default_setting_value' => '',
                'default_setting_enabled' => true,
                'default_setting_description' => 'CloudPLAY admin username for tenant provisioning',
            ],
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'cloudplay_admin_password',
                'default_setting_name' => 'text',
                'default_setting_value' => '',
                'default_setting_enabled' => true,
                'default_setting_description' => 'CloudPLAY admin password for tenant provisioning',
            ],
        ];

        foreach ($settings as $setting) {
            DefaultSettings::firstOrCreate(
                [
                    'default_setting_category' => $setting['default_setting_category'],
                    'default_setting_subcategory' => $setting['default_setting_subcategory'],
                ],
                $setting
            );
        }

        echo "Seeded CloudPLAY mobile app provider settings.\n";
    }
}
