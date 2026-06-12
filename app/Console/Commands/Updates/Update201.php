<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update201
{
    private const VERSION = '1.8.8.8';

    public function apply(): bool
    {
        try {
            $this->refreshProvisionTemplateLinks();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function refreshProvisionTemplateLinks(): void
    {
        $exitCode = Artisan::call('provisioning:link-templates', [
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'provisioning:link-templates failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Refreshed legacy provisioning template symlinks for /prov phonebook URL cleanup.\n";
    }
}
