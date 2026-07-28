<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update278
{
    private const VERSION = '1.9.6.1';

    public function apply(): bool
    {
        try {
            $exitCode = Artisan::call('email:templates:seed', [
                '--no-interaction' => true,
            ]);

            $output = trim(Artisan::output());
            if ($output !== '') {
                echo $output."\n";
            }

            if ($exitCode !== 0) {
                echo 'Error applying update '.self::VERSION.": email:templates:seed failed.\n";

                return false;
            }

            echo 'Update '.self::VERSION." completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update '.self::VERSION.': '.$exception->getMessage()."\n";

            return false;
        }
    }
}
