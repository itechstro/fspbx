<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Services\Provisioning\IbratroProvisionSettings;
use Throwable;

class Update210
{
    private const VERSION = '1.8.8.17';

    public function apply(): bool
    {
        try {
            $this->seedIbratroProvisionSettings();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedIbratroProvisionSettings(): void
    {
        $inserted = 0;

        foreach (IbratroProvisionSettings::definitions() as $setting) {
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

        echo "Seeded {$inserted} Ibratro provision default settings.\n";
    }
}
