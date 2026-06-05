<?php

namespace Modules\ContactCenter\Database\Seeders;

use App\Models\Groups;
use App\Models\Permissions;
use Illuminate\Database\Seeder;
use App\Models\GroupPermissions;
use Illuminate\Database\Eloquent\Model;

class ContactCenterDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // $this->call("OthersTableSeeder");

        $this->createGroups();

        $this->createPermissions();

        $this->createGroupPermissions();

        Model::reguard();
    }

    private function createGroups()
    {

        $groups = [
            [
                'group_name'        => 'Contact Center Agent',
                'group_protected'   => 'true',
                'group_level'       => 20,
                'group_description' => "Contact Center Agent Group",
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'group_name'        => 'Contact Center Supervisor',
                'group_protected'   => 'true',
                'group_level'       => 20,
                'group_description' => "Contact Center Supervisor Group",
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
            [
                'group_name'        => 'Contact Center Admin',
                'group_protected'   => 'true',
                'group_level'       => 40,
                'group_description' => "Contact Center Admin Group",
                'insert_date'       => date("Y-m-d H:i:s"),
            ],
        ];

        // Log::alert(Category::where('name', trans('custom-fields::general.categories.cost_recovery'))->where('company_id', $company_id)->value('id'));
        foreach ($groups as $group) {
            $existing_item = Groups::where('group_name', $group['group_name'])
                ->first();

            if (empty($existing_item)) {
                // Add new group
                Groups::create([
                    'group_name'        => $group['group_name'],
                    'group_protected'   => $group['group_protected'],
                    'group_level'       => $group['group_level'],
                    'group_description' => $group['group_description'],
                    'insert_date'       => $group['insert_date'],
                ]);
            }
        }
    }

    private function createPermissions()
    {

        $permissions = [
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_agent_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_agent_assign',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_agent_unassign',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_agent_delete',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_supervisor_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_admin_create',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_settings_edit',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'application_name'      => 'Contact Center',
                'permission_name'       => 'contact_center_dashboard_view',
                'insert_date'           => date("Y-m-d H:i:s"),
            ],

        ];

        foreach ($permissions as $permission) {
            $existing_item = Permissions::where('permission_name', $permission['permission_name'])
                ->first();

            if (empty($existing_item)) {
                // Add new permission
                Permissions::create([
                    'application_name'        => $permission['application_name'],
                    'permission_name'        => $permission['permission_name'],
                    'insert_date'       => $permission['insert_date'],
                ]);

            }
        }
    }


    private function createGroupPermissions()
    {

        $group_permissions = [
            [
                'permission_name'        => 'contact_center_agent_create',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_create',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_dashboard_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_dashboard_view',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_settings_edit',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_settings_edit',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_admin_create',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_admin_create',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_assign',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_assign',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_unassign',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_unassign',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_delete',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_agent_delete',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_supervisor_create',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "Contact Center Admin",
                'group_uuid'            => Groups::where('group_name', "Contact Center Admin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],
            [
                'permission_name'        => 'contact_center_supervisor_create',
                'permission_protected'   => 'true',
                'permission_assigned'    => 'true',
                'group_name'            => "superadmin",
                'group_uuid'            => Groups::where('group_name', "superadmin")->value('group_uuid'),
                'insert_date'           => date("Y-m-d H:i:s"),
            ],

        ];

        foreach ($group_permissions as $permission) {
            $existing_item = GroupPermissions::where('permission_name', $permission['permission_name'])
                ->where('group_uuid', $permission['group_uuid'])
                ->first();

            if (empty($existing_item)) {
                // Add new permission
                GroupPermissions::create([
                    'permission_name'        => $permission['permission_name'],
                    'permission_protected'  => $permission['permission_protected'],
                    'permission_assigned'  => $permission['permission_assigned'],
                    'group_name'            => $permission['group_name'],
                    'group_uuid'            => $permission['group_uuid'],
                    'insert_date'       => $permission['insert_date'],
                ]);

            }
        }
    }
}
