<?php

namespace App\Console\Commands\Updates;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Groups;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuItemGroup;
use App\Models\MenuLanguage;
use App\Services\DialplanService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class Update271
{
    private const VERSION = '1.9.3.12';

    private const MENU_TITLE = 'Call Permissions';

    private const MENU_LINK = '/call-permissions';

    private const OLD_MENU_LINK = '/class-of-service';

    private const DIALPLAN_APP_UUID = 'c4a8f1e2-9b3d-4c7a-8f6e-1d2c3b4a5e6f';

    private const RUNTIME_DIRECTORY_LUA = '/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/directory/directory.lua';

    private const RUNTIME_LUA_DIR = '/usr/share/freeswitch/scripts/lua';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_07_08_120000_rename_class_of_service_to_call_permissions.php',
            ]);
            echo trim((string) Artisan::output()) . "\n";

            $this->renameMenuItem();
            $this->updateDialplans();
            $this->deployRuntimeLua();
            $this->patchRuntimeDirectoryLua();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function renameMenuItem(): void
    {
        $menu = Menu::query()->where('menu_name', 'fspbx')->first();
        if (! $menu) {
            echo "Menu 'fspbx' was not found; skipping Call Permissions menu rename.\n";

            return;
        }

        $applicationsItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_title', 'Applications')
            ->whereNull('menu_item_parent_uuid')
            ->first();

        if (! $applicationsItem) {
            echo "Applications menu item was not found; skipping Call Permissions menu rename.\n";

            return;
        }

        $menuItem = MenuItem::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where(function ($query) {
                $query->where('menu_item_link', self::OLD_MENU_LINK)
                    ->orWhere('menu_item_link', self::MENU_LINK)
                    ->orWhere('menu_item_title', 'Class of Service')
                    ->orWhere('menu_item_title', self::MENU_TITLE);
            })
            ->first();

        if (! $menuItem) {
            $callBlock = MenuItem::query()
                ->where('menu_uuid', $menu->menu_uuid)
                ->where('menu_item_parent_uuid', $applicationsItem->menu_item_uuid)
                ->where('menu_item_link', '/call-blocks')
                ->first();

            $order = $callBlock
                ? ((int) $callBlock->menu_item_order + 1)
                : (((int) MenuItem::query()
                    ->where('menu_uuid', $menu->menu_uuid)
                    ->where('menu_item_parent_uuid', $applicationsItem->menu_item_uuid)
                    ->max('menu_item_order')) + 1);

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

            echo "Added Call Permissions menu item under Applications.\n";
        } else {
            $menuItem->forceFill([
                'menu_item_title' => self::MENU_TITLE,
                'menu_item_link' => self::MENU_LINK,
                'menu_item_parent_uuid' => $applicationsItem->menu_item_uuid,
            ])->save();

            echo "Updated Class of Service menu item to Call Permissions.\n";
        }

        $language = MenuLanguage::query()
            ->where('menu_uuid', $menu->menu_uuid)
            ->where('menu_item_uuid', $menuItem->menu_item_uuid)
            ->where('menu_language', 'en-us')
            ->first();

        if ($language) {
            $language->forceFill(['menu_item_title' => self::MENU_TITLE])->save();
        } else {
            MenuLanguage::query()->create([
                'menu_language_uuid' => (string) Str::uuid(),
                'menu_uuid' => $menu->menu_uuid,
                'menu_item_uuid' => $menuItem->menu_item_uuid,
                'menu_language' => 'en-us',
                'menu_item_title' => self::MENU_TITLE,
            ]);
        }

        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();
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

    private function updateDialplans(): void
    {
        $dialplans = Dialplans::query()
            ->where('app_uuid', self::DIALPLAN_APP_UUID)
            ->get();

        $updated = 0;
        $contexts = collect();

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $updatedXml = str_replace(
                ['lua/class_of_service.lua', 'name="class_of_service"', 'name="call_permission"'],
                ['lua/call_permissions.lua', 'name="call_permissions"', 'name="call_permissions"'],
                $xml
            );

            $attrs = [
                'dialplan_name' => 'call_permissions',
                'dialplan_description' => 'Call Permissions outbound restrictions',
                'update_date' => now(),
            ];

            if ($updatedXml !== $xml) {
                $attrs['dialplan_xml'] = $updatedXml;
            }

            $dialplan->forceFill($attrs)->save();
            $updated++;
            $contexts->push($dialplan->dialplan_context);
        }

        $detailUpdated = DialplanDetails::query()
            ->whereIn('dialplan_uuid', $dialplans->pluck('dialplan_uuid')->filter())
            ->where('dialplan_detail_tag', 'action')
            ->where('dialplan_detail_type', 'lua')
            ->where('dialplan_detail_data', 'lua/class_of_service.lua')
            ->update([
                'dialplan_detail_data' => 'lua/call_permissions.lua',
                'update_date' => now(),
            ]);

        $contexts->filter()->unique()->each(
            fn ($context) => app(DialplanService::class)->clearDialplanCache($context)
        );

        echo "Updated {$updated} Call Permissions dialplan(s).\n";
        echo "Updated {$detailUpdated} Call Permissions dialplan detail(s).\n";
    }

    private function deployRuntimeLua(): void
    {
        $source = base_path('resources/lua/call_permissions.lua');
        if (! File::exists($source)) {
            echo "WARNING: {$source} not found. Skipping runtime Lua deploy.\n";

            return;
        }

        if (! File::isDirectory(self::RUNTIME_LUA_DIR)) {
            echo 'WARNING: ' . self::RUNTIME_LUA_DIR . " not found. Skipping runtime Lua deploy.\n";

            return;
        }

        File::copy($source, self::RUNTIME_LUA_DIR . '/call_permissions.lua');

        $legacy = self::RUNTIME_LUA_DIR . '/class_of_service.lua';
        if (File::exists($legacy)) {
            File::delete($legacy);
            echo "Removed legacy {$legacy}.\n";
        }

        echo 'Deployed ' . self::RUNTIME_LUA_DIR . "/call_permissions.lua.\n";
    }

    private function patchRuntimeDirectoryLua(): void
    {
        if (! File::exists(self::RUNTIME_DIRECTORY_LUA)) {
            echo 'WARNING: ' . self::RUNTIME_DIRECTORY_LUA . " not found. Skipping directory.lua patch.\n";

            return;
        }

        $contents = File::get(self::RUNTIME_DIRECTORY_LUA);
        $updated = $contents;

        $updated = str_replace(
            'class_of_service_uuid = row.class_of_service_uuid;',
            'call_permission_uuid = row.call_permission_uuid;',
            $updated
        );

        $updated = str_replace(
            '(class_of_service_uuid ~= nil) and (string.len(class_of_service_uuid) > 0)',
            '(call_permission_uuid ~= nil) and (string.len(call_permission_uuid) > 0)',
            $updated
        );

        $updated = str_replace(
            'name="class_of_service_uuid" value="]] .. xml.sanitize(class_of_service_uuid)',
            'name="call_permission_uuid" value="]] .. xml.sanitize(call_permission_uuid)',
            $updated
        );

        if (! str_contains($updated, 'call_permission_uuid = row.call_permission_uuid')) {
            $updated = str_replace(
                'toll_allow = row.toll_allow;',
                "toll_allow = row.toll_allow;\n\t\t\t\t\t\t\t\tcall_permission_uuid = row.call_permission_uuid;",
                $updated
            );
        }

        if (! str_contains($updated, 'name="call_permission_uuid"')) {
            $updated = str_replace(
                '<variable name="toll_allow" value="]] .. xml.sanitize(toll_allow) .. [["/>]]);',
                '<variable name="toll_allow" value="]] .. xml.sanitize(toll_allow) .. [["/>]]);' . "\n"
                . "\t\t\t\t\t\t\tif (call_permission_uuid ~= nil) and (string.len(call_permission_uuid) > 0) then\n"
                . "\t\t\t\t\t\t\t\txml:append([[								<variable name=\"call_permission_uuid\" value=\"]] .. xml.sanitize(call_permission_uuid) .. [[\"/>]]);\n"
                . "\t\t\t\t\t\t\tend",
                $updated
            );
        }

        if ($updated === $contents) {
            echo "Runtime directory.lua already uses Call Permissions variables.\n";

            return;
        }

        File::put(self::RUNTIME_DIRECTORY_LUA, $updated);
        echo 'Patched ' . self::RUNTIME_DIRECTORY_LUA . ".\n";
    }
}
