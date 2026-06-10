<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use Throwable;

class Update196
{
    private const VERSION = '1.8.8.4';

    public function apply(): bool
    {
        try {
            $this->moveMobileAppsMenuItem();
            $this->ensureAdvancedVisibleToAdmin();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function moveMobileAppsMenuItem(): void
    {
        $items = MenuItem::query()
            ->where('menu_item_link', '/apps')
            ->get();

        if ($items->isEmpty()) {
            echo "No /apps menu item found; skipping menu move.\n";

            return;
        }

        $moved = 0;

        foreach ($items as $item) {
            $advancedParent = MenuItem::query()
                ->where('menu_uuid', $item->menu_uuid)
                ->where('menu_item_title', 'Advanced')
                ->whereNull('menu_item_parent_uuid')
                ->first();

            if (! $advancedParent) {
                echo "Advanced parent not found for menu {$item->menu_uuid}; skipping.\n";

                continue;
            }

            if ($item->menu_item_parent_uuid === $advancedParent->menu_item_uuid) {
                continue;
            }

            $maxOrder = (int) MenuItem::query()
                ->where('menu_item_parent_uuid', $advancedParent->menu_item_uuid)
                ->max('menu_item_order');

            $item->update([
                'menu_item_parent_uuid' => $advancedParent->menu_item_uuid,
                'menu_item_order' => $maxOrder + 1,
            ]);

            $moved++;
        }

        echo "Moved {$moved} Mobile Apps menu item(s) to Advanced.\n";
    }

    private function ensureAdvancedVisibleToAdmin(): void
    {
        $adminGroup = Groups::query()->where('group_name', 'admin')->first();

        if (! $adminGroup) {
            echo "Admin group not found; skipping Advanced menu group assignment.\n";

            return;
        }

        $parents = MenuItem::query()
            ->where('menu_item_title', 'Advanced')
            ->whereNull('menu_item_parent_uuid')
            ->get();

        $added = 0;

        foreach ($parents as $parent) {
            $exists = MenuItemGroup::query()
                ->where('menu_item_uuid', $parent->menu_item_uuid)
                ->where('group_uuid', $adminGroup->group_uuid)
                ->exists();

            if ($exists) {
                continue;
            }

            MenuItemGroup::create([
                'menu_item_group_uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'menu_uuid' => $parent->menu_uuid,
                'menu_item_uuid' => $parent->menu_item_uuid,
                'group_name' => 'admin',
                'group_uuid' => $adminGroup->group_uuid,
            ]);

            $added++;
        }

        echo "Granted admin access to {$added} Advanced menu parent(s).\n";
    }
}
