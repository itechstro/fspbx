<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update235
{
    private const VERSION = '1.8.8.42';

    public function apply(): bool
    {
        try {
            $this->ensureAiUsageRatesPermissions();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureAiUsageRatesPermissions(): void
    {
        $applicationName = 'System Settings';
        $permissions = [
            'ai_usage_rates_view',
            'ai_usage_rates_edit',
        ];
        $now = date('Y-m-d H:i:s');

        $existingPermissions = Permissions::query()
            ->whereIn('permission_name', $permissions)
            ->pluck('permission_name')
            ->all();

        $permissionRows = collect($permissions)
            ->diff($existingPermissions)
            ->map(fn ($permissionName) => [
                'permission_uuid' => (string) Str::uuid(),
                'application_name' => $applicationName,
                'permission_name' => $permissionName,
                'insert_date' => $now,
            ])
            ->values()
            ->all();

        if ($permissionRows !== []) {
            Permissions::query()->insert($permissionRows);
            echo 'Created ' . count($permissionRows) . " AI usage rates permission row(s).\n";
        }

        $group = Groups::query()->where('group_name', 'superadmin')->first();
        if (! $group) {
            echo "Group 'superadmin' not found; AI usage rates permissions not assigned.\n";

            return;
        }

        $existingGroupPermissions = GroupPermissions::query()
            ->where('group_uuid', $group->group_uuid)
            ->whereIn('permission_name', $permissions)
            ->pluck('permission_name')
            ->all();

        $groupPermissionRows = collect($permissions)
            ->diff($existingGroupPermissions)
            ->map(fn ($permissionName) => [
                'group_permission_uuid' => (string) Str::uuid(),
                'group_uuid' => $group->group_uuid,
                'group_name' => 'superadmin',
                'permission_name' => $permissionName,
                'permission_protected' => 'true',
                'permission_assigned' => 'true',
                'insert_date' => $now,
            ])
            ->values()
            ->all();

        if ($groupPermissionRows === []) {
            return;
        }

        GroupPermissions::query()->insert($groupPermissionRows);
        echo 'Assigned ' . count($groupPermissionRows) . " AI usage rates permission(s) to group 'superadmin'.\n";
    }
}
