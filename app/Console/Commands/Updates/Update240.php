<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Models\Permissions;
use App\Services\PhoneFirmwareService;
use Illuminate\Support\Str;
use Throwable;

class Update240
{
    private const VERSION = '1.8.8.47';

    private const MENU_TITLE = 'Phone Firmware';

    private const MENU_LINK = '/phone-firmware';

    public function apply(): bool
    {
        try {
            app(PhoneFirmwareService::class)->ensureRoot();
            echo "Ensured phone firmware storage directory.\n";

            $this->ensurePermissions();
            $this->ensureMenuItem();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensurePermissions(): void
    {
        $applicationName = 'Devices';
        $permissions = [
            'phone_firmware_view',
            'phone_firmware_upload',
            'phone_firmware_delete',
        ];
        $now = date('Y-m-d H:i:s');

        $existingPermissions = Permissions::query()
            ->whereIn('permission_name', $permissions)
            ->pluck('permission_name')
            ->all();

        $permissionRows = collect($permissions)
            ->diff($existingPermissions)
            ->map(fn ($permissionName) => [
                'permission_uuid' => (string) Str::uuid(),
                'application_name' => $applicationName,
                'permission_name' => $permissionName,
                'insert_date' => $now,
            ])
            ->values()
            ->all();

        if ($permissionRows !== []) {
            Permissions::query()->insert($permissionRows);
            echo 'Created ' . count($permissionRows) . " phone firmware permission row(s).\n";
        }

        $assignments = [
            'superadmin' => $permissions,
            'admin' => [
                'phone_firmware_view',
                'phone_firmware_upload',
            ],
        ];

        foreach ($assignments as $groupName => $groupPermissions) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; phone firmware permissions not assigned.\n";
                continue;
            }

            $existingGroupPermissions = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->whereIn('permission_name', $groupPermissions)
                ->pluck('permission_name')
                ->all();

            $groupPermissionRows = collect($groupPermissions)
                ->diff($existingGroupPermissions)
                ->map(fn ($permissionName) => [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid' => $group->group_uuid,
                    'group_name' => $groupName,
                    'permission_name' => $permissionName,
                    'permission_protected' => 'true',
                    'permission_assigned' => 'true',
                    'insert_date' => $now,
                ])
                ->values()
                ->all();

            if ($groupPermissionRows === []) {
                continue;
            }

            GroupPermissions::query()->insert($groupPermissionRows);
            echo 'Assigned ' . count($groupPermissionRows) . " phone firmware permission(s) to group '{$groupName}'.\n";
        }
    }

    private function ensureMenuItem(): void
    {
        $menu = Menu::query()
            ->where('menu_name', 'fspbx')
            ->first();

        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Phone Firmware menu item.\n";

            return;
        }

        $accountsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Accounts')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $accountsItem) {
            echo "Accounts menu item was not found; skipping Phone Firmware menu item.\n";

            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_link', self::MENU_LINK)
            ->first();

        if (! $menuItem) {
            $devicesItem = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where('menu_item_parent_uuid', $accountsItem->menu_item_uuid)
                ->where('menu_item_link', '/devices')
                ->first();

            $order = $devicesItem
                ? ((int) $devicesItem->menu_item_order + 1)
                : $this->nextMenuItemOrder($menu, $accountsItem);

            $menuItem = MenuItem::query()->create([
                'menu_item_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_parent_uuid' => $accountsItem->menu_item_uuid,
                'menu_item_title' => self::MENU_TITLE,
                'menu_item_link' => self::MENU_LINK,
                'menu_item_icon' => '',
                'menu_item_category' => 'internal',
                'menu_item_protected' => 'false',
                'menu_item_order' => $order,
            ]);

            echo 'Added Phone Firmware menu item under Accounts.' . PHP_EOL;
        } else {
            $menuItem->forceFill([
                'menu_item_title' => self::MENU_TITLE,
                'menu_item_link' => self::MENU_LINK,
                'menu_item_parent_uuid' => $accountsItem->menu_item_uuid,
            ])->save();

            echo 'Phone Firmware menu item already exists; ensured title and link.' . PHP_EOL;
        }

        $this->ensureMenuLanguage($menu, $menuItem);
        $this->ensureMenuItemGroups($menu, $menuItem, ['superadmin', 'admin']);
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
