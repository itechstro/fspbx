<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

class Update237
{
    private const VERSION = '1.8.8.44';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_06_18_000001_create_call_history_analytics_report_schedules_table.php',
                '--force' => true,
            ]);
            echo trim(Artisan::output()) . "\n";

            $this->ensureCallHistoryAnalyticsPermissions();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureCallHistoryAnalyticsPermissions(): void
    {
        $applicationName = 'XML CDR';
        $permissions = [
            'cdr_analytics_view',
            'cdr_analytics_schedule',
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
            echo 'Created ' . count($permissionRows) . " call history analytics permission row(s).\n";
        }

        $assignments = [
            'superadmin' => $permissions,
            'admin' => $permissions,
            'user' => ['cdr_analytics_view'],
        ];

        foreach ($assignments as $groupName => $groupPermissions) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; call history analytics permissions not assigned.\n";
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
            echo 'Assigned ' . count($groupPermissionRows) . " call history analytics permission(s) to group '{$groupName}'.\n";
        }
    }
}
