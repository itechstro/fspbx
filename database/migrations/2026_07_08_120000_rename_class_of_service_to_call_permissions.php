<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameTableIfNeeded('v_class_of_service', 'v_call_permissions');
        $this->renameColumnIfNeeded('v_call_permissions', 'class_of_service_uuid', 'call_permission_uuid');
        $this->renameColumnIfNeeded('v_call_permissions', 'cos_name', 'name');
        $this->renameColumnIfNeeded('v_call_permissions', 'cos_description', 'description');

        $this->renameTableIfNeeded('v_class_of_service_destinations', 'v_call_permission_destinations');
        $this->renameColumnIfNeeded('v_call_permission_destinations', 'class_of_service_destination_uuid', 'call_permission_destination_uuid');
        $this->renameColumnIfNeeded('v_call_permission_destinations', 'class_of_service_uuid', 'call_permission_uuid');

        $this->renameColumnIfNeeded('v_extensions', 'class_of_service_uuid', 'call_permission_uuid');

        $this->renamePermission('class_of_service_view', 'call_permission_view', 'Call Permissions');
        $this->renamePermission('class_of_service_add', 'call_permission_add', 'Call Permissions');
        $this->renamePermission('class_of_service_edit', 'call_permission_edit', 'Call Permissions');
        $this->renamePermission('class_of_service_delete', 'call_permission_delete', 'Call Permissions');
        $this->renamePermission('extension_class_of_service', 'extension_call_permission', 'Extensions');
    }

    public function down(): void
    {
        $this->renamePermission('call_permission_view', 'class_of_service_view', 'Class of Service');
        $this->renamePermission('call_permission_add', 'class_of_service_add', 'Class of Service');
        $this->renamePermission('call_permission_edit', 'class_of_service_edit', 'Class of Service');
        $this->renamePermission('call_permission_delete', 'class_of_service_delete', 'Class of Service');
        $this->renamePermission('extension_call_permission', 'extension_class_of_service', 'Extensions');

        $this->renameColumnIfNeeded('v_extensions', 'call_permission_uuid', 'class_of_service_uuid');

        $this->renameColumnIfNeeded('v_call_permission_destinations', 'call_permission_destination_uuid', 'class_of_service_destination_uuid');
        $this->renameColumnIfNeeded('v_call_permission_destinations', 'call_permission_uuid', 'class_of_service_uuid');
        $this->renameTableIfNeeded('v_call_permission_destinations', 'v_class_of_service_destinations');

        $this->renameColumnIfNeeded('v_call_permissions', 'call_permission_uuid', 'class_of_service_uuid');
        $this->renameColumnIfNeeded('v_call_permissions', 'name', 'cos_name');
        $this->renameColumnIfNeeded('v_call_permissions', 'description', 'cos_description');
        $this->renameTableIfNeeded('v_call_permissions', 'v_class_of_service');
    }

    private function renameTableIfNeeded(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameColumnIfNeeded(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, function ($blueprint) use ($from, $to) {
                $blueprint->renameColumn($from, $to);
            });
        }
    }

    private function renamePermission(string $from, string $to, string $applicationName): void
    {
        if (! Schema::hasTable('v_permissions')) {
            return;
        }

        $exists = DB::table('v_permissions')->where('permission_name', $to)->exists();
        if ($exists) {
            $oldUuid = DB::table('v_permissions')->where('permission_name', $from)->value('permission_uuid');
            if ($oldUuid && Schema::hasTable('v_group_permissions')) {
                DB::table('v_group_permissions')->where('permission_uuid', $oldUuid)->delete();
            }
            DB::table('v_permissions')->where('permission_name', $from)->delete();

            return;
        }

        DB::table('v_permissions')
            ->where('permission_name', $from)
            ->update([
                'permission_name' => $to,
                'application_name' => $applicationName,
            ]);
    }
};
