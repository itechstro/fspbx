<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update262
{
    private const VERSION = '1.9.3.3';

    public function apply(): bool
    {
        try {
            $this->seedFanvilTemplates();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedFanvilTemplates(): void
    {
        $exitCode = Artisan::call('prov:templates:seed', [
            '--vendor' => 'fanvil',
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'prov:templates:seed --vendor=fanvil failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Re-seeded Fanvil provisioning templates.\n";
    }
}
