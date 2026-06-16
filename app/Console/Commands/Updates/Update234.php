<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update234
{
    private const VERSION = '1.8.8.41';

    public function apply(): bool
    {
        try {
            $this->ensureUsageDetailsPermission();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureUsageDetailsPermission(): void
    {
        $applicationName = 'Domains';
        $permissionName = 'domain_license_usage_details_view';
        $now = date('Y-m-d H:i:s');

        if (! Permissions::query()->where('permission_name', $permissionName)->exists()) {
            Permissions::query()->insert([
                'permission_uuid' => (string) Str::uuid(),
                'application_name' => $applicationName,
                'permission_name' => $permissionName,
                'insert_date' => $now,
            ]);
            echo "Created {$permissionName} permission row.\n";
        }

        $group = Groups::query()->where('group_name', 'superadmin')->first();
        if (! $group) {
            echo "Group 'superadmin' not found; {$permissionName} not assigned.\n";

            return;
        }

        if (GroupPermissions::query()
            ->where('group_uuid', $group->group_uuid)
            ->where('permission_name', $permissionName)
            ->exists()) {
            return;
        }

        GroupPermissions::query()->insert([
            'group_permission_uuid' => (string) Str::uuid(),
            'group_uuid' => $group->group_uuid,
            'group_name' => 'superadmin',
            'permission_name' => $permissionName,
            'permission_protected' => 'true',
            'permission_assigned' => 'true',
            'insert_date' => $now,
        ]);

        echo "Assigned {$permissionName} to group 'superadmin'.\n";
    }
}
