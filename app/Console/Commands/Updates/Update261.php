<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update261
{
    private const VERSION = '1.9.3.2';

    public function apply(): bool
    {
        try {
            $this->seedFanvilTimeZoneDefault();
            $this->seedFanvilTemplates();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedFanvilTimeZoneDefault(): void
    {
        $created = DefaultSettings::query()->firstOrCreate(
            [
                'default_setting_category' => 'provision',
                'default_setting_subcategory' => 'fanvil_time_zone',
                'default_setting_name' => 'text',
            ],
            [
                'default_setting_value' => '32',
                'default_setting_enabled' => true,
                'default_setting_description' => 'Fanvil time zone index. Use 32 for UTC+8 on Fanvil X-series phones.',
            ],
        );

        if ($created->wasRecentlyCreated) {
            echo "Seeded fanvil_time_zone default setting.\n";

            return;
        }

        echo "fanvil_time_zone default setting already exists.\n";
    }

    private function seedFanvilTemplates(): void
    {
        $exitCode = Artisan::call('prov:templates:seed', [
            '--vendor' => 'fanvil',
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'prov:templates:seed --vendor=fanvil failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Re-seeded Fanvil provisioning templates.\n";
    }
}
