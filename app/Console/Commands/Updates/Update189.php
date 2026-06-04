<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Carbon;
use Throwable;

class Update189
{
    private const VERSION = '1.8.7.2';

    public function apply(): bool
    {
        try {
            $this->ensurePresentationFormatDefaultSettings();
            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensurePresentationFormatDefaultSettings(): void
    {
        $settings = [
            [
                'default_setting_category' => 'domain',
                'default_setting_subcategory' => 'date_format',
                'default_setting_name' => 'text',
                'default_setting_value' => '',
                'default_setting_enabled' => true,
                'default_setting_description' => 'Optional PHP date format override (e.g. d/m/Y, M j Y). Leave blank to use the country setting.',
            ],
            [
                'default_setting_category' => 'domain',
                'default_setting_subcategory' => 'time_format',
                'default_setting_name' => 'text',
                'default_setting_value' => '',
                'default_setting_enabled' => true,
                'default_setting_description' => '12-hour or 24-hour display: use 12h or 24h (FusionPBX style), or a PHP format such as g:i A or H:i. Leave blank to use the country setting.',
            ],
        ];

        foreach ($settings as $setting) {
            $existing = DefaultSettings::query()
                ->where('default_setting_category', $setting['default_setting_category'])
                ->where('default_setting_subcategory', $setting['default_setting_subcategory'])
                ->where('default_setting_name', '!=', 'array')
                ->first();

            if ($existing) {
                $existing->fill([
                    'default_setting_name' => $setting['default_setting_name'],
                    'default_setting_value' => $setting['default_setting_value'],
                    'default_setting_enabled' => $setting['default_setting_enabled'],
                    'default_setting_description' => $setting['default_setting_description'],
                ])->save();
                echo "Updated domain.{$setting['default_setting_subcategory']} default setting.\n";
                continue;
            }

            DefaultSettings::create([
                ...$setting,
                'insert_date' => Carbon::now(),
            ]);

            echo "Created domain.{$setting['default_setting_subcategory']} default setting.\n";
        }
    }
}
