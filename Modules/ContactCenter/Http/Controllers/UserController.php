<?php

namespace Modules\ContactCenter\Http\Controllers;

use App\Models\User;
use App\Models\Groups;
use App\Models\UserGroup;
use App\Models\Extensions;
use Illuminate\Support\Str;
use App\Models\CallCenterAgents;
use App\Data\ExtensionDetailData;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}


    /**
     * Store a newly created resource in storage.
     */
    public function store()
    {
        $group_name = ucfirst(request('role'));
        try {
            DB::beginTransaction();

            $currentDomain = session('domain_uuid');

            $extension = QueryBuilder::for(Extensions::class)
                // only extensions in the current domain
                ->where('domain_uuid', $currentDomain)
                ->with(['voicemail' => function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain)
                        ->select('voicemail_id', 'domain_uuid', 'voicemail_mail_to');
                }])
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'directory_first_name',
                    'directory_last_name',
                ])
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();

            // wrap in your DTO
            $extensionDto = ExtensionDetailData::from($extension);

            // Check if user exists
            if (User::where('user_email', $extension->email)->exists()) {
                throw new \Exception('A user with this email already exists.');
            }

            // Create a new user
            $user = new User();

            $user->password      = Hash::make(Str::random(25));
            $user->domain_uuid   = $currentDomain;
            $user->add_user      = Auth::user()->username;
            $user->insert_date   = now();
            $user->insert_user   = session('user_uuid');
            $user->username      = trim($user->first_name . (!empty($user->last_name) ? '_' . $user->last_name : ''));
            $user->user_email    = $extension->email ?? '';
            $user->user_enabled  = true; // Assuming it's a boolean, otherwise use "true" (string)

            $user->save();

            $user->user_adv_fields()->create([
                'user_uuid'   => $user->user_uuid,
                'first_name'  => $extension->directory_first_name,
                'last_name'   => $extension->directory_last_name,
            ]);

            $user->settings()->createMany([
                [
                    'user_uuid'                => $user->user_uuid,
                    'domain_uuid'              => $user->domain_uuid,
                    'user_setting_category'    => 'domain',
                    'user_setting_subcategory' => 'language',
                    'user_setting_name'        => 'code',
                    'user_setting_value'       => get_domain_setting('language'),
                    'user_setting_enabled'     => true,
                    'insert_date'              => now(),
                    'insert_user'              => session('user_uuid'),
                ],
                [
                    'user_uuid'                => $user->user_uuid,
                    'domain_uuid'              => $user->domain_uuid,
                    'user_setting_category'    => 'domain',
                    'user_setting_subcategory' => 'time_zone',
                    'user_setting_name'        => 'name',
                    'user_setting_value'       => get_local_time_zone($currentDomain),
                    'user_setting_enabled'     => true,
                    'insert_date'              => now(),
                    'insert_user'              => session('user_uuid'),
                ]
            ]);


            $groupNames = [
                'Contact Center ' . $group_name, // Contact Center group
                'user',                          // Regular user group
            ];

            foreach ($groupNames as $name) {
                $group = Groups::where('group_name', $name)->first();

                if ($group) {
                    UserGroup::firstOrCreate(
                        [
                            'group_uuid' => $group->group_uuid,
                            'user_uuid'  => $user->user_uuid,
                        ],
                        [
                            'domain_uuid' => $currentDomain,
                            'group_name'  => $name,
                            'insert_date' => now(),
                            'insert_user' => session('user_uuid'),
                        ]
                    );
                }
            }

            // Create Contact Center Agent
            if ($group_name === 'Agent') {
                $agentData = [
                    'domain_uuid'               => $currentDomain,
                    'agent_name'                => trim($extension->directory_first_name . ' ' . $extension->directory_last_name),
                    'agent_type'                => 'callback',
                    'agent_call_timeout'        => 20,
                    'agent_id'                  => $extension->extension,
                    'agent_password'            => $extension->extension,
                    'agent_contact'             => 'user/' . $extension->extension . '@' . session('domain_name'),
                    'agent_max_no_answer'       => 0,
                    'agent_wrap_up_time'        => 10,
                    'agent_reject_delay_time'   => 90,
                    'agent_busy_delay_time'     => 90,
                    'agent_no_answer_delay_time' => 30,
                    'agent_record'              => 'false',
                    'insert_date'               => now(),
                    'insert_user'               => session('user_uuid'),
                ];

                // Check if the agent already exists
                $agent = CallCenterAgents::where('domain_uuid', $agentData['domain_uuid'])
                    ->where('agent_id', $agentData['agent_id'])
                    ->first();

                if ($agent) {
                    throw new \Exception('Agent already exists');
                }

                // Create new agent
                $agent = CallCenterAgents::create($agentData);

                $eslService = new FreeswitchEslService();

                // Add agent config to Freeswitch
                $command = sprintf(
                    'bgapi callcenter_config agent add %s %s',
                    $agent->call_center_agent_uuid,
                    'callback',
                );
                $result = $eslService->executeCommand($command, $disconnect = false);

                // Reload agent to update all settings
                $command = sprintf(
                    'bgapi callcenter_config agent reload %s',
                    $agent->call_center_agent_uuid,
                );
                $result = $eslService->executeCommand($command, $disconnect = false);
                $eslService->disconnect();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [$group_name .' created successfully']],
                'agent' => $agent ?? null,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('UserController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['error' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('contactcenter::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('contactcenter::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update() {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
