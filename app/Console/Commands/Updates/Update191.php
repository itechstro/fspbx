<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update191
{
    private const VERSION = '1.8.7.4';

    private const MODULE_NAME = 'ContactCenter';

    public function apply(): bool
    {
        try {
            $this->ensureContactCenterModuleEnabled();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureContactCenterModuleEnabled(): void
    {
        $modulePath = base_path('Modules/' . self::MODULE_NAME);

        if (! is_dir($modulePath)) {
            echo 'Contact Center module not found; skipping module enable and seed.' . "\n";

            return;
        }

        $this->mergeModuleStatus(true);

        echo 'Enabling Contact Center module...' . "\n";
        $exitCode = Artisan::call('module:enable', [
            'module' => self::MODULE_NAME,
        ]);
        echo Artisan::output();

        if ($exitCode !== 0) {
            throw new \RuntimeException('module:enable ContactCenter failed with exit code ' . $exitCode);
        }

        echo 'Seeding Contact Center permissions...' . "\n";
        $exitCode = Artisan::call('module:seed', [
            'module' => self::MODULE_NAME,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        echo Artisan::output();

        if ($exitCode !== 0) {
            throw new \RuntimeException('module:seed ContactCenter failed with exit code ' . $exitCode);
        }
    }

    private function mergeModuleStatus(bool $enabled): void
    {
        $statusFile = base_path('modules_statuses.json');
        $statuses = [];

        if (is_file($statusFile)) {
            $decoded = json_decode((string) file_get_contents($statusFile), true);
            $statuses = is_array($decoded) ? $decoded : [];
        }

        if (($statuses[self::MODULE_NAME] ?? false) === $enabled) {
            echo 'Contact Center already ' . ($enabled ? 'enabled' : 'disabled') . " in modules_statuses.json.\n";

            return;
        }

        $statuses[self::MODULE_NAME] = $enabled;

        file_put_contents(
            $statusFile,
            json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        echo 'Updated modules_statuses.json to enable Contact Center.' . "\n";
    }
}
