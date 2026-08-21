<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Services\Provisioning\IntradeProvisionSettings;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update280
{
    private const VERSION = '1.9.7.1';

    public function apply(): bool
    {
        try {
            $this->seedIntradeProvisionSettings();
            $this->seedIntradeTemplates();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedIntradeProvisionSettings(): void
    {
        $inserted = 0;

        foreach (IntradeProvisionSettings::definitions() as $setting) {
            $created = DefaultSettings::query()->firstOrCreate(
                [
                    'default_setting_category' => $setting['default_setting_category'],
                    'default_setting_subcategory' => $setting['default_setting_subcategory'],
                    'default_setting_name' => $setting['default_setting_name'],
                ],
                $setting,
            );

            if ($created->wasRecentlyCreated) {
                $inserted++;
            }
        }

        echo "Seeded {$inserted} Intrade provision default settings.\n";
    }

    private function seedIntradeTemplates(): void
    {
        $exitCode = Artisan::call('prov:templates:seed', [
            '--vendor' => 'intrade',
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'prov:templates:seed --vendor=intrade failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Re-seeded Intrade provisioning templates.\n";

        Artisan::call('view:clear');
        echo "Cleared compiled Blade views.\n";
    }
}
