<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\MobileAppUsers;
use App\Models\DefaultSettings;
use App\Jobs\SendAppCredentials;
use App\Contracts\MobileAppProviderInterface;
use App\Services\CloudPlayApiService;
use App\Services\CloudPlayEnterpriseDirectorySync;
use App\Services\MobileApp\MobileAppProviderResolver;
use App\Services\RingotelApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Spatie\QueryBuilder\QueryBuilder;
use App\Models\MobileAppPasswordResetLinks;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Requests\UpdateRingotelApiTokenRequest;
use App\Http\Requests\StoreRingotelConnectionRequest;
use App\Http\Requests\PairRingotelOrganizationRequest;
use App\Http\Requests\UpdateRingotelConnectionRequest;
use App\Http\Requests\StoreRingotelOrganizationRequest;
use App\Http\Requests\UpdateRingotelOrganizationRequest;
use App\Traits\ChecksLimits;

class AppsController extends Controller
{
        use ChecksLimits;
    
    protected $ringotelApiService;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'RingotelAppSettings';
    protected $searchable = ['domain_name', 'domain_description'];
    protected $allowedSortFields = [
        'domain_name',
        'domain_description',
        'ringotel_status',
    ];

    public function __construct()
    {
        $this->model = new Domain();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        return Inertia::render(
            'CloudPlayAppSettings',
            [
                'data' => function () {
                    return $this->getData();
                },
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],
                'routes' => [
                    'current_page' => route('apps.index'),
                    'item_options' => route('apps.item.options'),
                    'create_customer' => route('apps.cloudplay.customer.create'),
                    'pair_customer' => route('apps.cloudplay.customer.pair'),
                    'get_all_customers' => route('apps.cloudplay.customer.all'),
                    'destroy_customer' => route('apps.cloudplay.customer.destroy'),
                    'get_customer' => route('apps.cloudplay.customer.get'),
                    'update_customer' => route('apps.cloudplay.customer.update'),
                    'get_profiles' => route('apps.cloudplay.profiles.all'),
                    'get_settings' => route('apps.cloudplay.settings.get'),
                    'update_settings' => route('apps.cloudplay.settings.update'),
                    'sync_enterprise_phonebook' => route('apps.cloudplay.enterprise-phonebook.sync'),
                ],
            ]
        );
    }

    /**
     *  Get data
     */
    public function getData($paginate = null)
    {
        $paginate ??= fspbx_pagination_per_page();

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $requestedSortField = request()->get('sortField', 'domain_name');
        $requestedSortOrder = request()->get('sortOrder', 'asc');

        $this->sortField = in_array($requestedSortField, $this->allowedSortFields, true)
            ? $requestedSortField
            : 'domain_name';
        $this->sortOrder = in_array($requestedSortOrder, ['asc', 'desc'], true)
            ? $requestedSortOrder
            : 'asc';

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // Normalize the query-level sort flag into the existing UI payload shape.
        $data->each(function ($domain) {
            $domain->ringotel_status = !empty($domain->ringotel_status_sort) ? 'true' : 'false';
            $domain->cloudplay_cust_id = $domain->settings
                ->firstWhere('domain_setting_subcategory', 'org_id')
                ?->domain_setting_value;
            $domain->cloudplay_cust_username = $domain->settings
                ->firstWhere('domain_setting_subcategory', 'cloudplay_cust_username')
                ?->domain_setting_value;
            $domain->cloudplay_profile_id = $domain->settings
                ->firstWhere('domain_setting_subcategory', 'cloudplay_profile_id')
                ?->domain_setting_value;
        });

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        // Get all domains with 'domain_enabled' set to 'true' and eager load settings
        $data->where('domain_enabled', 'true')
            ->with(['settings' => function ($query) {
                $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value')
                    ->where('domain_setting_category', 'app shell')
                    ->whereIn('domain_setting_subcategory', ['org_id', 'cloudplay_cust_username', 'cloudplay_profile_id'])
                    ->where('domain_setting_enabled', true);
            }]);

        $data->select(
            'domain_uuid',
            'domain_name',
            'domain_description',
        );

        $data->selectRaw("
            EXISTS (
                SELECT 1
                FROM v_domain_settings ds
                WHERE ds.domain_uuid = v_domains.domain_uuid
                  AND ds.domain_setting_category = ?
                  AND ds.domain_setting_subcategory = ?
                  AND ds.domain_setting_enabled = ?
            ) AS ringotel_status_sort
        ", ['app shell', 'org_id', true]);

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $sortColumn = $this->sortField === 'ringotel_status'
            ? 'ringotel_status_sort'
            : $this->sortField;

        $data->orderBy($sortColumn, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }

    public function getItemOptions(CloudPlayApiService $cloudPlayApiService)
    {
        try {
            return $this->getCloudPlayItemOptions($cloudPlayApiService);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [
                    'error' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    function getAppSettings($domain_uuid)
    {
        // Fetch all domain settings for the given domain_uuid
        $domainSettings = DomainSettings::where('domain_uuid', $domain_uuid)
            ->where('domain_setting_category', 'mobile_apps')
            ->where('domain_setting_enabled', true)
            ->pluck('domain_setting_value', 'domain_setting_subcategory');


        // Fetch all default settings
        $defaultSettings = DefaultSettings::where('default_setting_enabled', true)
            ->where('default_setting_category', 'mobile_apps')
            ->pluck('default_setting_value', 'default_setting_subcategory');

        // Merge settings, prioritizing domain-level settings
        $allSettings = $defaultSettings->merge($domainSettings);

        return $allSettings;
    }


    public function getUserPermissions()
    {
        $permissions = [];
        return $permissions;
    }

    /**
     * Submit API request to Ringotel to create a new organization
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrganization(StoreRingotelOrganizationRequest $request, RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to create organization
            $organization = $this->ringotelApiService->createOrganization($inputs);

            // Check for existing records
            $existingSetting = DomainSettings::where('domain_uuid', $inputs['domain_uuid'])
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->first();

            if ($existingSetting) {
                // Delete the existing record 
                $existingSetting->delete();
            }

            // Save the new record
            $domainSetting = DomainSettings::create([
                'domain_uuid' => $inputs['domain_uuid'],
                'domain_setting_category' => 'app shell',
                'domain_setting_subcategory' => 'org_id',
                'domain_setting_name' => 'text',
                'domain_setting_value' => $organization['id'],
                'domain_setting_enabled' => true,
            ]);

            // Check for existing records
            $existingSetting = DomainSettings::where('domain_uuid', $inputs['domain_uuid'])
                ->where('domain_setting_category', 'mobile_apps')
                ->where('domain_setting_subcategory', 'dont_send_user_credentials')
                ->first();

            if ($existingSetting) {
                $existingSetting->delete();
            }

            $domainSetting = DomainSettings::create([
                'domain_uuid' => $inputs['domain_uuid'],
                'domain_setting_category' => 'mobile_apps',
                'domain_setting_subcategory' => 'dont_send_user_credentials',
                'domain_setting_name' => 'boolean',
                'domain_setting_value' => $inputs['dont_send_user_credentials'],
                'domain_setting_enabled' => true,
                'domain_setting_description' => "Don't include user credentials in the welcome email"
            ]);

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $organization['id'],
                'messages' => ['success' => ['Organization successfully activated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to activate organization. Check logs for more details'], 'server2' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit API request to Ringotel to create a new organization
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function UpdateOrganization(UpdateRingotelOrganizationRequest $request, RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to update organization
            $organization = $this->ringotelApiService->updateOrganization($inputs);

            DomainSettings::updateOrCreate(
            [
                'domain_uuid' => $inputs['domain_uuid'],
                'domain_setting_category' => 'mobile_apps',
                'domain_setting_subcategory' => 'dont_send_user_credentials',
            ],
            [
                'domain_setting_name' => 'boolean',
                'domain_setting_value' => $inputs['dont_send_user_credentials'],
                'domain_setting_enabled' => true,
                'domain_setting_description' => "Don't include user credentials in the welcome email",
            ]
        );

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Organization successfully updated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update organization. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit request to destroy organization to Ringotel
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyOrganization(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        try {
            // Get Org ID from database
            $domain_uuid = request('domain_uuid');
            $org_id = $this->ringotelApiService->getOrgIdByDomainUuid($domain_uuid);

            // Remove local references from the database
            DomainSettings::where('domain_uuid', $domain_uuid)
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->delete();

            if (!$org_id) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Organization ID not found for the given domain.']]
                ], 404); // 404 Not Found
            }

            // Retrieve all connections for the organization
            $connections = $this->ringotelApiService->getConnections($org_id);

            // Delete each connection
            foreach ($connections as $connection) {
                $this->ringotelApiService->deleteConnection([
                    'conn_id' => $connection->id,
                    'org_id' => $org_id,
                ]);
            }

            // Delete the organization
            $deleteResponse = $this->ringotelApiService->deleteOrganization($org_id);

            if ($deleteResponse) {
                MobileAppUsers::where('domain_uuid', $domain_uuid)
                    ->where('org_id', $org_id)
                    ->delete();

                return response()->json([
                    'messages' => ['success' => ['Organization and its connections were successfully deleted.']]
                ], 200); // 200 OK
            }

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to delete the organization.']]
            ], 500); // 500 Internal Server Error

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error
        }
    }


    /**
     * Submit API request to Ringotel to create a new connection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createConnection(StoreRingotelConnectionRequest $request, RingotelApiService $ringotelApiService)
    {

        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to create connection
            $connection = $this->ringotelApiService->createConnection($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'org_id' => $inputs['org_id'],
                'conn_id' => $connection['id'],
                'connection_name' => $inputs['connection_name'],
                'domain' => $inputs['domain'] . ":" . $inputs['port'],
                'messages' => ['success' => ['Connection created successfully']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to add connection. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Submit API request to Ringotel to delete specified connection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyConnection(RingotelApiService $ringotelApiService)
    {

        $this->ringotelApiService = $ringotelApiService;

        try {
            // Send API request to delete connection
            $connection = $this->ringotelApiService->deleteConnection(request()->all());

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Connection deleted successfully']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to delete connection. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit API request to update connection
     *
     * @return \Illuminate\Http\Response
     */
    public function updateConnection(UpdateRingotelConnectionRequest $request, RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        $inputs = $request->validated();

        try {
            // Send API request to create connection
            $connection = $this->ringotelApiService->updateConnection($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Connection updated successfully']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update connection. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Submit getOrganizations request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrganizations(RingotelApiService $ringotelApiService)
    {
        $this->ringotelApiService = $ringotelApiService;

        try {
            $organizations = $this->ringotelApiService->getOrganizations();
            $formattedOrganizations = collect($organizations)->map(function ($org) {
                return [
                    'name' => "{$org->name} (id: {$org->id})",
                    'value' => $org->id,
                ];
            });
            return $formattedOrganizations;
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }


    /**
     * Submit getUsers request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsersByOrgId(RingotelApiService $ringotelApiService, $orgId)
    {

        $this->ringotelApiService = $ringotelApiService;
        try {
            $users = $this->ringotelApiService->getUsersByOrgId($orgId);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }


        return response()->json([
            'users' => $users,
            'status' => 200,
            'success' => [
                'message' => 'The request processed successfully'
            ]
        ]);
    }


    /**
     * Connect existing Ringotel organization to selected domain
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pairOrganization(PairRingotelOrganizationRequest $request)
    {
        // Extract data from the request
        $orgId = $request->input('org_id');
        $domainUuid = $request->input('domain_uuid');

        try {
            // Store or update the domain setting record
            $domainSettings = DomainSettings::updateOrCreate(
                [
                    'domain_uuid' => $domainUuid,
                    'domain_setting_category' => 'app shell',
                    'domain_setting_subcategory' => 'org_id',
                ],
                [
                    'domain_setting_name' => 'text',
                    'domain_setting_value' => $orgId,
                    'domain_setting_enabled' => true,
                ]
            );

            // Check if the record was saved successfully
            if (!$domainSettings) {
                throw new \Exception('Unable to connect this organization');
            }

            return response()->json([
                'messages' => ['success' => ['Connection updated successfully']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }


    /**
     * Sync Ringotel app users from the cloud
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncUsers(RingotelApiService $ringotelApiService)
    {
        // logger(request()->all());
        $this->ringotelApiService = $ringotelApiService;

        try {
            // Get all connections
            $connections = $this->ringotelApiService->getConnections(request('org_id'));
            $org_id = request('org_id');
            $domain_uuid = request('domain_uuid');

            // Retrieve all extensions in a single query
            $extensions = Extensions::where('domain_uuid', $domain_uuid)->get();
            $extensionMap = $extensions->keyBy('extension'); // Map extensions by 'extension' for quick lookups

            $mobileAppUsersData = []; // Array to hold bulk insert data

            foreach ($connections as $connection) {
                // Get all users for this connection
                $users = $this->ringotelApiService->getUsers($org_id, $connection->id);

                foreach ($users as $user) {
                    // Check if the extension exists in the map
                    $extension = $extensionMap->get($user->extension);

                    if ($extension) {
                        // Prepare data for bulk insert
                        $mobileAppUsersData[] = [
                            'extension_uuid' => $extension->extension_uuid,
                            'domain_uuid' => $extension->domain_uuid,
                            'org_id' => $org_id,
                            'conn_id' => $connection->id,
                            'user_id' => $user->id,
                            'status' => $user->status,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // Perform bulk insert for MobileAppUsers
            if (!empty($mobileAppUsersData)) {
                MobileAppUsers::insert($mobileAppUsersData);
            }

            return response()->json([
                'messages' => ['success' => ['User are successfully synced']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }

    /**
     * Return Ringotel app user settings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMobileAppOptions(MobileAppProviderResolver $providerResolver)
    {
        try {
            $provider = $providerResolver->resolve();
            $mobile_app = QueryBuilder::for(MobileAppUsers::query())
                ->select('mobile_app_user_uuid', 'org_id', 'conn_id', 'user_id', 'status')
                ->where('extension_uuid', request('extension_uuid'))
                ->first();

            $org_id = DomainSettings::where('domain_uuid', session('domain_uuid'))
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->where('domain_setting_enabled', true)
                ->value('domain_setting_value');

            if (empty($org_id)) {
                throw new \Exception("Contact your administrator to enable mobile apps.");
            }

            if ($provider->getProviderKey() === 'cloudplay') {
                $credentials = app(CloudPlayApiService::class)->getCustomerCredentials(session('domain_uuid'));
                if ($credentials['username'] === '') {
                    throw new \Exception("Contact your administrator to enable mobile apps.");
                }
            }

            $profileId = null;
            $profileName = null;
            if ($provider->getProviderKey() === 'cloudplay') {
                $cloudPlayApiService = app(CloudPlayApiService::class);
                $profileId = $cloudPlayApiService->getProfileId(session('domain_uuid'));
                if ($profileId) {
                    $profileName = $cloudPlayApiService->getProfiles(session('domain_uuid'))
                        ->first(fn ($profile) => (int) ($profile['profile_id'] ?? 0) === (int) $profileId)['profile_name'] ?? null;
                }
            }

            $connections = $provider->requiresConnectionSelection()
                ? $provider->getConnections($org_id)
                : collect();

            return response()->json([
                'mobile_app' => $mobile_app,
                'org_id' => $org_id,
                'connections' => $connections,
                'provider' => $provider->getProviderKey(),
                'requires_connection' => $provider->requiresConnectionSelection(),
                'supports_contact_only' => $provider->supportsContactOnlyUsers(),
                'cloudplay_profile_id' => $profileId,
                'cloudplay_profile_name' => $profileName,
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@getMobileAppOptions error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 404);
        }
    }


    /**
     * Submit new user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(MobileAppProviderResolver $providerResolver)
    {
        $provider = $providerResolver->resolve();

        try {
            $currentDomain = session('domain_uuid');
            
       // Check for limits
        if (request('status') == 1) {
            if ($resp = $this->enforceLimit(
                'mobile_app_users',
                \App\Models\MobileAppUsers::class,
                'domain_uuid'
            )) {
                return $resp;
            }
        }

            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'password',
                    'effective_caller_id_name',
                    'effective_caller_id_number',

                ])
                ->with([
                    'voicemail' => function ($query) use ($currentDomain) {
                        $query->where('domain_uuid', $currentDomain)
                            ->select(
                                'voicemail_uuid',
                                'domain_uuid',
                                'voicemail_id',
                                'voicemail_mail_to',
                            );
                    },

                ])
                ->where('domain_uuid', $currentDomain)
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();



            // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
            $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
            if ($hidePassInEmail === null) {
                $hidePassInEmail = 'false';
            }

            if (!$provider->supportsContactOnlyUsers() && (int) request('status') === -1) {
                throw new \Exception('Contact-only mobile app users are not supported for the selected provider.');
            }

            $params = [
                'org_id' => request('org_id'),
                'conn_id' => request('connection'),
                'domain_uuid' => $currentDomain,
                'name' => $extension->effective_caller_id_name,
                'email' => $extension->email ? $extension->email : "",
                'ext' => $extension->extension,
                'username' => $extension->extension,
                'authname' => $extension->extension,
                'password' => $extension->password,
                'status' => request('status'),
                'noemail' => true,
            ];

            $user = $provider->createUser($params);

            $passwordUrlShow = null;

            // If success and user is activated send user email with credentials
            if ($user) {
                if ($hidePassInEmail == 'true' && request('status') == 1) {
                    // Include get-password link and remove password value
                    $passwordToken = Str::random(40);
                    MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
                    $appCredentials = new MobileAppPasswordResetLinks();
                    $appCredentials->token = $passwordToken;
                    $appCredentials->extension_uuid = $extension->extension_uuid;
                    $appCredentials->domain = $user['domain'];
                    $appCredentials->save();

                    $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
                    $includePasswordUrl = route('appsGetPasswordByToken', $passwordToken);
                    $user['password_url'] = $includePasswordUrl;
                }
                if ($extension->email && (int) request('status') === 1) {
                    $this->dispatchAppCredentials($user, $extension, $currentDomain, 1);
                }
            }

            // Delete any prior info from database
            MobileAppUsers::where('extension_uuid', $extension->extension_uuid)->delete();

            // Save returned user info in database
            $appUser = new MobileAppUsers();
            $appUser->extension_uuid = $extension->extension_uuid;
            $appUser->domain_uuid = $extension->domain_uuid;
            $appUser->org_id = request('org_id');
            $appUser->conn_id = $this->mobileAppConnIdForStorage(request('connection'), $provider);
            $appUser->user_id = $user['id'];
            $appUser->status = $user['status'];
            app(CloudPlayEnterpriseDirectorySync::class)->adoptExtensionEdId($extension, $appUser);
            $appUser->save();

            if ((int) request('status') === 1) {
                app(CloudPlayEnterpriseDirectorySync::class)->sync($provider, $extension, $appUser, true);
            }

            $qrcode = $this->buildMobileAppQrCode($provider, $user, $hidePassInEmail, (int) request('status'));

            if ($hidePassInEmail != 'false') {
                $user['password_url'] = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
                $user['password'] = null;
            }

            return response()->json([
                'user' => $user,
                'qrcode' => $qrcode,
                'messages' => ['success' => ['Mobile app has been enabled']]
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@createUser error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 404);
        }
    }

    /**
     * Submit delete user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(MobileAppProviderResolver $providerResolver)
    {
        $provider = $providerResolver->resolve();

        try {
            $mobileApp = MobileAppUsers::find(request('mobile_app_user_uuid'));
            app(CloudPlayEnterpriseDirectorySync::class)->remove($provider, $mobileApp);
            $mobileApp?->delete();

            $params = [
                'org_id' => request('org_id'),
                'user_id' => request('user_id'),
                'domain_uuid' => session('domain_uuid'),
            ];

            $response = $provider->deleteUser($params);

            return response()->json([
                'messages' => ['success' => ['Mobile app has been removed']]
            ], 200);
        } catch (\Exception $e) {
            logger('ExtensionsController@deleteUser error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }


    /**
     * Submit password reset request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(MobileAppProviderResolver $providerResolver)
    {
        $provider = $providerResolver->resolve();

        try {
            $currentDomain = session('domain_uuid');

            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'effective_caller_id_name',
                ])
                ->with([
                    'voicemail' => function ($query) use ($currentDomain) {
                        $query->where('domain_uuid', $currentDomain)
                            ->select(
                                'voicemail_uuid',
                                'domain_uuid',
                                'voicemail_id',
                                'voicemail_mail_to',
                            );
                    },
                ])
                ->where('domain_uuid', $currentDomain)
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();

            $params = [
                'org_id' => request('org_id'),
                'user_id' => request('user_id'),
                'domain_uuid' => $currentDomain,
                'noemail' => true,
                'username' => $extension->extension,
                'ext' => $extension->extension,
                'authname' => $extension->extension,
                'password' => $extension->password,
                'name' => $extension->effective_caller_id_name,
                'email' => $extension->email ?? '',
            ];

            $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
            if ($hidePassInEmail === null) {
                $hidePassInEmail = 'false';
            }

            $user = $provider->resetPassword($params);

            // If success and user is activated send user email with credentials
            if ($user) {
                $email = $extension->email;
                if ($email) {
                    $user['email'] = $email;
                }

                if ($hidePassInEmail == 'true') {
                    // Include get-password link
                    $passwordToken = Str::random(40);
                    MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
                    $appCredentials = new MobileAppPasswordResetLinks();
                    $appCredentials->token = $passwordToken;
                    $appCredentials->extension_uuid = $extension->extension_uuid;
                    $appCredentials->domain = $user['domain'];
                    $appCredentials->save();

                    $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
                    $includePasswordUrl = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
                    $user['password_url'] = $includePasswordUrl;
                }
                if ($email) {
                    $this->dispatchAppCredentials($user, $extension, $currentDomain, 1);
                }
            }

            $qrcode = $this->buildMobileAppQrCode($provider, $user, $hidePassInEmail, 1);

            if ($hidePassInEmail != 'false') {
                $user['password'] = null;
            }

            return response()->json([
                'user' => $user,
                'qrcode' => $qrcode,
                'messages' => ['success' => ['Mobile app credentials have been reset']]
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@resetPassword error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
            ], 404);
        }
    }

    /**
     * Submit bulk mobile app user actions for selected extensions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUserAction(Request $request, MobileAppProviderResolver $providerResolver)
    {
        $provider = $providerResolver->resolve();

        if (!userCheckPermission('extension_edit') || !userCheckPermission('extension_mobile_app_settings')) {
            return response()->json([
                'success' => false,
                'errors' => ['permission' => ['Access denied.']],
            ], 403);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['uuid'],
            'action' => ['required', 'in:enable,add_contact,deactivate,remove,reset_credentials'],
            'connection' => ['nullable', 'string'],
        ]);

        if (
            $provider->requiresConnectionSelection()
            && in_array($data['action'], ['enable', 'add_contact'], true)
            && empty($data['connection'])
        ) {
            return response()->json([
                'success' => false,
                'errors' => ['connection' => ['A mobile app connection is required.']],
            ], 422);
        }

        if ($data['action'] === 'add_contact' && !$provider->supportsContactOnlyUsers()) {
            return response()->json([
                'success' => false,
                'errors' => ['action' => ['Contact-only mobile app users are not supported for the selected provider.']],
            ], 422);
        }

        $orgId = DomainSettings::where('domain_uuid', session('domain_uuid'))
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->where('domain_setting_enabled', true)
            ->value('domain_setting_value');

        if (empty($orgId)) {
            return response()->json([
                'success' => false,
                'errors' => ['mobile_app' => ['Contact your administrator to enable mobile apps.']],
            ], 422);
        }

        $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
        if ($hidePassInEmail === null) {
            $hidePassInEmail = 'false';
        }

        $ids = array_values(array_unique($data['items']));

        $extensions = Extensions::query()
            ->with([
                'mobile_app',
                'voicemail' => function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->select('voicemail_uuid', 'domain_uuid', 'voicemail_id', 'voicemail_mail_to');
                },
            ])
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('extension_uuid', $ids)
            ->get();

        $processed = 0;
        $skipped = max(count($ids) - $extensions->count(), 0);
        $failed = 0;

        foreach ($extensions as $extension) {
            try {
                $mobileApp = $extension->mobile_app;
                $user = null;

                if ($data['action'] === 'add_contact') {
                    if ($mobileApp) {
                        $skipped++;
                        continue;
                    }

                    $status = -1;

                    $user = $provider->createUser([
                        'org_id' => $orgId,
                        'conn_id' => $data['connection'] ?? null,
                        'domain_uuid' => session('domain_uuid'),
                        'name' => $extension->effective_caller_id_name,
                        'email' => $extension->email ?: '',
                        'ext' => $extension->extension,
                        'username' => $extension->extension,
                        'authname' => $extension->extension,
                        'password' => $extension->password,
                        'status' => $status,
                        'noemail' => true,
                    ]);

                    MobileAppUsers::where('extension_uuid', $extension->extension_uuid)->delete();

                    $appUser = new MobileAppUsers();
                    $appUser->extension_uuid = $extension->extension_uuid;
                    $appUser->domain_uuid = $extension->domain_uuid;
                    $appUser->org_id = $orgId;
                    $appUser->conn_id = $this->mobileAppConnIdForStorage($data['connection'] ?? null, $provider);
                    $appUser->user_id = $user['id'];
                    $appUser->status = $user['status'];
                    $appUser->save();

                    $processed++;
                    continue;
                }

                if ($data['action'] === 'enable') {
                    if (!$mobileApp) {
                        if ($resp = $this->enforceLimit('mobile_app_users', MobileAppUsers::class, 'domain_uuid')) {
                            $skipped++;
                            continue;
                        }

                        $user = $provider->createUser([
                            'org_id' => $orgId,
                            'conn_id' => $data['connection'] ?? null,
                            'domain_uuid' => session('domain_uuid'),
                            'name' => $extension->effective_caller_id_name,
                            'email' => $extension->email ?: '',
                            'ext' => $extension->extension,
                            'username' => $extension->extension,
                            'authname' => $extension->extension,
                            'password' => $extension->password,
                            'status' => 1,
                            'noemail' => true,
                        ]);

                        MobileAppUsers::where('extension_uuid', $extension->extension_uuid)->delete();

                        $appUser = new MobileAppUsers();
                        $appUser->extension_uuid = $extension->extension_uuid;
                        $appUser->domain_uuid = $extension->domain_uuid;
                        $appUser->org_id = $orgId;
                        $appUser->conn_id = $this->mobileAppConnIdForStorage($data['connection'] ?? null, $provider);
                        $appUser->user_id = $user['id'];
                        $appUser->status = $user['status'];
                        app(CloudPlayEnterpriseDirectorySync::class)->adoptExtensionEdId($extension, $appUser);
                        $appUser->save();
                        app(CloudPlayEnterpriseDirectorySync::class)->sync($provider, $extension, $appUser, true);
                    } elseif ((int) $mobileApp->status !== 1) {
                        $user = $provider->updateUser([
                            'user_id' => $mobileApp->user_id,
                            'org_id' => $mobileApp->org_id,
                            'conn_id' => $mobileApp->conn_id,
                            'domain_uuid' => session('domain_uuid'),
                            'status' => 1,
                            'no_email' => true,
                            'name' => $extension->effective_caller_id_name,
                            'email' => $extension->email ?: '',
                            'ext' => $extension->extension,
                            'username' => $extension->extension,
                            'authname' => $extension->extension,
                            'password' => $extension->password,
                        ]);

                        app(CloudPlayEnterpriseDirectorySync::class)->adoptExtensionEdId($extension, $mobileApp);
                        $mobileApp->status = 1;
                        $mobileApp->save();
                        app(CloudPlayEnterpriseDirectorySync::class)->sync($provider, $extension, $mobileApp, true);
                    } else {
                        $skipped++;
                        continue;
                    }

                    if ($user && $extension->email) {
                        $user['email'] = $user['email'] ?? $extension->email;

                        if ($hidePassInEmail === 'true') {
                            $passwordToken = Str::random(40);
                            MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
                            $appCredentials = new MobileAppPasswordResetLinks();
                            $appCredentials->token = $passwordToken;
                            $appCredentials->extension_uuid = $extension->extension_uuid;
                            $appCredentials->domain = $user['domain'];
                            $appCredentials->save();
                            $user['password_url'] = route('appsGetPasswordByToken', $passwordToken);
                        }

                        $this->dispatchAppCredentials($user, $extension, session('domain_uuid'), 1);
                    }

                    $processed++;
                    continue;
                }

                if (!$mobileApp) {
                    $skipped++;
                    continue;
                }

                if ($data['action'] === 'deactivate') {
                    if ((int) $mobileApp->status !== 1) {
                        $skipped++;
                        continue;
                    }

                    $provider->deactivateUser([
                        'org_id' => $mobileApp->org_id,
                        'user_id' => $mobileApp->user_id,
                        'domain_uuid' => session('domain_uuid'),
                        'name' => $extension->effective_caller_id_name,
                        'email' => $extension->email ?: '',
                        'ext' => $extension->extension,
                        'username' => $extension->extension,
                        'authname' => $extension->extension,
                    ]);
                    app(CloudPlayEnterpriseDirectorySync::class)->sync($provider, $extension, $mobileApp, false);

                    if ($provider->getProviderKey() === 'ringotel') {
                        $users = app(RingotelApiService::class)->getUsers($mobileApp->org_id, $mobileApp->conn_id);
                        $user = collect($users)->firstWhere('username', $extension->extension);

                        if ($user) {
                            $mobileApp->user_id = $user->id;
                        }
                    }

                    $mobileApp->status = -1;
                    $mobileApp->save();
                    $processed++;
                    continue;
                }

                if ($data['action'] === 'remove') {
                    app(CloudPlayEnterpriseDirectorySync::class)->remove($provider, $mobileApp);

                    $provider->deleteUser([
                        'org_id' => $mobileApp->org_id,
                        'user_id' => $mobileApp->user_id,
                        'domain_uuid' => session('domain_uuid'),
                    ]);

                    $mobileApp->delete();
                    $processed++;
                    continue;
                }

                if ($data['action'] === 'reset_credentials') {
                    if ((int) $mobileApp->status !== 1) {
                        $skipped++;
                        continue;
                    }

                    $user = $provider->resetPassword([
                        'org_id' => $mobileApp->org_id,
                        'user_id' => $mobileApp->user_id,
                        'domain_uuid' => session('domain_uuid'),
                        'noemail' => true,
                        'username' => $extension->extension,
                        'ext' => $extension->extension,
                        'authname' => $extension->extension,
                        'password' => $extension->password,
                        'name' => $extension->effective_caller_id_name,
                        'email' => $extension->email ?: '',
                    ]);

                    if ($user && $extension->email) {
                        $user['email'] = $user['email'] ?? $extension->email;

                        if ($hidePassInEmail === 'true') {
                            $passwordToken = Str::random(40);
                            MobileAppPasswordResetLinks::where('extension_uuid', $extension->extension_uuid)->delete();
                            $appCredentials = new MobileAppPasswordResetLinks();
                            $appCredentials->token = $passwordToken;
                            $appCredentials->extension_uuid = $extension->extension_uuid;
                            $appCredentials->domain = $user['domain'];
                            $appCredentials->save();
                            $user['password_url'] = route('appsGetPasswordByToken', $passwordToken);
                        }

                        $this->dispatchAppCredentials($user, $extension, session('domain_uuid'), 1);
                    }

                    $processed++;
                    continue;
                }
            } catch (\Throwable $e) {
                $failed++;
                logger('AppsController@bulkUserAction item error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine(), [
                    'extension_uuid' => $extension->extension_uuid,
                    'action' => $data['action'],
                ]);
            }
        }

        return response()->json([
            'messages' => [
                'success' => [
                    'Mobile app bulk action completed successfully.',
                    "Processed {$processed}, skipped " . ($skipped + $failed) . '.',
                ],
            ],
        ], 200);
    }


    /**
     * Submit activate user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateUser(MobileAppProviderResolver $providerResolver)
    {
        $provider = $providerResolver->resolve();

        try {
            $currentDomain = session('domain_uuid');

            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'password',
                    'effective_caller_id_name',
                    'effective_caller_id_number',

                ])
                ->with([
                    'voicemail' => function ($query) use ($currentDomain) {
                        $query->where('domain_uuid', $currentDomain)
                            ->select(
                                'voicemail_uuid',
                                'domain_uuid',
                                'voicemail_id',
                                'voicemail_mail_to',
                            );
                    },

                    'mobile_app' => function ($query) {
                        $query->select(
                            'mobile_app_user_uuid',
                            'extension_uuid',
                            'conn_id',
                        );
                    },

                ])
                ->where('domain_uuid', $currentDomain)
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();

            $params = [
                'user_id'   => request('user_id'),
                'org_id'    => request('org_id'),
                'conn_id'   => $extension->mobile_app->conn_id,
                'domain_uuid' => $currentDomain,
                'status'    => 1,
                'no_email'  => true,
                'name'      => $extension->effective_caller_id_name,
                'email'     => $extension->email ?? '',
                'ext'       => $extension->extension,
                'username'  => $extension->extension,
                'authname'  => $extension->extension,
                'password'  => $extension->password,
            ];

            $user = $provider->updateUser($params);

            app(CloudPlayEnterpriseDirectorySync::class)->adoptExtensionEdId($extension, $extension->mobile_app);
            $extension->mobile_app->status = 1;
            $extension->mobile_app->save();
            app(CloudPlayEnterpriseDirectorySync::class)->sync($provider, $extension, $extension->mobile_app, true);

            // We don't show the password and QR code for the organisations that has dont_send_user_credentials=true
            $hidePassInEmail = get_domain_setting('dont_send_user_credentials');
            if ($hidePassInEmail === null) {
                $hidePassInEmail = 'false';
            }

            // If success and user is activated send user email with credentials
            if ($user) {
                if ($hidePassInEmail == 'true') {
                    // Include get-password link
                    $passwordToken = Str::random(40);
                    MobileAppPasswordResetLinks::where('extension_uuid', request('extension_uuid'))->delete();
                    $appCredentials = new MobileAppPasswordResetLinks();
                    $appCredentials->token = $passwordToken;
                    $appCredentials->extension_uuid = request('extension_uuid');
                    $appCredentials->domain = $user['domain'];
                    $appCredentials->save();

                    $passwordUrlShow = userCheckPermission('mobile_apps_password_url_show') ?? 'false';
                    $includePasswordUrl = route('appsGetPasswordByToken', $passwordToken);
                    $user['password_url'] = $includePasswordUrl;
                }
                if ($extension->email) {
                    $this->dispatchAppCredentials($user, $extension, $currentDomain, 1);
                }
            }

            $qrcode = $this->buildMobileAppQrCode($provider, $user, $hidePassInEmail, 1);

            if ($hidePassInEmail != 'false') {
                $user['password_url'] = $passwordUrlShow == 'true' ? route('appsGetPasswordByToken', $passwordToken) : null;
                $user['password'] = null;
            }

            return response()->json([
                'user' => $user,
                'qrcode' => $qrcode,
                'messages' => ['success' => ['Mobile app has been activated']]
            ], 200);
        } catch (\Exception $e) {
            logger('ExtensionsController@activateUser error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'status' => 500,
                'error' => [
                    'message' => 'An unexpected error occurred. Please try again later.',
                ],
            ]);
        }
    }

    /**
     * Submit deactivate user request to Ringotel API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivateUser(MobileAppProviderResolver $providerResolver)
    {
        $provider = $providerResolver->resolve();

        try {
            $mobile_app = MobileAppUsers::with('extension')->find(request('mobile_app_user_uuid'));

            if (!$mobile_app) {
                return response()->json([
                    'success' => false,
                    'errors' => ['error' => ['Mobile app user not found.']],
                ], 404);
            }

            $extension = $mobile_app->extension;

            $params = [
                'org_id' => request('org_id'),
                'user_id' => request('user_id'),
                'domain_uuid' => session('domain_uuid'),
                'name' => $extension?->effective_caller_id_name ?? '',
                'email' => $extension?->email ?? '',
                'ext' => $extension?->extension ?? request('ext'),
                'username' => $extension?->extension ?? request('ext'),
                'authname' => $extension?->extension ?? request('ext'),
            ];

            $provider->deactivateUser($params);
            app(CloudPlayEnterpriseDirectorySync::class)->sync($provider, $extension, $mobile_app, false);

            if ($provider->getProviderKey() === 'ringotel') {
                $users = app(RingotelApiService::class)->getUsers(request('org_id'), request('conn_id'));
                $user = collect($users)->firstWhere('username', request('ext'));

                if ($user) {
                    $mobile_app = MobileAppUsers::where('user_id', request('user_id'))->first();
                    $mobile_app->user_id = $user->id;
                    $mobile_app->status = -1;
                    $mobile_app->save();
                }
            } else {
                $mobile_app->status = -1;
                $mobile_app->save();
            }

            return response()->json([
                'messages' => ['success' => ['Mobile app has been deactivated']]
            ], 200);
        } catch (\Throwable $e) {
            logger('AppsController@deactivateUser error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['error' => [$e->getMessage()]],
            ], 422);
        }
    }

    public function emailUser()
    {
        SendAppCredentials::dispatch()->onQueue('emails');

        //Log::info('Dispatched email ');
        return 'Dispatched email ';
    }

    /**
     * Retrieve the Ringotel API token from DefaultSettings.
     *
     * @param UpdateRingotelApiTokenRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getToken()
    {
        try {
            // Retrieve the API token from DefaultSettings
            $token = DefaultSettings::where([
                ['default_setting_category', '=', 'mobile_apps'],
                ['default_setting_subcategory', '=', 'ringotel_api_token'],
                ['default_setting_enabled', '=', 'true'],
            ])->value('default_setting_value');

            return response()->json([
                'success' => true,
                'token' => $token,
            ], 200); // 200 OK with the token value
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to retrieve API Token. Check logs for more details']],
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Update or create the Ringotel API token in DefaultSettings.
     *
     * @param UpdateRingotelApiTokenRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateToken(UpdateRingotelApiTokenRequest $request)
    {
        $inputs = $request->validated();

        try {
            // Update or create the Ringotel API token in DefaultSettings
            DefaultSettings::updateOrCreate(
                [
                    'default_setting_category' => 'mobile_apps',
                    'default_setting_subcategory' => 'ringotel_api_token',
                ],
                [
                    'default_setting_name' => 'text',
                    'default_setting_value' => $inputs['token'], // Use the validated token input
                    'default_setting_enabled' => 'true', // Ensure the setting is enabled
                ]
            );

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['API Token was successfully updated']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update API Token. Check logs for more details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function getRegions()
    {
        $cacheKey = 'ringotel_regions';
        $cacheDuration = now()->addDay(); // Cache for 1 day

        $regions = Cache::remember($cacheKey, $cacheDuration, function () {
            $regions = $this->ringotelApiService->getRegions();

            return $regions->map(function ($region) {
                return [
                    'value' => $region->id,
                    'name' => $region->name,
                ];
            })
                ->sortBy('value') // Sort the collection by the 'value' field
                ->values() // Reset the keys after sorting
                ->toArray();
        });

        return $regions;
    }


    public function getProvider()
    {
        return response()->json([
            'provider' => get_mobile_app_provider(),
        ]);
    }

    public function updateProvider(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:ringotel,cloudplay'],
        ]);

        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'mobile_app_provider',
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => $data['provider'],
                'default_setting_enabled' => 'true',
            ]
        );

        return response()->json([
            'messages' => ['success' => ['Mobile app provider updated successfully.']],
            'provider' => $data['provider'],
        ]);
    }

    public function getCloudPlaySettings()
    {
        $settings = DefaultSettings::where('default_setting_category', 'mobile_apps')
            ->whereIn('default_setting_subcategory', [
                'cloudplay_api_url',
                'cloudplay_admin_username',
            ])
            ->pluck('default_setting_value', 'default_setting_subcategory');

        return response()->json([
            'api_url' => $settings->get('cloudplay_api_url', config('cloudplay.api_url')),
            'admin_username' => $settings->get('cloudplay_admin_username', config('cloudplay.admin_username')),
            'has_admin_password' => DefaultSettings::where([
                ['default_setting_category', '=', 'mobile_apps'],
                ['default_setting_subcategory', '=', 'cloudplay_admin_password'],
                ['default_setting_enabled', '=', 'true'],
            ])->whereNotNull('default_setting_value')->exists(),
        ]);
    }

    public function updateCloudPlaySettings(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'api_url' => ['required', 'url'],
            'admin_username' => ['required', 'string'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $apiUrl = rtrim($data['api_url'], '/');
        $adminPassword = $data['admin_password'] ?? '';
        if ($adminPassword === '') {
            $adminPassword = $cloudPlayApiService->getAdminPassword();
        }

        if ($adminPassword === '') {
            return response()->json([
                'errors' => ['admin_password' => ['Admin password is required for the first CloudPLAY API setup.']],
            ], 422);
        }

        try {
            $cloudPlayApiService->verifyAdminCredentials($apiUrl, $data['admin_username'], $adminPassword);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => ['server' => [$e->getMessage()]],
            ], 422);
        }

        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'cloudplay_api_url',
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => $apiUrl,
                'default_setting_enabled' => 'true',
            ]
        );

        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'cloudplay_admin_username',
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => $data['admin_username'],
                'default_setting_enabled' => 'true',
            ]
        );

        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'mobile_apps',
                'default_setting_subcategory' => 'cloudplay_admin_password',
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => Crypt::encryptString($adminPassword),
                'default_setting_enabled' => 'true',
            ]
        );

        Cache::forget('cloudplay_admin_token');

        return response()->json([
            'messages' => ['success' => ['CloudPLAY settings updated successfully.']],
        ]);
    }

    public function getCloudPlayCustomers(CloudPlayApiService $cloudPlayApiService)
    {
        try {
            $customers = $cloudPlayApiService->getCustomers()->map(function ($customer) {
                $label = trim(($customer['cust_cmp_name'] ?? '') . ' (' . ($customer['cust_username'] ?? '') . ')');

                return [
                    'value' => (string) ($customer['cust_id'] ?? ''),
                    'name' => $label !== ' ()' ? $label : (string) ($customer['cust_username'] ?? ''),
                    'cust_username' => $customer['cust_username'] ?? '',
                ];
            })->values();

            return response()->json([
                'customers' => $customers,
            ]);
        } catch (\Throwable $e) {
            logger('AppsController@getCloudPlayCustomers error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function syncEnterprisePhonebook(Request $request, CloudPlayEnterpriseDirectorySync $directorySync)
    {
        if (!userCheckPermission('extension_mobile_app_settings')) {
            return response()->json([
                'messages' => ['error' => ['You do not have permission to sync the enterprise phonebook.']],
            ], 403);
        }

        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
        ]);

        $domain = Domain::query()
            ->where('domain_uuid', $data['domain_uuid'])
            ->where('domain_enabled', 'true')
            ->first();

        if (!$domain) {
            return response()->json([
                'messages' => ['error' => ['Tenant not found or is disabled.']],
            ], 404);
        }

        $accessibleDomains = collect(session('domains') ?: []);

        if ($accessibleDomains->isNotEmpty() && !$accessibleDomains->pluck('domain_uuid')->contains($data['domain_uuid'])) {
            return response()->json([
                'messages' => ['error' => ['You do not have access to this tenant.']],
            ], 403);
        }

        try {
            $result = $directorySync->bulkSyncPhonebookOnlyExtensions($data['domain_uuid']);

            $messages = [
                sprintf(
                    'Synced %d extension(s) to CloudPLAY enterprise phonebook.',
                    $result['synced']
                ),
            ];

            if (($result['removed'] ?? 0) > 0) {
                $messages[] = sprintf('%d stale enterprise phonebook entry(ies) removed.', $result['removed']);
            }

            if ($result['skipped'] > 0) {
                $messages[] = sprintf('%d extension(s) were skipped.', $result['skipped']);
            }

            if (!empty($result['failed'])) {
                $messages[] = sprintf('%d extension(s) failed to sync or remove.', count($result['failed']));
            }

            return response()->json([
                'messages' => ['success' => $messages],
                'result' => $result,
            ], 200);
        } catch (\Throwable $e) {
            logger('AppsController@syncEnterprisePhonebook error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => [$e->getMessage()]],
            ], 422);
        }
    }

    public function createCloudPlayCustomer(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
            'cust_firstname' => ['required', 'string'],
            'cust_lastname' => ['required', 'string'],
            'cust_cmp_name' => ['required', 'string'],
            'cust_username' => ['required', 'string'],
            'cust_password' => ['required', 'string'],
            'cust_contact_email' => ['required', 'email'],
            'cust_contact_no' => ['nullable', 'string'],
            'cust_auth_ips' => ['nullable', 'string'],
        ]);

        try {
            $customer = $cloudPlayApiService->createCustomer($data);
            $cloudPlayApiService->saveDomainCustomer(
                $data['domain_uuid'],
                (int) $customer['cust_id'],
                $data['cust_username'],
                $data['cust_password'],
            );

            return response()->json([
                'cust_id' => $customer['cust_id'],
                'messages' => ['success' => ['CloudPLAY customer activated successfully.']],
            ], 201);
        } catch (\Throwable $e) {
            logger('AppsController@createCloudPlayCustomer error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function getCloudPlayCustomer(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
        ]);

        $domain = Domain::query()
            ->select('domain_uuid', 'domain_name', 'domain_description')
            ->whereKey($data['domain_uuid'])
            ->firstOrFail();

        $credentials = $cloudPlayApiService->getCustomerCredentials($domain->domain_uuid);
        $custId = $cloudPlayApiService->getCustomerId($domain->domain_uuid);

        return response()->json([
            'domain_uuid' => $domain->domain_uuid,
            'domain_name' => $domain->domain_name,
            'domain_description' => $domain->domain_description,
            'cust_id' => $custId,
            'cust_username' => $credentials['username'],
            'has_password' => $credentials['password'] !== '',
            'profile_id' => $cloudPlayApiService->getProfileId($domain->domain_uuid),
        ]);
    }

    public function getCloudPlayProfiles(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
        ]);

        try {
            $profiles = $cloudPlayApiService->getProfiles($data['domain_uuid'])->map(function ($profile) {
                return [
                    'value' => (string) ($profile['profile_id'] ?? ''),
                    'name' => trim(($profile['profile_name'] ?? '') . ' (ID ' . ($profile['profile_id'] ?? '') . ')'),
                    'profile_id' => $profile['profile_id'] ?? null,
                    'profile_name' => $profile['profile_name'] ?? '',
                ];
            })->values();

            return response()->json([
                'profiles' => $profiles,
            ]);
        } catch (\Throwable $e) {
            logger('AppsController@getCloudPlayProfiles error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function updateCloudPlayCustomer(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
            'cust_username' => ['required', 'string'],
            'cust_password' => ['nullable', 'string'],
            'profile_id' => ['nullable', 'integer'],
        ]);

        $custId = $cloudPlayApiService->getCustomerId($data['domain_uuid']);
        if (empty($custId)) {
            return response()->json([
                'errors' => ['server' => ['This tenant is not connected to CloudPLAY yet.']],
            ], 422);
        }

        $password = $data['cust_password'] ?? '';
        if ($password === '') {
            $password = $cloudPlayApiService->getCustomerCredentials($data['domain_uuid'])['password'];
        }

        if ($password === '') {
            return response()->json([
                'errors' => ['cust_password' => ['Customer password is required.']],
            ], 422);
        }

        try {
            $cloudPlayApiService->verifyCustomerCredentials($data['cust_username'], $password);
            $cloudPlayApiService->saveDomainCustomer(
                $data['domain_uuid'],
                (int) $custId,
                $data['cust_username'],
                $password,
                isset($data['profile_id']) ? (int) $data['profile_id'] : null,
            );

            if (array_key_exists('profile_id', $data) && (empty($data['profile_id']) || (int) $data['profile_id'] <= 0)) {
                DomainSettings::where('domain_uuid', $data['domain_uuid'])
                    ->where('domain_setting_category', 'app shell')
                    ->where('domain_setting_subcategory', 'cloudplay_profile_id')
                    ->delete();
            }

            return response()->json([
                'messages' => ['success' => ['Tenant CloudPLAY connection updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('AppsController@updateCloudPlayCustomer error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function pairCloudPlayCustomer(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
            'cust_id' => ['required'],
            'cust_username' => ['required', 'string'],
            'cust_password' => ['required', 'string'],
            'profile_id' => ['nullable', 'integer'],
        ]);

        try {
            $cloudPlayApiService->verifyCustomerCredentials($data['cust_username'], $data['cust_password']);
            $cloudPlayApiService->saveDomainCustomer(
                $data['domain_uuid'],
                (int) $data['cust_id'],
                $data['cust_username'],
                $data['cust_password'],
                isset($data['profile_id']) ? (int) $data['profile_id'] : null,
            );

            return response()->json([
                'messages' => ['success' => ['CloudPLAY customer connected successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('AppsController@pairCloudPlayCustomer error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function destroyCloudPlayCustomer(Request $request, CloudPlayApiService $cloudPlayApiService)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
        ]);

        try {
            $cloudPlayApiService->removeDomainCustomer($data['domain_uuid']);
            MobileAppUsers::where('domain_uuid', $data['domain_uuid'])->delete();

            return response()->json([
                'messages' => ['success' => ['CloudPLAY customer disconnected successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('AppsController@destroyCloudPlayCustomer error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }

    protected function mobileAppConnIdForStorage(?string $connection, MobileAppProviderInterface $provider): string
    {
        if (!$provider->requiresConnectionSelection()) {
            return '';
        }

        return (string) ($connection ?? '');
    }

    protected function getCloudPlayItemOptions(CloudPlayApiService $cloudPlayApiService)
    {
        $item_uuid = request('item_uuid');

        $model = $this->model
            ->select('domain_uuid', 'domain_name', 'domain_description')
            ->where($this->model->getKeyName(), $item_uuid)
            ->first();

        if (!$model) {
            throw new \Exception('Failed to fetch item details. Item not found');
        }

        $credentials = $cloudPlayApiService->getCustomerCredentials($model->domain_uuid);
        $custId = $cloudPlayApiService->getCustomerId($model->domain_uuid);
        $model->org_id = $custId;
        $model->cloudplay_status = !empty($custId) && $credentials['username'] !== '' ? 'true' : 'false';
        $model->cloudplay_cust_username = $credentials['username'];

        return [
            'navigation' => [
                [
                    'name' => 'Customer',
                    'icon' => 'BuildingOfficeIcon',
                    'slug' => 'customer',
                ],
            ],
            'model' => $model,
            'settings' => [
                'suggested_cust_username' => strtolower(preg_replace('/[^a-z0-9]+/i', '', $model->domain_name ?? '')),
                'suggested_cust_cmp_name' => $model->domain_description ?: $model->domain_name,
            ],
            'permissions' => $this->getUserPermissions(),
            'routes' => [
                'create_customer' => route('apps.cloudplay.customer.create'),
                'pair_customer' => route('apps.cloudplay.customer.pair'),
                'destroy_customer' => route('apps.cloudplay.customer.destroy'),
            ],
        ];
    }

    protected function prepareMobileAppCredentialsEmail(
        array $user,
        Extensions $extension,
        ?string $domainUuid = null,
        int $status = 1
    ): array {
        $user['name'] = $user['name'] ?? $extension->effective_caller_id_name ?? '';
        $user['extension'] = $user['extension'] ?? $extension->extension ?? '';
        $domainUuid = $user['domain_uuid'] ?? $domainUuid ?? $extension->domain_uuid ?? session('domain_uuid');
        $user['domain_uuid'] = $domainUuid;
        $user['status'] = $status;

        $hidePassInEmail = get_domain_setting('dont_send_user_credentials', $domainUuid);
        if ($hidePassInEmail === null) {
            $hidePassInEmail = 'false';
        }

        if ($status === 1 && $hidePassInEmail === 'false' && empty($user['qrCodeUrl'])) {
            $user['qrCodeUrl'] = $this->buildMobileAppQrCodeUrl($user, $domainUuid);
        }

        return $user;
    }

    protected function buildMobileAppQrCodeUrl(array $user, ?string $domainUuid = null): ?string
    {
        $payload = $this->resolveMobileAppQrPayload($user, $domainUuid);
        if ($payload === '') {
            return null;
        }

        try {
            return URL::temporarySignedRoute(
                'appsMobileAppQr',
                now()->addDays(30),
                ['payload' => Crypt::encryptString($payload)]
            );
        } catch (\Throwable $e) {
            logger('AppsController@buildMobileAppQrCodeUrl error: ' . $e->getMessage());

            return null;
        }
    }

    protected function resolveMobileAppQrPayload(array $user, ?string $domainUuid = null): string
    {
        $domainUuid = $domainUuid ?? $user['domain_uuid'] ?? session('domain_uuid');

        if (get_mobile_app_provider() === 'cloudplay') {
            $userId = (int) ($user['id'] ?? 0);

            if ($domainUuid && $userId > 0) {
                try {
                    $qrCode = app(CloudPlayApiService::class)->getQrCode($domainUuid, $userId);

                    if (!empty($qrCode)) {
                        return $qrCode;
                    }
                } catch (\Throwable $e) {
                    logger('AppsController@resolveMobileAppQrPayload getQrCode failed: ' . $e->getMessage());
                }
            }
        }

        return json_encode([
            'domain' => $user['domain'] ?? '',
            'username' => $user['username'] ?? '',
            'password' => $user['password'] ?? '',
        ]);
    }

    protected function buildMobileAppQrCode($provider, array $user, $hidePassInEmail, int $status): ?string
    {
        if ($hidePassInEmail != 'false' || $status != 1) {
            return null;
        }

        $payload = $this->resolveMobileAppQrPayload($user);
        if ($payload === '') {
            return null;
        }

        $qrcode = QrCode::format('png')->size(180)->margin(1)->generate($payload);

        return base64_encode($qrcode);
    }

    protected function dispatchAppCredentials(array $user, Extensions $extension, ?string $domainUuid = null, int $status = 1): void
    {
        SendAppCredentials::dispatch(
            $this->prepareMobileAppCredentialsEmail($user, $extension, $domainUuid, $status)
        )->onQueue('emails');
    }
}
