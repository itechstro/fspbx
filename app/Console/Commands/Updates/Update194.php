<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\MenuItem;
use App\Models\MenuLanguage;
use App\Support\MobileAppSettingsCatalog;
use Throwable;

class Update194
{
    private const VERSION = '1.8.8.2';

    public function apply(): bool
    {
        try {
            $this->renameMobileAppsMenuItem();
            $this->refreshMobileAppSettingDescriptions();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function renameMobileAppsMenuItem(): void
    {
        $items = MenuItem::query()
            ->where('menu_item_link', '/apps')
            ->get();

        if ($items->isEmpty()) {
            echo "No /apps menu item found; skipping menu rename.\n";

            return;
        }

        foreach ($items as $item) {
            $item->update(['menu_item_title' => 'Mobile Apps']);

            MenuLanguage::query()
                ->where('menu_item_uuid', $item->menu_item_uuid)
                ->update(['menu_item_title' => 'Mobile Apps']);
        }

        echo "Renamed {$items->count()} menu item(s) to Mobile Apps.\n";
    }

    private function refreshMobileAppSettingDescriptions(): void
    {
        $settings = DefaultSettings::query()
            ->where('default_setting_category', 'mobile_apps')
            ->get();

        $updated = 0;

        foreach ($settings as $setting) {
            $description = MobileAppSettingsCatalog::description(
                (string) $setting->default_setting_subcategory,
                $setting->default_setting_description
            );

            if ($description === null || $description === $setting->default_setting_description) {
                continue;
            }

            $setting->default_setting_description = $description;
            $setting->save();
            $updated++;
        }

        echo "Updated {$updated} mobile_apps setting description(s).\n";
    }
}
