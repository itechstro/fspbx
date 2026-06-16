<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use App\Models\FusionCache;
use App\Models\GroupPermissions;
use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class Update233
{
    private const VERSION = '1.8.8.40';

    private const DIALPLAN_SOURCE = '/var/www/fspbx/resources/dialplans/021_check-billing-suspension.xml';

    private const DIALPLAN_TARGET = '/var/www/fspbx/public/app/dialplans/resources/switch/conf/dialplan/021_check-billing-suspension.xml';

    private const LUA_SOURCE = '/var/www/fspbx/resources/freeswitch_scripts/check_outbound_minutes.lua';

    public function apply(): bool
    {
        try {
            $this->ensureDomainLicensePermissions();
            $this->deployOutboundMinutesLua();
            $this->deployBillingSuspensionDialplan();
            $this->patchBillingSuspensionDialplans();

            FusionCache::clear('dialplan.*');

            if (file_exists('/var/www/fspbx/public/core/upgrade/upgrade.php')) {
                shell_exec('cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1');
                echo "Ran upgrade defaults to refresh dialplan imports.\n";
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureDomainLicensePermissions(): void
    {
        $applicationName = 'Domains';
        $permissions = [
            'domain_license_view',
            'domain_license_edit',
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
            echo 'Created ' . count($permissionRows) . " domain license permission row(s).\n";
        }

        $assignments = [
            'superadmin' => $permissions,
            'admin' => $permissions,
        ];

        foreach ($assignments as $groupName => $groupPermissions) {
            $group = Groups::query()->where('group_name', $groupName)->first();
            if (! $group) {
                echo "Group '{$groupName}' not found; domain license permissions not assigned.\n";
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
            echo 'Assigned ' . count($groupPermissionRows) . " domain license permission(s) to group '{$groupName}'.\n";
        }
    }

    private function deployOutboundMinutesLua(): void
    {
        if (! file_exists(self::LUA_SOURCE)) {
            throw new \RuntimeException('Missing check_outbound_minutes.lua source file.');
        }

        echo "Outbound minutes Lua script is available at " . self::LUA_SOURCE . ".\n";
    }

    private function deployBillingSuspensionDialplan(): void
    {
        if (! file_exists(self::DIALPLAN_SOURCE)) {
            throw new \RuntimeException('Missing 021_check-billing-suspension.xml source file.');
        }

        File::ensureDirectoryExists(dirname(self::DIALPLAN_TARGET));
        File::copy(self::DIALPLAN_SOURCE, self::DIALPLAN_TARGET);
        echo "Deployed billing suspension dialplan XML.\n";
    }

    private function patchBillingSuspensionDialplans(): void
    {
        $needle = 'check_outbound_minutes.lua';
        $insertion = '<action application="lua" data="check_outbound_minutes.lua"/>';

        $dialplans = Dialplans::query()
            ->where('dialplan_xml', 'like', '%check_ext_suspension.lua%')
            ->where('dialplan_xml', 'not like', '%' . $needle . '%')
            ->get(['dialplan_uuid', 'dialplan_xml']);

        $updated = 0;

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $updatedXml = preg_replace(
                '/(<action application="lua" data="check_ext_suspension\.lua"\s*\/>)/',
                '$1' . "\n    " . $insertion,
                $xml,
                1,
                $count,
            );

            if ($count < 1 || $updatedXml === $xml) {
                continue;
            }

            $dialplan->dialplan_xml = $updatedXml;
            $dialplan->update_date = now();
            $dialplan->save();
            $updated++;
        }

        echo "Patched {$updated} billing suspension dialplan row(s) with outbound minute enforcement.\n";
    }
}
