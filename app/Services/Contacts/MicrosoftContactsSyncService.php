<?php

namespace App\Services\Contacts;

use App\Models\ContactSyncConnection;
use App\Models\VContact;
use App\Services\ContactService;
use Illuminate\Support\Facades\Http;

class MicrosoftContactsSyncService
{
    public function __construct(
        private ContactOAuthTokenService $tokenService,
        private ContactSyncCredentialService $credentials,
        private ContactSyncMetadataService $metadataService,
        private ExternalContactMapper $mapper,
        private ContactService $contactService,
    ) {}

    public function exchangeAuthorizationCode(string $domainUuid, string $userUuid, string $code, string $redirectUri): ContactSyncConnection
    {
        $tenant = $this->credentials->microsoftTenantId($domainUuid);
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id' => $this->credentials->microsoftClientId($domainUuid),
            'client_secret' => $this->credentials->microsoftClientSecret($domainUuid),
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'scope' => 'offline_access Contacts.Read User.Read',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Microsoft authorization failed: ' . $response->body());
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');
        $refreshToken = (string) ($payload['refresh_token'] ?? '');

        if ($accessToken === '' || $refreshToken === '') {
            throw new \RuntimeException('Microsoft authorization did not return the required tokens.');
        }

        $profile = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me');
        $email = $profile->successful()
            ? (string) ($profile->json('mail') ?: $profile->json('userPrincipalName') ?: '')
            : '';

        $connection = ContactSyncConnection::firstOrNew([
            'domain_uuid' => $domainUuid,
            'provider' => ContactSyncConnection::PROVIDER_MICROSOFT,
        ]);

        if (! $connection->exists) {
            $connection->insert_date = now();
            $connection->insert_user = $userUuid;
        }

        $connection->fill([
            'account_email' => $email !== '' ? $email : null,
            'refresh_token' => $refreshToken,
            'access_token' => $accessToken,
            'token_expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 3500)),
            'scopes' => (string) ($payload['scope'] ?? ''),
            'sync_enabled' => 'true',
            'connected_by_user_uuid' => $userUuid,
            'update_date' => now(),
            'update_user' => $userUuid,
        ]);
        $connection->save();

        return $connection;
    }

    /**
     * @return array{created:int,updated:int,skipped:int}
     */
    public function syncConnection(ContactSyncConnection $connection): array
    {
        $accessToken = $this->tokenService->ensureAccessToken($connection);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $url = 'https://graph.microsoft.com/v1.0/me/contacts?$top=100&$select=id,givenName,surname,displayName,companyName,jobTitle,mobilePhone,businessPhones,homePhones,emailAddresses,personalNotes';

        while ($url) {
            $response = Http::withToken($accessToken)->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('Microsoft contacts fetch failed: ' . $response->body());
            }

            $payload = $response->json();

            foreach ($payload['value'] ?? [] as $contact) {
                $mapped = $this->mapContact($contact);

                if ($mapped === null) {
                    $stats['skipped']++;

                    continue;
                }

                $result = $this->upsertMappedContact($connection, $mapped);
                $stats[$result]++;
            }

            $url = $payload['@odata.nextLink'] ?? null;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>|null
     */
    private function mapContact(array $contact): ?array
    {
        $externalId = (string) ($contact['id'] ?? '');

        if ($externalId === '') {
            return null;
        }

        $phones = [];

        if (! empty($contact['mobilePhone'])) {
            $phones[] = [
                'phone_label' => 'mobile',
                'phone_number' => (string) $contact['mobilePhone'],
                'phone_primary' => true,
            ];
        }

        foreach ($contact['businessPhones'] ?? [] as $index => $number) {
            $phones[] = [
                'phone_label' => 'work',
                'phone_number' => (string) $number,
                'phone_primary' => empty($phones) && $index === 0,
            ];
        }

        foreach ($contact['homePhones'] ?? [] as $index => $number) {
            $phones[] = [
                'phone_label' => 'home',
                'phone_number' => (string) $number,
                'phone_primary' => empty($phones) && $index === 0,
            ];
        }

        $emails = collect($contact['emailAddresses'] ?? [])
            ->map(fn (array $row, int $index) => [
                'email_label' => strtolower((string) ($row['name'] ?? ($index === 0 ? 'work' : 'other'))),
                'email_address' => (string) ($row['address'] ?? ''),
                'email_primary' => $index === 0,
            ])
            ->all();

        $given = trim((string) ($contact['givenName'] ?? ''));
        $family = trim((string) ($contact['surname'] ?? ''));
        $display = trim((string) ($contact['displayName'] ?? ''));

        if ($given === '' && $family === '' && $display === '' && $phones === [] && $emails === []) {
            return null;
        }

        if ($given === '' && $family === '' && $display !== '') {
            $parts = preg_split('/\s+/', $display, 2) ?: [];
            $given = $parts[0] ?? '';
            $family = $parts[1] ?? '';
        }

        return [
            'external_id' => $externalId,
            'etag' => '',
            'updated_at' => now()->toIso8601String(),
            'contact_name_given' => $given,
            'contact_name_family' => $family,
            'contact_organization' => (string) ($contact['companyName'] ?? ''),
            'contact_title' => (string) ($contact['jobTitle'] ?? ''),
            'contact_note' => (string) ($contact['personalNotes'] ?? ''),
            'phones' => $phones,
            'emails' => $emails,
            'urls' => [],
            'addresses' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertMappedContact(ContactSyncConnection $connection, array $mapped): string
    {
        $existingUuid = $this->metadataService->findContactUuidByExternalId(
            $connection->domain_uuid,
            ContactSyncConnection::PROVIDER_MICROSOFT,
            $mapped['external_id'],
        );

        $payload = $this->mapper->toContactServicePayload($mapped);
        $contact = $existingUuid
            ? VContact::query()->where('domain_uuid', $connection->domain_uuid)->findOrFail($existingUuid)
            : null;

        $saved = $this->contactService->save($payload, $contact);
        $this->metadataService->upsertSyncMetadata(
            $saved,
            ContactSyncConnection::PROVIDER_MICROSOFT,
            $mapped['external_id'],
            $mapped['etag'] ?? null,
            $mapped['updated_at'] ?? null,
        );

        return $contact ? 'updated' : 'created';
    }
}
