<?php

namespace App\Console\Commands\Updates;

use App\Services\PhoneFirmwareService;
use Throwable;

class Update282
{
    private const VERSION = '1.9.8.1';

    public function apply(): bool
    {
        try {
            $written = app(PhoneFirmwareService::class)->ensureIntradeManifestCaseAliases('intrade');
            echo "Wrote {$written} lowercase Intrade firmware meta alias(es).\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ': ' . $exception->getMessage() . "\n";

            return false;
        }
    }
}
