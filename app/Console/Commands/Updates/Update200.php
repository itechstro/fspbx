<?php

namespace App\Console\Commands\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update200
{
    private const VERSION = '1.8.8.7';

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
        if (Schema::hasColumn('v_extensions', 'cloudplay_ed_id')) {
            echo "v_extensions.cloudplay_ed_id already exists; skipping.\n";

            return;
        }

        Schema::table('v_extensions', function (Blueprint $table) {
            $table->unsignedBigInteger('cloudplay_ed_id')->nullable()->after('description');
        });

        echo "Added v_extensions.cloudplay_ed_id.\n";
    }
}
