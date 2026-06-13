<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update217
{
    private const VERSION = '1.8.8.24';

    public function apply(): bool
    {
        try {
            $this->seedIbratroTemplates();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedIbratroTemplates(): void
    {
        $exitCode = Artisan::call('prov:templates:seed', [
            '--vendor' => 'ibratro',
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'prov:templates:seed --vendor=ibratro failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Re-seeded Ibratro provisioning templates.\n";
    }
}
