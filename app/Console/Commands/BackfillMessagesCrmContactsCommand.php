<?php

namespace App\Console\Commands;

use App\Services\Contacts\MessagesCrmContactBackfillService;
use Illuminate\Console\Command;

class BackfillMessagesCrmContactsCommand extends Command
{
    protected $signature = 'phonebook-contacts:backfill-messages-crm
        {--domain= : Only backfill contacts for this domain UUID}
        {--domain_uuid= : Alias for --domain}
        {--dry-run : Show how many contacts would be created without writing}';

    protected $description = 'Create phonebook contacts from Messages CRM contacts that are not already linked';

    public function handle(MessagesCrmContactBackfillService $service): int
    {
        $domainUuid = $this->option('domain') ?: $this->option('domain_uuid');
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Dry run: no phonebook contacts will be created.'
            : 'Backfilling phonebook contacts from Messages CRM contacts.'
        );

        $result = $service->backfill($domainUuid, $dryRun);

        $this->line("Created: {$result['created']}");
        $this->line("Skipped (already linked): {$result['skipped']}");

        if ($result['errors'] > 0) {
            $this->warn("Errors: {$result['errors']}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
