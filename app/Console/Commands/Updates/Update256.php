<?php

namespace App\Console\Commands\Updates;

use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Str;
use Throwable;

class Update256
{
    private const VERSION = '1.9.0.14';

    private const APPLICATION_NAME = 'Recorder';

    private const RECORDER_SEARCH_SENTIMENT = 'recorder_search_sentiment';

    public function apply(): bool
    {
        try {
            $this->ensureRecorderSearchSentimentPermission();
            $this->assignToDefaultAdminGroups();
            $this->mirrorAssignmentsFromXmlCdrSearchSentiment();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureRecorderSearchSentimentPermission(): void
    {
        $exists = Permissions::query()
            ->where('permission_name', self::RECORDER_SEARCH_SENTIMENT)
            ->exists();

        if ($exists) {
            echo "Permission '" . self::RECORDER_SEARCH_SENTIMENT . "' already exists.\n";

            return;
        }

        Permissions::query()->insert([
            'permission_uuid' => (string) Str::uuid(),
            'application_name' => self::APPLICATION_NAME,
            'permission_name' => self::RECORDER_SEARCH_SENTIMENT,
            'insert_date' => date('Y-m-d H:i:s'),
        ]);

        echo "Created permission '" . self::RECORDER_SEARCH_SENTIMENT . "'.\n";
    }

    private function assignToDefaultAdminGroups(): void
    {
        foreach (['superadmin', 'admin'] as $groupName) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; skipped recorder_search_sentiment assignment.\n";
                continue;
            }

            $this->assignPermissionToGroup($group, self::RECORDER_SEARCH_SENTIMENT, $groupName);
        }
    }

    private function mirrorAssignmentsFromXmlCdrSearchSentiment(): void
    {
        $groupUuids = GroupPermissions::query()
            ->where('permission_name', 'xml_cdr_search_sentiment')
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
                ->where('permission_name', self::RECORDER_SEARCH_SENTIMENT)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->assignPermissionToGroup($group, self::RECORDER_SEARCH_SENTIMENT, $group->group_name);
            $assigned++;
        }

        if ($assigned > 0) {
            echo "Mirrored recorder_search_sentiment to {$assigned} group(s) with xml_cdr_search_sentiment.\n";
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
