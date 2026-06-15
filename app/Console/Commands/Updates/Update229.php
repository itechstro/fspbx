<?php

namespace App\Console\Commands\Updates;

use App\Models\Groups;
use App\Models\GroupPermissions;
use App\Models\Permissions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

class Update229
{
    private const VERSION = '1.8.8.36';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_15_000001_create_recorder_analytics_report_schedules_table.php',
            ]);

            echo trim((string) Artisan::output()) . "\n";

            $this->ensureRecorderAnalyticsPermissions();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureRecorderAnalyticsPermissions(): void
    {
        $applicationName = 'Recorder';
        $permissions = [
            'recorder_analytics_view',
            'recorder_analytics_schedule',
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
            echo 'Created ' . count($permissionRows) . " Recorder analytics permission row(s).\n";
        }

        $assignments = [
            'superadmin' => $permissions,
            'admin' => $permissions,
            'user' => ['recorder_analytics_view'],
        ];

        foreach ($assignments as $groupName => $groupPermissions) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; Recorder analytics permissions not assigned.\n";
                continue;
            }

            $existingGroupPermissions = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->whereIn('permission_name', $groupPermissions)
                ->pluck('permission_name')
                ->all();

            $groupPermissionRows = collect($groupPermissions)
                ->diff($existingGroupPermissions)
                ->map(fn ($permissionName) => [
                    'group_permission_uuid' => (string) Str::uuid(),
                    'group_uuid' => $group->group_uuid,
                    'group_name' => $groupName,
                    'permission_name' => $permissionName,
                    'permission_protected' => 'true',
                    'permission_assigned' => 'true',
                    'insert_date' => $now,
                ])
                ->values()
                ->all();

            if ($groupPermissionRows === []) {
                continue;
            }

            GroupPermissions::query()->insert($groupPermissionRows);
            echo 'Assigned ' . count($groupPermissionRows) . " Recorder analytics permission(s) to group '{$groupName}'.\n";
        }
    }
}
