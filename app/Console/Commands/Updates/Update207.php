<?php

namespace App\Console\Commands\Updates;

use Throwable;

class Update207
{
    private const VERSION = '1.8.8.14';

    public function apply(): bool
    {
        try {
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
