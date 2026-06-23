<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update258
{
    private const VERSION = '1.9.0.16';

    private const APPLICATION_NAME = 'Recorder';

    /**
     * Recorder permission => legacy permission to mirror group assignments from.
     *
     * @var array<string, string>
     */
    private const PERMISSION_MIRRORS = [
        'recorder_view_details' => 'xml_cdr_details',
        'recorder_recording_play' => 'call_recording_play',
        'recorder_recording_download' => 'call_recording_download',
        'recorder_transcription_view' => 'transcription_view',
        'recorder_transcription_read' => 'transcription_read',
        'recorder_transcription_create' => 'transcription_create',
        'recorder_transcription_summary' => 'transcription_summary',
    ];

    public function apply(): bool
    {
        try {
            foreach (array_keys(self::PERMISSION_MIRRORS) as $permissionName) {
                $this->ensurePermission($permissionName);
            }

            foreach (array_keys(self::PERMISSION_MIRRORS) as $permissionName) {
                $this->assignToDefaultAdminGroups($permissionName);
            }

            foreach (self::PERMISSION_MIRRORS as $recorderPermission => $legacyPermission) {
                $this->mirrorAssignmentsFromLegacy($recorderPermission, $legacyPermission);
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensurePermission(string $permissionName): void
    {
        $exists = Permissions::query()
            ->where('permission_name', $permissionName)
            ->exists();

        if ($exists) {
            echo "Permission '{$permissionName}' already exists.\n";

            return;
        }

        Permissions::query()->insert([
            'permission_uuid' => (string) Str::uuid(),
            'application_name' => self::APPLICATION_NAME,
            'permission_name' => $permissionName,
            'insert_date' => date('Y-m-d H:i:s'),
        ]);

        echo "Created permission '{$permissionName}'.\n";
    }

    private function assignToDefaultAdminGroups(string $permissionName): void
    {
        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; skipped {$permissionName} assignment.\n";
                continue;
            }

            $this->assignPermissionToGroup($group, $permissionName, $groupName);
        }
    }

    private function mirrorAssignmentsFromLegacy(string $recorderPermission, string $legacyPermission): void
    {
        $groupUuids = GroupPermissions::query()
            ->where('permission_name', $legacyPermission)
            ->where('permission_assigned', 'true')
            ->pluck('group_uuid')
            ->unique();

        $assigned = 0;

        foreach ($groupUuids as $groupUuid) {
            $group = Groups::query()->where('group_uuid', $groupUuid)->first();
            if (! $group) {
                continue;
            }

            $exists = GroupPermissions::query()
                ->where('group_uuid', $group->group_uuid)
                ->where('permission_name', $recorderPermission)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->assignPermissionToGroup($group, $recorderPermission, $group->group_name);
            $assigned++;
        }

        if ($assigned > 0) {
            echo "Mirrored {$recorderPermission} to {$assigned} group(s) with {$legacyPermission}.\n";
        }
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
