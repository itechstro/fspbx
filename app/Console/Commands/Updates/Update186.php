<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Carbon;
use Throwable;

class Update186
{
    private const VERSION = '1.8.6';

    public function apply(): bool
    {
        try {
            $this->ensureTranslationLanguageDefaultSetting();
            echo "Update " . self::VERSION . " completed successfully.\n";
            return true;
        } catch (Throwable $exception) {
            echo "Error applying update " . self::VERSION . ": {$exception->getMessage()}\n";
            return false;
        }
    }

    private function ensureTranslationLanguageDefaultSetting(): void
    {
        $setting = [
            'default_setting_category' => 'call_transcription',
            'default_setting_subcategory' => 'transcription_translation_language',
            'default_setting_name' => 'text',
            'default_setting_value' => 'en-us',
            'default_setting_enabled' => true,
            'default_setting_description' => 'Target language for AI transcript translation. Examples: en-us, zh-cn, ms, ta.',
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
            echo "Updated call_transcription.transcription_translation_language default setting.\n";
            return;
        }

        DefaultSettings::create([
            ...$setting,
            'insert_date' => Carbon::now(),
        ]);

        echo "Created call_transcription.transcription_translation_language default setting.\n";
    }
}

