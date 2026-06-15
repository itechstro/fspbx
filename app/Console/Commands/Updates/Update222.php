<?php

namespace App\Console\Commands\Updates;

use App\Services\Install\BrandingAssets;
use Throwable;

class Update222
{
    private const VERSION = '1.8.8.29';

    public function apply(): bool
    {
        try {
            $branding = app(BrandingAssets::class);
            $branding->install(forceThemeSettings: false);
            $branding->applyThemeSettingsIfLegacy();
            $branding->ensureAppNameEnv();

            echo "Installed CloudPLAY Talk branding assets.\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
