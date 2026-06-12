<?php

namespace App\Services\Contacts;

use App\Models\ContactSyncConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ContactOAuthTokenService
{
    public function __construct(
        private ContactSyncCredentialService $credentials,
    ) {}

    public function ensureAccessToken(ContactSyncConnection $connection): string
    {
        if (
            $connection->access_token
            && $connection->token_expires_at
            && $connection->token_expires_at->isFuture()
        ) {
            return $connection->access_token;
        }

        if (! $connection->refresh_token) {
            throw new RuntimeException('No refresh token is stored for this connection.');
        }

        return match ($connection->provider) {
            ContactSyncConnection::PROVIDER_GOOGLE => $this->refreshGoogleToken($connection),
            ContactSyncConnection::PROVIDER_MICROSOFT => $this->refreshMicrosoftToken($connection),
            default => throw new RuntimeException('Unsupported contact sync provider.'),
        };
    }

    private function refreshGoogleToken(ContactSyncConnection $connection): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->credentials->googleClientId($connection->domain_uuid),
            'client_secret' => $this->credentials->googleClientSecret($connection->domain_uuid),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google token refresh failed: ' . $response->body());
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Google token refresh returned an empty access token.');
        }

        $connection->access_token = $accessToken;
        $connection->token_expires_at = now()->addSeconds((int) ($payload['expires_in'] ?? 3500));
        $connection->update_date = now();
        $connection->save();

        return $accessToken;
    }

    private function refreshMicrosoftToken(ContactSyncConnection $connection): string
    {
        $tenant = $this->credentials->microsoftTenantId($connection->domain_uuid);
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id' => $this->credentials->microsoftClientId($connection->domain_uuid),
            'client_secret' => $this->credentials->microsoftClientSecret($connection->domain_uuid),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
            'scope' => 'offline_access Contacts.Read User.Read',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Microsoft token refresh failed: ' . $response->body());
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Microsoft token refresh returned an empty access token.');
        }

        $connection->access_token = $accessToken;
        if (! empty($payload['refresh_token'])) {
            $connection->refresh_token = (string) $payload['refresh_token'];
        }
        $connection->token_expires_at = now()->addSeconds((int) ($payload['expires_in'] ?? 3500));
        $connection->update_date = now();
        $connection->save();

        return $accessToken;
    }
}
