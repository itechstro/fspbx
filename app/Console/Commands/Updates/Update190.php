<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Carbon;
use Throwable;

class Update190
{
    private const VERSION = '1.8.7.3';

    private const TIME_FORMAT_DESCRIPTION = '12-hour or 24-hour display: use 12h or 24h (FusionPBX style), or a PHP format such as g:i A or H:i. Leave blank to use the country setting.';

    public function apply(): bool
    {
        try {
            $this->refreshTimeFormatSettingDescription();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function refreshTimeFormatSettingDescription(): void
    {
        $setting = DefaultSettings::query()
            ->where('default_setting_category', 'domain')
            ->where('default_setting_subcategory', 'time_format')
            ->where('default_setting_name', '!=', 'array')
            ->first();

        if (! $setting) {
            return;
        }

        $setting->fill([
            'default_setting_description' => self::TIME_FORMAT_DESCRIPTION,
            'update_date' => Carbon::now(),
        ])->save();

        echo "Updated domain.time_format setting description.\n";
    }
}
