<?php

namespace App\Services\Install;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class BrandingAssets
{
    public const APP_NAME = 'CloudPLAY Talk';

    public const SHORT_NAME = 'CloudPLAY';

    private const SOURCE_DIR = 'install/assets';

    private const ASSET_FILES = [
        'android-chrome-192x192.png',
        'android-chrome-384x384.png',
        'apple-touch-icon.png',
        'browserconfig.xml',
        'favicon-16x16.png',
        'favicon-32x32.png',
        'favicon.ico',
        'logo.png',
        'mstile-150x150.png',
        'safari-pinned-tab.svg',
        'site.webmanifest',
    ];

    public function install(bool $forceThemeSettings = true): void
    {
        $this->ensureStorageLink();
        $this->copyAssets();

        if ($forceThemeSettings) {
            $this->applyThemeSettings();
        }
    }

    public function applyThemeSettingsIfLegacy(): void
    {
        $this->upsertThemeSetting('title', self::APP_NAME, $this->isLegacyBrandingValue(...));
        $this->upsertThemeSetting('footer', $this->defaultFooter(), $this->isLegacyFooterValue(...));
    }

    public function ensureAppNameEnv(): void
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            return;
        }

        $env = File::get($envPath);

        if (!preg_match('/^APP_NAME=(.*)$/m', $env, $matches)) {
            return;
        }

        $currentName = trim($matches[1], "\"'");

        if (!in_array($currentName, ['FS PBX', 'Laravel'], true)) {
            return;
        }

        $env = preg_replace(
            '/^APP_NAME=.*$/m',
            'APP_NAME="' . self::APP_NAME . '"',
            $env,
            1
        );

        File::put($envPath, rtrim($env) . "\n");
    }

    public function defaultFooter(): string
    {
        return '© Copyright 2008 - ' . date('Y') . ' ' . self::APP_NAME . '. All rights reserved.';
    }

    private function ensureStorageLink(): void
    {
        Artisan::call('storage:link', ['--force' => true]);
    }

    private function copyAssets(): void
    {
        $sourceDir = base_path(self::SOURCE_DIR);
        $targetDir = storage_path('app/public');

        if (!is_dir($sourceDir)) {
            throw new \RuntimeException("Branding asset source directory not found: {$sourceDir}");
        }

        File::ensureDirectoryExists($targetDir);

        foreach (self::ASSET_FILES as $file) {
            $source = $sourceDir . DIRECTORY_SEPARATOR . $file;

            if (!File::exists($source)) {
                throw new \RuntimeException("Branding asset missing: {$source}");
            }

            File::copy($source, $targetDir . DIRECTORY_SEPARATOR . $file);
        }
    }

    private function applyThemeSettings(): void
    {
        $this->replaceThemeSetting('title', self::APP_NAME);
        $this->replaceThemeSetting('footer', $this->defaultFooter());
    }

    private function replaceThemeSetting(string $subcategory, string $value): void
    {
        DefaultSettings::where('default_setting_category', 'theme')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_name', '!=', 'array')
            ->get()
            ->each
            ->delete();

        DefaultSettings::create([
            'default_setting_category' => 'theme',
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => 'text',
            'default_setting_value' => $value,
            'default_setting_enabled' => true,
            'default_setting_description' => $subcategory === 'title'
                ? 'Set the hover logo title.'
                : '',
            'insert_date' => now(),
        ]);
    }

    private function upsertThemeSetting(string $subcategory, string $value, callable $shouldUpdate): void
    {
        $settings = DefaultSettings::where('default_setting_category', 'theme')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_name', '!=', 'array')
            ->get();

        if ($settings->isEmpty()) {
            $this->replaceThemeSetting($subcategory, $value);

            return;
        }

        foreach ($settings as $setting) {
            if ($shouldUpdate((string) $setting->default_setting_value)) {
                $setting->update(['default_setting_value' => $value]);
            }
        }
    }

    private function isLegacyBrandingValue(string $value): bool
    {
        return trim($value) === '' || stripos($value, 'FS PBX') !== false;
    }

    private function isLegacyFooterValue(string $value): bool
    {
        return trim($value) === '' || stripos($value, 'FS PBX') !== false;
    }
}
