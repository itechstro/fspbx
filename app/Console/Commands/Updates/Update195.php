<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Throwable;

class Update195
{
    private const VERSION = '1.8.8.3';

    public function apply(): bool
    {
        try {
            $this->moveMobileAppsMenuItem();
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
            $applicationsParent = MenuItem::query()
                ->where('menu_uuid', $item->menu_uuid)
                ->where('menu_item_title', 'Applications')
                ->whereNull('menu_item_parent_uuid')
                ->first();

            if (! $applicationsParent) {
                echo "Applications parent not found for menu {$item->menu_uuid}; skipping.\n";

                continue;
            }

            if ($item->menu_item_parent_uuid === $applicationsParent->menu_item_uuid) {
                continue;
            }

            $maxOrder = (int) MenuItem::query()
                ->where('menu_item_parent_uuid', $applicationsParent->menu_item_uuid)
                ->max('menu_item_order');

            $item->update([
                'menu_item_parent_uuid' => $applicationsParent->menu_item_uuid,
                'menu_item_order' => $maxOrder + 1,
            ]);

            $moved++;
        }

        echo "Moved {$moved} Mobile Apps menu item(s) to Applications.\n";
    }
}
