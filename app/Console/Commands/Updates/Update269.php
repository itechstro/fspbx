<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;

class Update269
{
    private const VERSION = '1.9.3.10';

    private const RUNTIME_DIRECTORY_LUA = '/usr/share/freeswitch/scripts/app/xml_handler/resources/scripts/directory/directory.lua';

    private const RUNTIME_LUA_DIR = '/usr/share/freeswitch/scripts/lua';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_07_08_000001_create_class_of_service_tables.php',
            ]);

            echo trim((string) Artisan::output()) . "\n";

            $this->deployRuntimeLua();
            $this->patchRuntimeDirectoryLua();

            $updated = MenuItem::query()
                ->where('menu_item_title', 'Class of Service')
                ->where(function ($query) {
                    $query->where('menu_item_link', '/app/class_of_service/class_of_service.php')
                        ->orWhere('menu_item_link', 'like', '%class_of_service%');
                })
                ->update([
                    'menu_item_link' => '/class-of-service',
                ]);

            echo $updated === 0
                ? "No Class of Service menu items required updating.\n"
                : "Updated {$updated} Class of Service menu item(s).\n";

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function deployRuntimeLua(): void
    {
        $source = base_path('resources/lua/class_of_service.lua');

        if (! File::exists($source)) {
            echo "WARNING: {$source} not found. Skipping runtime Lua deploy.\n";

            return;
        }

        if (! File::isDirectory(self::RUNTIME_LUA_DIR)) {
            echo 'WARNING: ' . self::RUNTIME_LUA_DIR . " not found. Skipping runtime Lua deploy.\n";

            return;
        }

        $destination = self::RUNTIME_LUA_DIR . '/class_of_service.lua';
        File::copy($source, $destination);
        echo "Deployed {$destination}.\n";
    }

    private function patchRuntimeDirectoryLua(): void
    {
        if (! File::exists(self::RUNTIME_DIRECTORY_LUA)) {
            echo 'WARNING: ' . self::RUNTIME_DIRECTORY_LUA . " not found. Skipping directory.lua patch.\n";

            return;
        }

        $contents = File::get(self::RUNTIME_DIRECTORY_LUA);
        $updated = $contents;

        if (! str_contains($updated, 'class_of_service_uuid = row.class_of_service_uuid')) {
            $updated = str_replace(
                "toll_allow = row.toll_allow;",
                "toll_allow = row.toll_allow;\n\t\t\t\t\t\t\t\tclass_of_service_uuid = row.class_of_service_uuid;",
                $updated
            );
        }

        if (! str_contains($updated, 'name="class_of_service_uuid"')) {
            $updated = str_replace(
                '<variable name="toll_allow" value="]] .. xml.sanitize(toll_allow) .. [["/>]]);',
                '<variable name="toll_allow" value="]] .. xml.sanitize(toll_allow) .. [["/>]]);' . "\n"
                . "\t\t\t\t\t\t\tif (class_of_service_uuid ~= nil) and (string.len(class_of_service_uuid) > 0) then\n"
                . "\t\t\t\t\t\t\t\txml:append([[								<variable name=\"class_of_service_uuid\" value=\"]] .. xml.sanitize(class_of_service_uuid) .. [[\"/>]]);\n"
                . "\t\t\t\t\t\t\tend",
                $updated
            );
        }

        if ($updated === $contents) {
            echo "Runtime directory.lua already includes Class of Service variables.\n";

            return;
        }

        File::put(self::RUNTIME_DIRECTORY_LUA, $updated);
        echo 'Patched ' . self::RUNTIME_DIRECTORY_LUA . ".\n";
    }
}
