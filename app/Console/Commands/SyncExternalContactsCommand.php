<?php

namespace App\Console\Commands;

use App\Jobs\SyncExternalContactsJob;
use Illuminate\Console\Command;

class SyncExternalContactsCommand extends Command
{
    protected $signature = 'contacts:sync-external {--connection= : Optional contact_sync_connection_uuid}';

    protected $description = 'Sync phonebook contacts from connected Google and Microsoft accounts';

    public function handle(): int
    {
        SyncExternalContactsJob::dispatch($this->option('connection'));

        $this->info('External contact sync job dispatched.');

        return self::SUCCESS;
    }
}
