<?php

namespace App\Services\Contacts;

use App\Models\ContactSyncConnection;
use App\Models\VContact;
use App\Services\ContactService;
use Illuminate\Support\Facades\Http;

class GoogleContactsSyncService
{
    public function __construct(
        private ContactOAuthTokenService $tokenService,
        private ContactSyncMetadataService $metadataService,
        private ExternalContactMapper $mapper,
        private ContactService $contactService,
    ) {}

    public function exchangeAuthorizationCode(string $domainUuid, string $userUuid, string $code, string $redirectUri): ContactSyncConnection
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => app(ContactSyncCredentialService::class)->googleClientId($domainUuid),
            'client_secret' => app(ContactSyncCredentialService::class)->googleClientSecret($domainUuid),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Google authorization failed: ' . $response->body());
        }

        $payload = $response->json();
        $accessToken = (string) ($payload['access_token'] ?? '');
        $refreshToken = (string) ($payload['refresh_token'] ?? '');

        if ($accessToken === '' || $refreshToken === '') {
            throw new \RuntimeException('Google authorization did not return the required tokens.');
        }

        $profile = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v2/userinfo');
        $email = $profile->successful() ? (string) ($profile->json('email') ?? '') : '';

        $connection = ContactSyncConnection::firstOrNew([
            'domain_uuid' => $domainUuid,
            'provider' => ContactSyncConnection::PROVIDER_GOOGLE,
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
        $pageToken = null;

        do {
            $response = Http::withToken($accessToken)->get('https://people.googleapis.com/v1/people/me/connections', [
                'pageSize' => 500,
                'pageToken' => $pageToken,
                'personFields' => 'names,emailAddresses,phoneNumbers,organizations,addresses,urls,biographies,metadata',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Google contacts fetch failed: ' . $response->body());
            }

            $payload = $response->json();
            foreach ($payload['connections'] ?? [] as $person) {
                $mapped = $this->mapPerson($person);

                if ($mapped === null) {
                    $stats['skipped']++;

                    continue;
                }

                $result = $this->upsertMappedContact($connection, $mapped);
                $stats[$result]++;
            }

            $pageToken = $payload['nextPageToken'] ?? null;
        } while ($pageToken);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>|null
     */
    private function mapPerson(array $person): ?array
    {
        $externalId = (string) ($person['resourceName'] ?? '');

        if ($externalId === '') {
            return null;
        }

        $name = $person['names'][0] ?? [];
        $organization = $person['organizations'][0] ?? [];

        $phones = collect($person['phoneNumbers'] ?? [])
            ->map(fn (array $row, int $index) => [
                'phone_label' => strtolower((string) ($row['type'] ?? ($index === 0 ? 'work' : 'other'))),
                'phone_number' => preg_replace('/\D+/', '', (string) ($row['value'] ?? '')) ?: (string) ($row['value'] ?? ''),
                'phone_primary' => (bool) ($row['metadata']['primary'] ?? $index === 0),
            ])
            ->all();

        $emails = collect($person['emailAddresses'] ?? [])
            ->map(fn (array $row, int $index) => [
                'email_label' => strtolower((string) ($row['type'] ?? ($index === 0 ? 'work' : 'other'))),
                'email_address' => (string) ($row['value'] ?? ''),
                'email_primary' => (bool) ($row['metadata']['primary'] ?? $index === 0),
            ])
            ->all();

        if ($phones === [] && $emails === [] && trim((string) ($name['displayName'] ?? '')) === '') {
            return null;
        }

        return [
            'external_id' => $externalId,
            'etag' => (string) ($person['etag'] ?? ''),
            'updated_at' => (string) ($person['metadata']['sources'][0]['updateTime'] ?? now()->toIso8601String()),
            'contact_name_given' => (string) ($name['givenName'] ?? ''),
            'contact_name_family' => (string) ($name['familyName'] ?? ''),
            'contact_organization' => (string) ($organization['name'] ?? ''),
            'contact_title' => (string) ($organization['title'] ?? ''),
            'contact_note' => (string) ($person['biographies'][0]['value'] ?? ''),
            'phones' => $phones,
            'emails' => $emails,
            'urls' => collect($person['urls'] ?? [])
                ->map(fn (array $row, int $index) => [
                    'url_label' => strtolower((string) ($row['type'] ?? ($index === 0 ? 'work' : 'other'))),
                    'url_address' => (string) ($row['value'] ?? ''),
                    'url_primary' => $index === 0,
                ])
                ->all(),
            'addresses' => collect($person['addresses'] ?? [])
                ->map(fn (array $row, int $index) => [
                    'address_label' => strtolower((string) ($row['type'] ?? ($index === 0 ? 'work' : 'other'))),
                    'address_street' => trim(((string) ($row['streetAddress'] ?? '')) . ' ' . ((string) ($row['extendedAddress'] ?? ''))),
                    'address_locality' => (string) ($row['city'] ?? ''),
                    'address_region' => (string) ($row['region'] ?? ''),
                    'address_postal_code' => (string) ($row['postalCode'] ?? ''),
                    'address_country' => (string) ($row['country'] ?? ''),
                    'address_primary' => $index === 0,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function upsertMappedContact(ContactSyncConnection $connection, array $mapped): string
    {
        $existingUuid = $this->metadataService->findContactUuidByExternalId(
            $connection->domain_uuid,
            ContactSyncConnection::PROVIDER_GOOGLE,
            $mapped['external_id'],
        );

        $payload = $this->mapper->toContactServicePayload($mapped);
        $contact = $existingUuid
            ? VContact::query()->where('domain_uuid', $connection->domain_uuid)->findOrFail($existingUuid)
            : null;

        $saved = $this->contactService->save($payload, $contact);
        $this->metadataService->upsertSyncMetadata(
            $saved,
            ContactSyncConnection::PROVIDER_GOOGLE,
            $mapped['external_id'],
            $mapped['etag'] ?? null,
            $mapped['updated_at'] ?? null,
        );

        return $contact ? 'updated' : 'created';
    }
}
