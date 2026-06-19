<?php

namespace App\Services;

use App\Contracts\MobileAppProviderInterface;
use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\Extensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudPlayApiService implements MobileAppProviderInterface
{
    protected int $timeout = 30;

    protected ?string $enterpriseDirectoryCacheDomain = null;

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $enterpriseDirectoryCache = null;

    public function getProviderKey(): string
    {
        return 'cloudplay';
    }

    public function getApiUrl(): string
    {
        $value = DefaultSettings::where([
            ['default_setting_category', '=', 'mobile_apps'],
            ['default_setting_subcategory', '=', 'cloudplay_api_url'],
            ['default_setting_enabled', '=', 'true'],
        ])->value('default_setting_value');

        if (!empty($value)) {
            return rtrim($value, '/');
        }

        return rtrim((string) config('cloudplay.api_url', ''), '/');
    }

    public function getAdminUsername(): string
    {
        $value = DefaultSettings::where([
            ['default_setting_category', '=', 'mobile_apps'],
            ['default_setting_subcategory', '=', 'cloudplay_admin_username'],
            ['default_setting_enabled', '=', 'true'],
        ])->value('default_setting_value');

        return $value ?: (string) config('cloudplay.admin_username', '');
    }

    public function getAdminPassword(): string
    {
        $value = DefaultSettings::where([
            ['default_setting_category', '=', 'mobile_apps'],
            ['default_setting_subcategory', '=', 'cloudplay_admin_password'],
            ['default_setting_enabled', '=', 'true'],
        ])->value('default_setting_value');

        if (!empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                return $value;
            }
        }

        return (string) config('cloudplay.admin_password', '');
    }

    public function getConnections(string $orgId): Collection
    {
        return collect();
    }

    public function supportsContactOnlyUsers(): bool
    {
        return false;
    }

    public function requiresConnectionSelection(): bool
    {
        return false;
    }

    public function getCustomerCredentials(string $domainUuid): array
    {
        $settings = DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'app shell')
            ->whereIn('domain_setting_subcategory', ['cloudplay_cust_username', 'cloudplay_cust_password'])
            ->where('domain_setting_enabled', true)
            ->pluck('domain_setting_value', 'domain_setting_subcategory');

        $password = $settings->get('cloudplay_cust_password', '');
        if ($password !== '') {
            try {
                $password = Crypt::decryptString($password);
            } catch (\Throwable) {
                // Keep stored value as-is when it is not encrypted.
            }
        }

        return [
            'username' => (string) $settings->get('cloudplay_cust_username', ''),
            'password' => (string) $password,
        ];
    }

    public function isConfiguredForDomain(string $domainUuid): bool
    {
        $credentials = $this->getCustomerCredentials($domainUuid);

        return $credentials['username'] !== '' && $credentials['password'] !== '';
    }

    public function getCustomerId(string $domainUuid): ?string
    {
        return DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->where('domain_setting_enabled', true)
            ->value('domain_setting_value');
    }

    protected function ensureApiUrl(): string
    {
        $url = $this->getApiUrl();
        if ($url === '') {
            throw new \Exception('CloudPLAY API URL is missing.');
        }

        return $url;
    }

    protected function request(string $method, string $path, array $payload = [], ?string $token = null, ?string $baseUrl = null): array
    {
        $request = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->baseUrl($baseUrl ?? $this->ensureApiUrl());

        if ($token) {
            $request = $request->withToken($token);
        }

        $response = $request->{$method}($path, $payload);

        if ($response->failed()) {
            $message = $response->json('message')
                ?? $response->json('error')
                ?? $response->body()
                ?? 'CloudPLAY API request failed.';

            throw new \Exception($this->formatApiErrorMessage(is_string($message) ? $message : json_encode($message)));
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new \Exception('CloudPLAY API returned an invalid response.');
        }

        if (($body['status'] ?? null) === 'ERROR') {
            throw new \Exception($this->formatApiErrorMessage($body['message'] ?? 'CloudPLAY API returned an error.'));
        }

        return $body;
    }

    protected function formatApiErrorMessage(string $message): string
    {
        if (stripos($message, 'invalid credentials') !== false) {
            return 'CloudPLAY login failed. Check the username and password.';
        }

        if (preg_match('/limit\s*(exceed|exceeded|reached)/i', $message)) {
            return 'CloudPLAY rejected the request because this tenant\'s CloudPLAY customer license is at its user limit. '
                . 'Increase the license in CloudPLAY admin or remove unused CloudPLAY users, then try again. '
                . 'FS PBX tenant limits on Domain License are separate from CloudPLAY licensing. '
                . 'CloudPLAY message: ' . $message;
        }

        return $message;
    }

    public function verifyCustomerCredentials(string $username, string $password, ?string $apiUrl = null): string
    {
        if ($username === '' || $password === '') {
            throw new \Exception('CloudPLAY customer username and password are required.');
        }

        $response = $this->request('post', '/customer/login', [
            'username' => $username,
            'password' => $password,
            'device_type' => 'web',
        ], null, $apiUrl ? rtrim($apiUrl, '/') : null);

        $token = $response['access_token'] ?? null;
        if (empty($token)) {
            throw new \Exception('CloudPLAY customer login did not return an access token.');
        }

        return $token;
    }

    public function verifyAdminCredentials(string $apiUrl, string $username, string $password): string
    {
        if ($apiUrl === '') {
            throw new \Exception('CloudPLAY API URL is missing.');
        }

        if ($username === '' || $password === '') {
            throw new \Exception('CloudPLAY admin username and password are required.');
        }

        $response = $this->request('post', '/admin/login', [
            'username' => $username,
            'password' => $password,
        ], null, rtrim($apiUrl, '/'));

        $token = $response['access_token'] ?? null;
        if (empty($token)) {
            throw new \Exception('CloudPLAY admin login did not return an access token.');
        }

        return $token;
    }

    protected function getAdminToken(): string
    {
        return Cache::remember('cloudplay_admin_token', now()->addMinutes(50), function () {
            return $this->verifyAdminCredentials(
                $this->getApiUrl(),
                $this->getAdminUsername(),
                $this->getAdminPassword(),
            );
        });
    }

    public function getCustomerToken(string $domainUuid): string
    {
        $cacheKey = 'cloudplay_customer_token:' . $domainUuid;

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($domainUuid) {
            $credentials = $this->getCustomerCredentials($domainUuid);

            return $this->verifyCustomerCredentials(
                $credentials['username'],
                $credentials['password'],
            );
        });
    }

    public function clearCustomerToken(string $domainUuid): void
    {
        Cache::forget('cloudplay_customer_token:' . $domainUuid);
    }

    public function getCustomers(): Collection
    {
        $response = $this->request('post', '/admin/customer/list', [
            'page_num' => 1,
            'data_per_page' => 500,
        ], $this->getAdminToken());

        $customers = $response['data'] ?? [];

        return collect(is_array($customers) ? $customers : []);
    }

    public function createCustomer(array $params): array
    {
        $payload = [
            'cust_firstname' => $params['cust_firstname'],
            'cust_lastname' => $params['cust_lastname'],
            'cust_cmp_name' => $params['cust_cmp_name'],
            'cust_username' => $params['cust_username'],
            'cust_password' => $params['cust_password'],
            'cust_contact_email' => $params['cust_contact_email'],
            'cust_contact_no' => $params['cust_contact_no'] ?? '0',
            'cust_auth_ips' => $params['cust_auth_ips'] ?? '0.0.0.0/0',
            'encrypt_password' => 'FALSE',
        ];

        $response = $this->request('post', '/admin/customer/create', $payload, $this->getAdminToken());
        $data = $response['data'] ?? [];

        if (!is_array($data) || empty($data['cust_id'])) {
            throw new \Exception('CloudPLAY customer creation did not return a customer ID.');
        }

        return $data;
    }

    public function getProfileId(string $domainUuid): ?int
    {
        $value = DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'cloudplay_profile_id')
            ->where('domain_setting_enabled', true)
            ->value('domain_setting_value');

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function getProfiles(string $domainUuid): Collection
    {
        $token = $this->getCustomerToken($domainUuid);
        $response = $this->request('post', '/customer/profile/list', [
            'page_num' => 1,
            'data_per_page' => 500,
        ], $token);

        $profiles = $response['data'] ?? [];

        return collect(is_array($profiles) ? $profiles : []);
    }

    public function getProfileConfigurations(string $domainUuid, int $profileId): array
    {
        $token = $this->getCustomerToken($domainUuid);
        $response = $this->request('post', '/customer/profile/get-configurations', [
            'profile_id' => $profileId,
        ], $token);

        return $response['data']['configurations'] ?? [];
    }

    protected function normalizeCloudPlayProtocol(?string $protocol): string
    {
        $protocol = strtolower(trim((string) $protocol));

        return match ($protocol) {
            'sip', 'udp' => 'udp',
            'sip-tcp', 'tcp' => 'tcp',
            'sips', 'tls' => 'tls',
            'wss', 'ws' => 'wss',
            default => in_array($protocol, ['udp', 'tcp', 'tls', 'wss'], true) ? $protocol : 'udp',
        };
    }

    protected function cloudPlayProtocolOrDefault(?string $value, string $default): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $this->normalizeCloudPlayProtocol($default);
        }

        return $this->normalizeCloudPlayProtocol($value);
    }

    protected function profileStringValue(mixed $value, string $fallback = ''): string
    {
        if ($value === null) {
            return $fallback;
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' ? $stringValue : $fallback;
    }

    public function buildMobileAppUsername(string $domainUuid, string $extension): string
    {
        return $this->getMobileAppUsernamePrefix($domainUuid) . $extension;
    }

    protected function getMobileAppUsernamePrefix(string $domainUuid): string
    {
        $credentials = $this->getCustomerCredentials($domainUuid);

        if ($credentials['username'] !== '') {
            return rtrim(strtolower($credentials['username']), '_') . '_';
        }

        $domainName = Domain::whereKey($domainUuid)->value('domain_name') ?? '';
        $label = explode('.', $domainName)[0] ?? $domainName;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '', $label));

        if ($slug === '') {
            $slug = substr(str_replace('-', '', $domainUuid), 0, 8);
        }

        if (!str_starts_with($slug, 'cp')) {
            $slug = 'cp' . $slug;
        }

        return $slug . '_';
    }

    public function saveDomainCustomer(string $domainUuid, int $custId, string $username, string $password, ?int $profileId = null): void
    {
        $this->upsertDomainSetting($domainUuid, 'org_id', (string) $custId);
        $this->upsertDomainSetting($domainUuid, 'cloudplay_cust_username', $username);
        $this->upsertDomainSetting($domainUuid, 'cloudplay_cust_password', Crypt::encryptString($password));

        if ($profileId !== null && $profileId > 0) {
            $this->upsertDomainSetting($domainUuid, 'cloudplay_profile_id', (string) $profileId);
        }

        $this->clearCustomerToken($domainUuid);
    }

    protected function upsertDomainSetting(string $domainUuid, string $subcategory, string $value): void
    {
        DomainSettings::updateOrCreate(
            [
                'domain_uuid' => $domainUuid,
                'domain_setting_category' => 'app shell',
                'domain_setting_subcategory' => $subcategory,
            ],
            [
                'domain_setting_name' => 'text',
                'domain_setting_value' => $value,
                'domain_setting_enabled' => true,
            ]
        );
    }

    public function removeDomainCustomer(string $domainUuid): void
    {
        DomainSettings::where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'app shell')
            ->whereIn('domain_setting_subcategory', ['org_id', 'cloudplay_cust_username', 'cloudplay_cust_password', 'cloudplay_profile_id'])
            ->delete();

        $this->clearCustomerToken($domainUuid);
    }

    protected function buildSipConfigurations(array $params): array
    {
        $domainUuid = $params['domain_uuid'] ?? session('domain_uuid');
        $profileId = $params['profile_id'] ?? $this->getProfileId($domainUuid);
        $profileSip = [];
        $profileSipIos = [];
        $profileSipAndroid = [];

        if (!empty($profileId)) {
            try {
                $configurations = $this->getProfileConfigurations($domainUuid, (int) $profileId);
                $profileSip = $configurations['Sip'] ?? [];
                $profileSipIos = $configurations['Sip_ios'] ?? [];
                $profileSipAndroid = $configurations['Sip_android'] ?? [];
            } catch (\Throwable) {
                // Fall back to domain defaults when profile configuration cannot be loaded.
            }
        }

        $protocolSetting = get_domain_setting('mobile_app_conn_protocol', $domainUuid) ?: 'udp';
        $proxy = get_domain_setting('mobile_app_proxy', $domainUuid) ?: '';
        $port = get_domain_setting('line_sip_port', $domainUuid) ?: '5060';
        $sipServer = $params['sip_server'] ?? Domain::whereKey($domainUuid)->value('domain_name') ?? session('domain_name');
        $authUsername = (string) ($params['authname'] ?? $params['username']);
        $authPassword = (string) ($params['password'] ?? '');

        $sip = [
            'sip_auth_username' => $authUsername,
            'sip_auth_password' => $authPassword,
            'sip_auth_sipServer' => $this->profileStringValue($profileSip['sip_auth_sipServer'] ?? null, (string) $sipServer),
            'sip_auth_sipProtocol' => $this->cloudPlayProtocolOrDefault($profileSip['sip_auth_sipProtocol'] ?? null, $protocolSetting),
            'sip_auth_sipPort' => $this->profileStringValue($profileSip['sip_auth_sipPort'] ?? null, (string) $port),
            'sip_auth_outboundProxyServer' => $this->profileStringValue($profileSip['sip_auth_outboundProxyServer'] ?? null, (string) $proxy),
            'sip_auth_outboundProxyPort' => $this->profileStringValue(
                $profileSip['sip_auth_outboundProxyPort'] ?? null,
                $proxy !== '' ? (string) $port : ''
            ),
            'forced_socket' => $this->profileStringValue($profileSip['forced_socket'] ?? null, ''),
            'sip_authid' => $authUsername,
            'sip_callerid' => (string) ($params['name'] ?? $params['username']),
            'sip_extension' => (string) ($params['ext'] ?? $params['username']),
            'sip_register_interval' => $this->profileStringValue($profileSip['sip_register_interval'] ?? null, '3600'),
            'sip_register_respectServerExpires' => $this->profileStringValue($profileSip['sip_register_respectServerExpires'] ?? null, 'TRUE'),
        ];

        return [
            'sip_configurations' => $sip,
            'sip_configurations_ios' => [
                'sip_auth_username_ios' => $authUsername,
                'sip_auth_password_ios' => $authPassword,
                'sip_auth_sipServer_ios' => $this->profileStringValue($profileSipIos['sip_auth_sipServer_ios'] ?? null, $sip['sip_auth_sipServer']),
                'sip_auth_sipProtocol_ios' => $this->cloudPlayProtocolOrDefault($profileSipIos['sip_auth_sipProtocol_ios'] ?? null, 'tls'),
                'sip_auth_sipPort_ios' => $this->profileStringValue($profileSipIos['sip_auth_sipPort_ios'] ?? null, $sip['sip_auth_sipPort']),
                'sip_auth_outboundProxyServer_ios' => $this->profileStringValue($profileSipIos['sip_auth_outboundProxyServer_ios'] ?? null, $sip['sip_auth_outboundProxyServer']),
                'sip_auth_outboundProxyPort_ios' => $this->profileStringValue($profileSipIos['sip_auth_outboundProxyPort_ios'] ?? null, $sip['sip_auth_outboundProxyPort']),
                'sip_authid_ios' => $authUsername,
            ],
            'sip_configurations_android' => [
                'sip_auth_username_android' => $authUsername,
                'sip_auth_password_android' => $authPassword,
                'sip_auth_sipServer_android' => $this->profileStringValue($profileSipAndroid['sip_auth_sipServer_android'] ?? null, $sip['sip_auth_sipServer']),
                'sip_auth_sipProtocol_android' => $this->cloudPlayProtocolOrDefault($profileSipAndroid['sip_auth_sipProtocol_android'] ?? null, 'tcp'),
                'sip_auth_sipPort_android' => $this->profileStringValue($profileSipAndroid['sip_auth_sipPort_android'] ?? null, $sip['sip_auth_sipPort']),
                'sip_auth_outboundProxyServer_android' => $this->profileStringValue($profileSipAndroid['sip_auth_outboundProxyServer_android'] ?? null, $sip['sip_auth_outboundProxyServer']),
                'sip_auth_outboundProxyPort_android' => $this->profileStringValue($profileSipAndroid['sip_auth_outboundProxyPort_android'] ?? null, $sip['sip_auth_outboundProxyPort']),
                'sip_authid_android' => $authUsername,
            ],
        ];
    }

    protected function generateAppPassword(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }

    public function buildMobileAppQrPayload(array $user): string
    {
        return json_encode([
            'domain' => (string) ($user['domain'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
            'password' => (string) ($user['password'] ?? ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function normalizeUserResponse(array $data, array $params, ?string $plainPassword = null): array
    {
        $domainUuid = $params['domain_uuid'] ?? session('domain_uuid');
        $credentials = $this->getCustomerCredentials($domainUuid);
        $extension = (string) ($params['ext'] ?? $params['username'] ?? '');

        return [
            'id' => (string) ($data['usr_id'] ?? $params['user_id'] ?? ''),
            'username' => $data['usr_username'] ?? $this->buildMobileAppUsername($domainUuid, $extension),
            'password' => $plainPassword ?? ($data['usr_password'] ?? $params['app_password'] ?? ''),
            'domain' => $credentials['username'],
            'status' => strtoupper((string) ($data['usr_status'] ?? 'Y')) === 'Y' ? 1 : -1,
            'email' => $data['usr_email'] ?? ($params['email'] ?? ''),
            'name' => $params['name'] ?? '',
            'extension' => $extension,
            'domain_uuid' => $domainUuid,
        ];
    }

    public function createUser(array $params): array
    {
        $domainUuid = $params['domain_uuid'] ?? session('domain_uuid');
        $token = $this->getCustomerToken($domainUuid);
        $appPassword = $params['app_password'] ?? $this->generateAppPassword();
        $profileId = $params['profile_id'] ?? $this->getProfileId($domainUuid);
        $extension = (string) ($params['ext'] ?? $params['username']);
        $mobileAppUsername = $this->buildMobileAppUsername($domainUuid, $extension);

        $payload = array_merge([
            'usr_username' => $mobileAppUsername,
            'usr_password' => $appPassword,
            'usr_email' => $params['email'] ?? '',
            'usr_country_phonecode' => $params['usr_country_phonecode'] ?? '',
            'usr_mobile_number' => $params['usr_mobile_number'] ?? '',
            'usr_account_name' => $params['name'] ?? $extension,
            'send_qr_code' => 'N',
            'encrypt_password' => 'FALSE',
        ], $this->buildSipConfigurations($params));

        if (!empty($profileId)) {
            $payload['profile_id'] = (int) $profileId;
        }

        if (($params['status'] ?? 1) != 1) {
            throw new \Exception('CloudPLAY Softphone does not support contact-only mobile app users.');
        }

        try {
            $response = $this->request('post', '/customer/user/create', $payload, $token);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'already exist') === false) {
                throw $e;
            }

            throw new \Exception(
                'This extension already exists in CloudPLAY as ' . $mobileAppUsername . '. '
                . 'Remove the orphaned user in CloudPLAY, then try again.'
            );
        }

        return $this->normalizeUserResponse($response['data'] ?? [], $params, $appPassword);
    }

    public function updateUser(array $params): array
    {
        $domainUuid = $params['domain_uuid'] ?? session('domain_uuid');
        $token = $this->getCustomerToken($domainUuid);
        $profileId = $params['profile_id'] ?? $this->getProfileId($domainUuid);

        // CloudPLAY requires these fields to be present; empty strings keep existing values.
        $payload = [
            'user_id' => (int) $params['user_id'],
            'usr_status' => (($params['status'] ?? 1) == 1) ? 'Y' : 'N',
            'usr_password' => '',
            'usr_email' => '',
            'usr_country_phonecode' => '',
            'usr_mobile_number' => '',
            'usr_account_name' => $params['name'] ?? '',
            'send_qr_code' => 'N',
            'encrypt_password' => 'FALSE',
        ];

        if (empty($profileId)) {
            throw new \Exception('CloudPLAY profile is not configured for this domain.');
        }

        $payload['profile_id'] = (int) $profileId;

        if (!empty($params['app_password'])) {
            $payload['usr_password'] = $params['app_password'];
        }

        if (isset($params['email']) && $params['email'] !== '') {
            $payload['usr_email'] = $params['email'];
        }

        $this->request('post', '/customer/user/update', $payload, $token);

        if (!empty($params['password']) || !empty($params['update_sip_configurations'])) {
            $this->request('post', '/customer/user/update-configurations', [
                'user_id' => (int) $params['user_id'],
                'configurations' => $this->buildSipConfigurations($params)['sip_configurations'],
                'send_push_notification' => $params['send_push_notification'] ?? 'false',
            ], $token);
        }

        return $this->normalizeUserResponse([
            'usr_id' => $params['user_id'],
            'usr_username' => $this->buildMobileAppUsername(
                $domainUuid,
                (string) ($params['ext'] ?? $params['username'] ?? '')
            ),
            'usr_status' => (($params['status'] ?? 1) == 1) ? 'Y' : 'N',
            'usr_email' => $params['email'] ?? '',
        ], $params, $payload['usr_password'] !== '' ? $payload['usr_password'] : null);
    }

    public function deleteUser(array $params): mixed
    {
        $domainUuid = $params['domain_uuid'] ?? session('domain_uuid');
        $token = $this->getCustomerToken($domainUuid);

        return $this->request('post', '/customer/user/delete', [
            'user_id' => (int) $params['user_id'],
        ], $token);
    }

    public function resetPassword(array $params): array
    {
        $domainUuid = $params['domain_uuid'] ?? session('domain_uuid');
        $newPassword = $this->generateAppPassword();

        return $this->updateUser(array_merge($params, [
            'password' => $newPassword,
            'app_password' => $newPassword,
            'status' => 1,
        ]));
    }

    public function deactivateUser(array $params): mixed
    {
        // CloudPLAY rejects an empty usr_password; rotate the app login password while suspending.
        return $this->updateUser(array_merge($params, [
            'status' => -1,
            'app_password' => $this->generateAppPassword(),
        ]));
    }

    public function getQrCode(string $domainUuid, int $userId): ?string
    {
        $token = $this->getCustomerToken($domainUuid);
        $response = $this->request('post', '/customer/user/get-qr-code', [
            'user_id' => $userId,
        ], $token);

        return $response['data']['qr_code'] ?? null;
    }

    public function clearEnterpriseDirectoryCache(): void
    {
        $this->enterpriseDirectoryCacheDomain = null;
        $this->enterpriseDirectoryCache = null;
    }

    public function enterpriseDirectoryEntryExists(string $domainUuid, int $edId): bool
    {
        if ($edId <= 0) {
            return false;
        }

        foreach ($this->listEnterpriseDirectory($domainUuid) as $entry) {
            if ((int) ($entry['ed_id'] ?? 0) === $edId) {
                return true;
            }
        }

        return false;
    }

    public function resolveEnterpriseDirectoryId(string $domainUuid, string $extension, ?int $storedEdId = null): int
    {
        $extension = trim($extension);
        $storedEdId = (int) ($storedEdId ?? 0);

        if ($extension !== '') {
            $byExtension = $this->findEnterpriseDirectoryIdByExtension($domainUuid, $extension);

            if ($byExtension > 0) {
                return $byExtension;
            }
        }

        if ($storedEdId <= 0) {
            return 0;
        }

        if (! $this->enterpriseDirectoryEntryExists($domainUuid, $storedEdId)) {
            return 0;
        }

        if ($extension !== '') {
            foreach ($this->listEnterpriseDirectory($domainUuid) as $entry) {
                if ((int) ($entry['ed_id'] ?? 0) !== $storedEdId) {
                    continue;
                }

                $entryExtension = (string) ($entry['ed_extension'] ?? $entry['ed_blf_prefix'] ?? '');

                if ($entryExtension !== '' && $entryExtension !== $extension) {
                    return 0;
                }

                break;
            }
        }

        return $storedEdId;
    }

    public function syncEnterpriseDirectory(string $domainUuid, array $params): int
    {
        $token = $this->getCustomerToken($domainUuid);
        $payload = $this->buildEnterpriseDirectoryPayload($domainUuid, $params);
        $extension = (string) ($params['extension'] ?? '');
        $storedEdId = isset($params['ed_id']) ? (int) $params['ed_id'] : 0;
        $edId = $this->resolveEnterpriseDirectoryId($domainUuid, $extension, $storedEdId);

        if ($edId > 0) {
            $payload['ed_id'] = (string) $edId;
            $response = $this->request('post', '/customer/enterprise/update', $payload, $token);
            $this->clearEnterpriseDirectoryCache();

            return (int) ($response['data']['ed_id'] ?? $edId);
        }

        $response = $this->request('post', '/customer/enterprise/create', $payload, $token);
        $createdId = (int) ($response['data']['ed_id'] ?? 0);

        if ($createdId <= 0) {
            throw new \Exception('CloudPLAY did not return an enterprise directory ID.');
        }

        $this->clearEnterpriseDirectoryCache();

        return $createdId;
    }

    public function deleteEnterpriseDirectory(string $domainUuid, int $edId): void
    {
        if ($edId <= 0) {
            return;
        }

        $token = $this->getCustomerToken($domainUuid);
        $this->request('post', '/customer/enterprise/delete', [
            'ed_id' => $edId,
        ], $token);
        $this->clearEnterpriseDirectoryCache();
    }

    public function listEnterpriseDirectory(string $domainUuid): array
    {
        if ($this->enterpriseDirectoryCacheDomain === $domainUuid && $this->enterpriseDirectoryCache !== null) {
            return $this->enterpriseDirectoryCache;
        }

        $token = $this->getCustomerToken($domainUuid);
        $entries = [];
        $page = 1;
        $perPage = 500;

        do {
            $response = $this->request('post', '/customer/enterprise/list', [
                'page_num' => $page,
                'data_per_page' => $perPage,
            ], $token);

            $batch = $response['data'] ?? [];
            if (!is_array($batch) || $batch === []) {
                break;
            }

            $entries = array_merge($entries, $batch);
            $page++;
        } while (count($batch) >= $perPage);

        $this->enterpriseDirectoryCacheDomain = $domainUuid;
        $this->enterpriseDirectoryCache = $entries;

        return $entries;
    }

    public function findEnterpriseDirectoryIdByExtension(string $domainUuid, string $extension): int
    {
        $ids = $this->findEnterpriseDirectoryIdsByExtension($domainUuid, $extension);

        if ($ids === []) {
            return 0;
        }

        foreach ($this->listEnterpriseDirectory($domainUuid) as $entry) {
            if ((int) ($entry['ed_id'] ?? 0) !== $ids[0]) {
                continue;
            }

            if (($entry['ed_status'] ?? 'Y') === 'Y') {
                return $ids[0];
            }
        }

        return $ids[0];
    }

    /**
     * @return array<int, int>
     */
    public function findEnterpriseDirectoryIdsByExtension(string $domainUuid, string $extension): array
    {
        $extension = trim($extension);

        if ($extension === '') {
            return [];
        }

        $ids = [];

        foreach ($this->listEnterpriseDirectory($domainUuid) as $entry) {
            $entryExtension = (string) ($entry['ed_extension'] ?? $entry['ed_blf_prefix'] ?? '');

            if ($entryExtension !== $extension) {
                continue;
            }

            $edId = (int) ($entry['ed_id'] ?? 0);

            if ($edId > 0) {
                $ids[] = $edId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, array{extension: string, ed_id: int}>
     */
    public function duplicateEnterpriseDirectoryEntries(string $domainUuid): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->listEnterpriseDirectory($domainUuid) as $entry) {
            $edId = (int) ($entry['ed_id'] ?? 0);
            $extension = (string) ($entry['ed_extension'] ?? $entry['ed_blf_prefix'] ?? '');

            if ($edId <= 0 || $extension === '') {
                continue;
            }

            if (! isset($seen[$extension])) {
                $seen[$extension] = $edId;

                continue;
            }

            $duplicates[] = [
                'extension' => $extension,
                'ed_id' => $edId,
            ];
        }

        return $duplicates;
    }

    protected function buildEnterpriseDirectoryPayload(string $domainUuid, array $params): array
    {
        [$firstName, $lastName] = $this->splitContactName($params['name'] ?? '');
        $extension = (string) ($params['extension'] ?? '');
        $profileId = $params['profile_id'] ?? $this->getProfileId($domainUuid);
        $callerId = preg_replace('/\D+/', '', (string) ($params['caller_id_number'] ?? ''));
        $businessPhone = $this->normalizePhoneForCloudPlay($params['business_phone'] ?? '', $domainUuid);

        if ($businessPhone === '' && ($params['active'] ?? true)) {
            $businessPhone = $callerId;
        }

        $company = Domain::where('domain_uuid', $domainUuid)->value('domain_description')
            ?? Domain::where('domain_uuid', $domainUuid)->value('domain_name')
            ?? '';

        return [
            'ed_first_name' => $firstName !== '' ? $firstName : ($extension !== '' ? $extension : 'Contact'),
            'ed_last_name' => $lastName,
            'ed_role' => '',
            'ed_directory' => 'Enterprise',
            'ed_title' => '',
            'ed_company' => $company,
            'ed_business_phone_number' => $businessPhone,
            'ed_other_number' => '',
            'ed_blf_prefix' => $extension,
            'ed_email_id' => (string) ($params['email'] ?? ''),
            'ed_mobile' => $this->normalizePhoneForCloudPlay($params['mobile'] ?? '', $domainUuid),
            'ed_landline' => '',
            'ed_extension' => $extension,
            'ed_profile_id' => $profileId ? (string) $profileId : '',
            'ed_status' => ($params['active'] ?? true) ? 'Y' : 'N',
        ];
    }

    protected function splitContactName(?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public function resolveExtensionMobileNumber(Extensions $extension): string
    {
        return app(\App\Services\Contacts\ContactUserLinkService::class)
            ->resolveMobileNumberForExtensionDirect($extension);
    }

    public function resolveExtensionBusinessPhoneNumber(Extensions $extension): string
    {
        return app(\App\Services\Contacts\ContactUserLinkService::class)
            ->resolveWorkNumberForExtensionDirect($extension);
    }

    protected function normalizePhoneForCloudPlay(?string $number, string $domainUuid): string
    {
        return formatContactPhoneE164($number, $domainUuid);
    }
}
