<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Throwable;

class Update197
{
    private const VERSION = '1.8.8.5';

    public function apply(): bool
    {
        try {
            $this->disableLegacyMobileAppSettings();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function disableLegacyMobileAppSettings(): void
    {
        $hidden = config('mobile_app_settings.hidden_subcategories', []);

        if ($hidden === []) {
            echo "No hidden mobile app settings configured; skipping.\n";

            return;
        }

        $updated = DefaultSettings::query()
            ->where('default_setting_category', 'mobile_apps')
            ->whereIn('default_setting_subcategory', $hidden)
            ->where('default_setting_enabled', 'true')
            ->update(['default_setting_enabled' => 'false']);

        echo "Disabled {$updated} legacy mobile app default setting row(s).\n";
    }
}
