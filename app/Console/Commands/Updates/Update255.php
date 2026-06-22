<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update255
{
    private const VERSION = '1.9.0.13';

    private const APPLICATION_NAME = 'Recorder';

    private const RECORDER_VIEW = 'recorder_view';

    private const RECORDER_ANALYTICS_VIEW = 'recorder_analytics_view';

    private const RECORDER_ANALYTICS_SCHEDULE = 'recorder_analytics_schedule';

    public function apply(): bool
    {
        try {
            $this->ensureRecorderViewPermission();
            $this->assignRecorderViewToDefaultAdminGroups();
            $this->removeDefaultRecorderPermissionsFromUserGroup();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureRecorderViewPermission(): void
    {
        $exists = Permissions::query()
            ->where('permission_name', self::RECORDER_VIEW)
            ->exists();

        if ($exists) {
            echo "Permission '" . self::RECORDER_VIEW . "' already exists.\n";

            return;
        }

        Permissions::query()->insert([
            'permission_uuid' => (string) Str::uuid(),
            'application_name' => self::APPLICATION_NAME,
            'permission_name' => self::RECORDER_VIEW,
            'insert_date' => date('Y-m-d H:i:s'),
        ]);

        echo "Created permission '" . self::RECORDER_VIEW . "'.\n";
    }

    private function assignRecorderViewToDefaultAdminGroups(): void
    {
        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; skipped recorder_view assignment.\n";
                continue;
            }

            $this->assignPermissionToGroup($group, self::RECORDER_VIEW, $groupName);
        }
    }

    private function removeDefaultRecorderPermissionsFromUserGroup(): void
    {
        $group = Groups::query()->where('group_name', 'user')->first();
        if (! $group) {
            echo "Group 'user' not found; skipped Recorder permission cleanup.\n";

            return;
        }

        $removed = GroupPermissions::query()
            ->where('group_uuid', $group->group_uuid)
            ->whereIn('permission_name', [
                self::RECORDER_VIEW,
                self::RECORDER_ANALYTICS_VIEW,
                self::RECORDER_ANALYTICS_SCHEDULE,
            ])
            ->delete();

        echo "Removed {$removed} Recorder permission assignment(s) from group 'user'.\n";
    }

    private function assignPermissionToGroup(Groups $group, string $permissionName, string $groupName): void
    {
        $exists = GroupPermissions::query()
            ->where('group_uuid', $group->group_uuid)
            ->where('permission_name', $permissionName)
            ->exists();

        if ($exists) {
            return;
        }

        GroupPermissions::query()->insert([
            'group_permission_uuid' => (string) Str::uuid(),
            'group_uuid' => $group->group_uuid,
            'group_name' => $groupName,
            'permission_name' => $permissionName,
            'permission_protected' => 'true',
            'permission_assigned' => 'true',
            'insert_date' => date('Y-m-d H:i:s'),
        ]);

        echo "Assigned '{$permissionName}' to group '{$groupName}'.\n";
    }
}
