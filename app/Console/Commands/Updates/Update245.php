<?php

namespace App\Console\Commands\Updates;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Throwable;

class Update245
{
    private const VERSION = '1.9.0.3';

    private const DUPLICATE_MENU_LINK = '/logs?tab=freeswitch_logs';

    public function apply(): bool
    {
        try {
            $this->removeDuplicateLogViewerMenuItem();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function removeDuplicateLogViewerMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();

        $query = MenuItem::query()
            ->where(function ($builder) {
                $builder->where('menu_item_link', self::DUPLICATE_MENU_LINK)
                    ->orWhere(function ($nested) {
                        $nested->where('menu_item_title', 'Log Viewer')
                            ->where('menu_item_link', 'like', '/logs%');
                    });
            });

        if ($menu) {
            $query->where('menu_uuid', $menu->menu_uuid);
        }

        $menuItemUuids = $query->pluck('menu_item_uuid')->all();

        if ($menuItemUuids === []) {
            echo "Duplicate Log Viewer menu item not found.\n";

            return;
        }

        MenuLanguage::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        MenuItemGroup::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        $deleted = MenuItem::query()
            ->whereIn('menu_item_uuid', $menuItemUuids)
            ->delete();

        echo "Removed {$deleted} duplicate Log Viewer menu item(s).\n";
    }
}
