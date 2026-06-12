<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class Update205
{
    private const VERSION = '1.8.8.12';

    public function apply(): bool
    {
        try {
            DB::transaction(function () {
                $this->updateSpeedDialMenuLinks();
                $this->ensureFspbxMenuItems();
            });

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function updateSpeedDialMenuLinks(): void
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/speed-dial')
            ->update([
                'menu_item_link' => '/contacts?speed_dial=1',
            ]);

        echo "Updated {$updated} Speed Dial menu item(s) to /contacts?speed_dial=1.\n";
    }

    private function ensureFspbxMenuItems(): void
    {
        $exitCode = Artisan::call('menu:create-fspbx', [
            '--update' => true,
        ]);

        echo trim((string) Artisan::output()) . "\n";

        if ($exitCode !== 0) {
            throw new \RuntimeException('menu:create-fspbx --update failed.');
        }
    }
}
