<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use Illuminate\Support\Str;
use Throwable;

class Update223
{
    private const VERSION = '1.8.8.30';

    private const MENU_TITLE = 'Recorder';

    private const MENU_LINK = '/recorder';

    public function apply(): bool
    {
        try {
            $this->ensureRecorderMenuItem();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureRecorderMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Recorder menu item.\n";

            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found; skipping Recorder menu item.\n";

            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_link', self::MENU_LINK)
            ->first();

        if (! $menuItem) {
            $callHistory = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where('menu_item_parent_uuid', $applicationsItem->menu_item_uuid)
                ->where('menu_item_link', '/call-detail-records')
                ->first();

            $order = $callHistory
                ? ((int) $callHistory->menu_item_order + 1)
                : $this->nextMenuItemOrder($menu, $applicationsItem);

            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
                'menu_item_title' => self::MENU_TITLE,
                'menu_item_link' => self::MENU_LINK,
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $order,
            ]);

            echo 'Added Recorder menu item under Applications.' . PHP_EOL;
        } else {
            $menuItem->forceFill([
                'menu_item_title' => self::MENU_TITLE,
                'menu_item_link' => self::MENU_LINK,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
            ])->save();

            echo 'Recorder menu item already exists; ensured title and link.' . PHP_EOL;
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin', 'user']);
    }

    private function nextMenuItemOrder(Menu $menu, MenuItem $parentItem): int
    {
        return ((int) MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_parent_uuid', $parentItem->menu_item_uuid)
            ->max('menu_item_order')) + 1;
    }

    private function ensureMenuLanguage(Menu $menu, MenuItem $menuItem): void
    {
        $language = MenuLanguage::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_uuid', $menuItem->menu_item_uuid)
            ->where('menu_language', 'en-us')
            ->first();

        if ($language) {
            if ($language->menu_item_title !== self::MENU_TITLE) {
                $language->forceFill(['menu_item_title' => self::MENU_TITLE])->save();
            }

            return;
        }

        MenuLanguage::query()->create([
            'menu_language_uuid' => (string) Str::uuid(),
            'menu_uuid' => $menu->menu_uuid,
            'menu_item_uuid' => $menuItem->menu_item_uuid,
            'menu_language' => 'en-us',
            'menu_item_title' => self::MENU_TITLE,
        ]);
    }

    private function ensureMenuItemGroups(Menu $menu, MenuItem $menuItem, array $groupNames): void
    {
        foreach ($groupNames as $groupName) {
            $group = Groups::query()
                ->where('group_name', $groupName)
                ->first();

            if (! $group) {
                continue;
            }

            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $menuItem->menu_item_uuid)
                ->where('group_uuid', $group->group_uuid)
                ->exists();

            if ($exists) {
                continue;
            }

            MenuItemGroup::query()->create([
                'menu_item_group_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'group_name' => $groupName,
                'group_uuid' => $group->group_uuid,
            ]);
        }
    }
}
