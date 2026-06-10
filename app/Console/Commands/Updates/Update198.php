<?php

namespace App\Console\Commands\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update198
{
    private const VERSION = '1.8.8.6';

    public function apply(): bool
    {
        try {
            $this->addCloudPlayEnterpriseDirectoryColumn();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function addCloudPlayEnterpriseDirectoryColumn(): void
    {
        if (Schema::hasColumn('mobile_app_users', 'cloudplay_ed_id')) {
            echo "mobile_app_users.cloudplay_ed_id already exists; skipping.\n";

            return;
        }

        Schema::table('mobile_app_users', function (Blueprint $table) {
            $table->unsignedBigInteger('cloudplay_ed_id')->nullable()->after('user_id');
        });

        echo "Added mobile_app_users.cloudplay_ed_id.\n";
    }
}
