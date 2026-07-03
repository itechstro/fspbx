<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update266
{
    private const VERSION = '1.9.3.7';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_07_03_000002_create_domain_usage_limit_alerts_table.php',
            ]);
            echo trim(Artisan::output())."\n";

            DefaultSettings::query()->updateOrCreate(
                [
                    'default_setting_category' => 'limit',
                    'default_setting_subcategory' => 'ai_usage_alert_email',
                ],
                [
                    'default_setting_name' => 'text',
                    'default_setting_value' => '',
                    'default_setting_enabled' => 'false',
                    'default_setting_description' => 'Comma-separated email addresses for AI usage limit alerts. Leave blank to fall back to Support Email.',
                ]
            );

            echo 'Seeded limit.ai_usage_alert_email default setting.'."\n";

            DefaultSettings::query()->updateOrCreate(
                [
                    'default_setting_category' => 'limit',
                    'default_setting_subcategory' => 'ai_usage_alert_approaching_percent',
                ],
                [
                    'default_setting_name' => 'numeric',
                    'default_setting_value' => '80',
                    'default_setting_enabled' => 'true',
                    'default_setting_description' => 'Send an approaching-limit alert when monthly AI usage reaches this percentage of the assigned limit (1-100).',
                ]
            );

            echo 'Seeded limit.ai_usage_alert_approaching_percent default setting.'."\n";
            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.": {$exception->getMessage()}\n";

            return false;
        }
    }
}
