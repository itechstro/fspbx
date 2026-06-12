<?php

namespace App\Http\Controllers;

use App\Models\ContactSyncConnection;
use App\Services\Contacts\ContactExternalSyncService;
use App\Services\Contacts\ContactSyncCredentialService;
use App\Services\Contacts\GoogleContactsSyncService;
use App\Services\Contacts\MicrosoftContactsSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ContactSyncController extends Controller
{
    public function __construct(
        private ContactSyncCredentialService $credentials,
        private GoogleContactsSyncService $googleSync,
        private MicrosoftContactsSyncService $microsoftSync,
        private ContactExternalSyncService $externalSync,
    ) {}

    public function status(): JsonResponse
    {
        if (! userCheckPermission('contact_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $domainUuid = session('domain_uuid');

        return response()->json([
            'providers' => [
                'google' => $this->providerStatus(ContactSyncConnection::PROVIDER_GOOGLE, $domainUuid),
                'microsoft' => $this->providerStatus(ContactSyncConnection::PROVIDER_MICROSOFT, $domainUuid),
            ],
            'permissions' => [
                'connect' => userCheckPermission('contact_sync_connect'),
                'sync' => userCheckPermission('contact_sync_run'),
            ],
        ]);
    }

    public function connectGoogle(): RedirectResponse
    {
        $this->authorizeConnect(ContactSyncConnection::PROVIDER_GOOGLE);

        $domainUuid = session('domain_uuid');
        $state = (string) Str::uuid();
        Cache::put($this->oauthCacheKey($state), [
            'provider' => ContactSyncConnection::PROVIDER_GOOGLE,
            'domain_uuid' => $domainUuid,
            'user_uuid' => session('user_uuid'),
        ], now()->addMinutes(15));

        $query = http_build_query([
            'client_id' => $this->credentials->googleClientId($domainUuid),
            'redirect_uri' => route('contacts.sync.google.callback'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'openid',
                'email',
                'https://www.googleapis.com/auth/contacts.readonly',
            ]),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        return $this->handleCallback($request, ContactSyncConnection::PROVIDER_GOOGLE, function (string $domainUuid, string $userUuid, string $code) {
            return $this->googleSync->exchangeAuthorizationCode(
                $domainUuid,
                $userUuid,
                $code,
                route('contacts.sync.google.callback'),
            );
        });
    }

    public function connectMicrosoft(): RedirectResponse
    {
        $this->authorizeConnect(ContactSyncConnection::PROVIDER_MICROSOFT);

        $domainUuid = session('domain_uuid');
        $tenant = $this->credentials->microsoftTenantId($domainUuid);
        $state = (string) Str::uuid();
        Cache::put($this->oauthCacheKey($state), [
            'provider' => ContactSyncConnection::PROVIDER_MICROSOFT,
            'domain_uuid' => $domainUuid,
            'user_uuid' => session('user_uuid'),
        ], now()->addMinutes(15));

        $query = http_build_query([
            'client_id' => $this->credentials->microsoftClientId($domainUuid),
            'response_type' => 'code',
            'redirect_uri' => route('contacts.sync.microsoft.callback'),
            'response_mode' => 'query',
            'scope' => 'offline_access Contacts.Read User.Read',
            'state' => $state,
        ]);

        return redirect("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$query}");
    }

    public function microsoftCallback(Request $request): RedirectResponse
    {
        return $this->handleCallback($request, ContactSyncConnection::PROVIDER_MICROSOFT, function (string $domainUuid, string $userUuid, string $code) {
            return $this->microsoftSync->exchangeAuthorizationCode(
                $domainUuid,
                $userUuid,
                $code,
                route('contacts.sync.microsoft.callback'),
            );
        });
    }

    public function disconnect(Request $request, string $provider): JsonResponse
    {
        if (! userCheckPermission('contact_sync_connect')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        ContactSyncConnection::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('provider', $provider)
            ->delete();

        return response()->json([
            'messages' => ['success' => ['Disconnected successfully.']],
        ]);
    }

    public function syncNow(Request $request, string $provider): JsonResponse
    {
        if (! userCheckPermission('contact_sync_run')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $connection = ContactSyncConnection::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('provider', $provider)
            ->first();

        if (! $connection) {
            return response()->json(['messages' => ['error' => ['No connection found for this provider.']]], 422);
        }

        try {
            $stats = $this->externalSync->syncConnection($connection);

            return response()->json([
                'messages' => ['success' => [
                    sprintf(
                        'Sync complete. Created %d, updated %d, skipped %d.',
                        $stats['created'],
                        $stats['updated'],
                        $stats['skipped'],
                    ),
                ]],
                'stats' => $stats,
                'providers' => [
                    $provider => $this->providerStatus($provider, session('domain_uuid')),
                ],
            ]);
        } catch (Throwable $e) {
            logger('ContactSyncController@syncNow error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            $connection->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => $e->getMessage(),
                'update_date' => now(),
                'update_user' => session('user_uuid'),
            ]);

            return response()->json([
                'messages' => ['error' => ['Contact sync failed.']],
            ], 500);
        }
    }

    public function toggle(Request $request, string $provider): JsonResponse
    {
        if (! userCheckPermission('contact_sync_connect')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $connection = ContactSyncConnection::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('provider', $provider)
            ->first();

        if (! $connection) {
            return response()->json(['messages' => ['error' => ['No connection found for this provider.']]], 422);
        }

        $connection->update([
            'sync_enabled' => $request->boolean('enabled') ? 'true' : 'false',
            'update_date' => now(),
            'update_user' => session('user_uuid'),
        ]);

        return response()->json([
            'messages' => ['success' => ['Sync setting updated.']],
            'providers' => [
                $provider => $this->providerStatus($provider, session('domain_uuid')),
            ],
        ]);
    }

    private function handleCallback(Request $request, string $provider, callable $exchange): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('contacts.index', ['sync' => 1])
                ->with('error', 'Authorization was denied or cancelled.');
        }

        $state = (string) $request->query('state', '');
        $context = Cache::pull($this->oauthCacheKey($state));

        if (! is_array($context) || ($context['provider'] ?? null) !== $provider) {
            return redirect()->route('contacts.index', ['sync' => 1])
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        session([
            'domain_uuid' => $context['domain_uuid'],
            'user_uuid' => $context['user_uuid'],
        ]);

        try {
            $exchange($context['domain_uuid'], $context['user_uuid'], (string) $request->query('code', ''));

            return redirect()->route('contacts.index', ['sync' => 1])
                ->with('message', ucfirst($provider) . ' account connected. Run sync to import contacts.');
        } catch (Throwable $e) {
            logger('ContactSyncController callback error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return redirect()->route('contacts.index', ['sync' => 1])
                ->with('error', 'Failed to connect ' . $provider . ' account.');
        }
    }

    private function authorizeConnect(string $provider): void
    {
        if (! userCheckPermission('contact_sync_connect')) {
            abort(403);
        }

        $domainUuid = session('domain_uuid');

        if ($provider === ContactSyncConnection::PROVIDER_GOOGLE && ! $this->credentials->googleConfigured($domainUuid)) {
            abort(422, 'Google OAuth credentials are not configured.');
        }

        if ($provider === ContactSyncConnection::PROVIDER_MICROSOFT && ! $this->credentials->microsoftConfigured($domainUuid)) {
            abort(422, 'Microsoft OAuth credentials are not configured.');
        }
    }

    private function providerStatus(string $provider, string $domainUuid): array
    {
        $connection = ContactSyncConnection::query()
            ->where('domain_uuid', $domainUuid)
            ->where('provider', $provider)
            ->first();

        return [
            'configured' => $provider === ContactSyncConnection::PROVIDER_GOOGLE
                ? $this->credentials->googleConfigured($domainUuid)
                : $this->credentials->microsoftConfigured($domainUuid),
            'connected' => (bool) $connection,
            'account_email' => $connection?->account_email,
            'sync_enabled' => $connection?->isSyncEnabled() ?? false,
            'last_sync_at' => optional($connection?->last_sync_at)?->toIso8601String(),
            'last_sync_status' => $connection?->last_sync_status,
            'last_sync_message' => $connection?->last_sync_message,
        ];
    }

    private function oauthCacheKey(string $state): string
    {
        return 'contact_sync_oauth:' . $state;
    }
}
