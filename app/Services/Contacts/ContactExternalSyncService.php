<?php

namespace App\Services\Contacts;

use App\Models\ContactSyncConnection;

class ContactExternalSyncService
{
    public function __construct(
        private GoogleContactsSyncService $googleSync,
        private MicrosoftContactsSyncService $microsoftSync,
    ) {}

    /**
     * @return array{created:int,updated:int,skipped:int}
     */
    public function syncConnection(ContactSyncConnection $connection): array
    {
        session([
            'domain_uuid' => $connection->domain_uuid,
            'user_uuid' => $connection->connected_by_user_uuid ?: session('user_uuid'),
        ]);

        $stats = match ($connection->provider) {
            ContactSyncConnection::PROVIDER_GOOGLE => $this->googleSync->syncConnection($connection),
            ContactSyncConnection::PROVIDER_MICROSOFT => $this->microsoftSync->syncConnection($connection),
            default => throw new \RuntimeException('Unsupported provider.'),
        };

        $connection->update([
            'last_sync_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => sprintf('Created %d, updated %d, skipped %d.', $stats['created'], $stats['updated'], $stats['skipped']),
            'update_date' => now(),
            'update_user' => session('user_uuid'),
        ]);

        return $stats;
    }
}
