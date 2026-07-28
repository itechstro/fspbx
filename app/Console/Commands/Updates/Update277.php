<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;
use Throwable;

class Update277
{
    private const VERSION = '1.9.6';

    public function apply(): bool
    {
        try {
            $updated = MenuItem::query()
                ->where('menu_item_link', '/app/email_templates/email_templates.php')
                ->update(['menu_item_link' => '/email-templates']);

            echo $updated === 0
                ? "No Email Templates menu items required updating.\n"
                : "Updated {$updated} Email Templates menu item(s).\n";

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    public function getSupervisorProgramsToRestart(): array
    {
        return [
            'fs-cdr-service',
        ];
    }
}
