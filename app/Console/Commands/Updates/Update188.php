<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Carbon;
use Throwable;

class Update188
{
    private const VERSION = '1.8.7.1';

    public function apply(): bool
    {
        try {
            $this->ensureShowRecorderFilterDefaultSetting();
            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureShowRecorderFilterDefaultSetting(): void
    {
        $setting = [
            'default_setting_category' => 'xml_cdr',
            'default_setting_subcategory' => 'show_recorder_filter',
            'default_setting_name' => 'boolean',
            'default_setting_value' => 'true',
            'default_setting_enabled' => true,
            'default_setting_description' => 'Show recorder calls in Call History and provision srs_recorder, recorder_catch_<domain> dialplans, and the recorder conference profile.',
        ];

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
            echo "Updated xml_cdr.show_recorder_filter default setting.\n";
            return;
        }

        DefaultSettings::create([
            ...$setting,
            'insert_date' => Carbon::now(),
        ]);

        echo "Created xml_cdr.show_recorder_filter default setting.\n";
    }
}
