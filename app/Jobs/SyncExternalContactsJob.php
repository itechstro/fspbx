<?php

namespace App\Jobs;

use App\Models\ContactSyncConnection;
use App\Services\Contacts\ContactExternalSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncExternalContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $connectionUuid = null,
    ) {}

    public function handle(ContactExternalSyncService $externalSync): void
    {
        $query = ContactSyncConnection::query()->where('sync_enabled', 'true');

        if ($this->connectionUuid) {
            $query->where('contact_sync_connection_uuid', $this->connectionUuid);
        }

        foreach ($query->get() as $connection) {
            try {
                $externalSync->syncConnection($connection);
            } catch (Throwable $e) {
                logger('SyncExternalContactsJob error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

                $connection->update([
                    'last_sync_at' => now(),
                    'last_sync_status' => 'error',
                    'last_sync_message' => $e->getMessage(),
                    'update_date' => now(),
                ]);
            }
        }
    }
}
