<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Throwable;

class Update199
{
    private const VERSION = '1.8.8.7';

    private const CONTACTS_MENU_UUID = 'f14e6ab6-6565-d4e6-cbad-a51d2e3e8ec6';

    public function apply(): bool
    {
        try {
            DB::transaction(function () {
                $this->updateLegacyContactsMenuLinks();
                $this->ensureFspbxContactsMenuItem();
            });

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function updateLegacyContactsMenuLinks(): void
    {
        $updated = MenuItem::query()
            ->where(function ($query) {
                $query->where('menu_item_uuid', self::CONTACTS_MENU_UUID)
                    ->orWhere('menu_item_link', '/app/contacts/contacts.php');
            })
            ->update([
                'menu_item_link' => '/contacts',
            ]);

        echo "Updated {$updated} legacy Contacts menu item(s) to /contacts.\n";
    }

    private function ensureFspbxContactsMenuItem(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('menu:create-fspbx', [
            '--update' => true,
        ]);

        echo trim((string) \Illuminate\Support\Facades\Artisan::output()) . "\n";

        if ($exitCode !== 0) {
            throw new \RuntimeException('menu:create-fspbx --update failed.');
        }
    }
}
