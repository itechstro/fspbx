<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\DB;
use Throwable;

class Update254
{
    private const VERSION = '1.9.0.12';

    public function apply(): bool
    {
        try {
            $deleted = $this->removeDuplicatePermissionRows();

            if ($deleted > 0) {
                echo "Removed {$deleted} duplicate v_permissions row(s).\n";
            } else {
                echo "No duplicate v_permissions rows found.\n";
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function removeDuplicatePermissionRows(): int
    {
        $duplicateNames = DB::table('v_permissions')
            ->select('permission_name')
            ->groupBy('permission_name')
            ->havingRaw('count(*) > 1')
            ->pluck('permission_name');

        $deleted = 0;

        foreach ($duplicateNames as $permissionName) {
            $rows = DB::table('v_permissions')
                ->where('permission_name', $permissionName)
                ->orderBy('insert_date')
                ->orderBy('permission_uuid')
                ->get(['permission_uuid']);

            if ($rows->count() <= 1) {
                continue;
            }

            $uuidsToDelete = $rows->skip(1)->pluck('permission_uuid')->all();

            $deleted += DB::table('v_permissions')
                ->whereIn('permission_uuid', $uuidsToDelete)
                ->delete();

            echo "Kept one row for permission '{$permissionName}', removed " . count($uuidsToDelete) . " duplicate(s).\n";
        }

        return $deleted;
    }
}
