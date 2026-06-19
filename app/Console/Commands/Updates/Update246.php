<?php

namespace App\Console\Commands\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update246
{
    private const VERSION = '1.9.0.4';

    public function apply(): bool
    {
        try {
            if (! Schema::hasColumn('mobile_app_users', 'app_password')) {
                Schema::table('mobile_app_users', function (Blueprint $table) {
                    $table->text('app_password')->nullable()->after('user_id');
                });

                echo "Added mobile_app_users.app_password.\n";
            } else {
                echo "mobile_app_users.app_password already exists; skipping.\n";
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
