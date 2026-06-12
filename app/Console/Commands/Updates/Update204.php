<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Update204
{
    private const VERSION = '1.8.8.11';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_12_000001_add_phonebook_contact_uuid_columns.php',
            ]);

            echo trim((string) Artisan::output()) . "\n";

            $this->backfillPhonebookContactLinks();
            $this->backfillMessagesCrmContacts();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function backfillPhonebookContactLinks(): void
    {
        if (! Schema::hasColumn('v_users', 'phonebook_contact_uuid')) {
            return;
        }

        $usersUpdated = DB::table('v_users as u')
            ->whereNotNull('u.contact_uuid')
            ->whereNull('u.phonebook_contact_uuid')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('v_contacts as c')
                    ->whereColumn('c.contact_uuid', 'u.contact_uuid');
            })
            ->update([
                'phonebook_contact_uuid' => DB::raw('contact_uuid'),
            ]);

        echo "Backfilled phonebook_contact_uuid on {$usersUpdated} user(s).\n";

        if (! Schema::hasColumn('v_extensions', 'phonebook_contact_uuid')) {
            return;
        }

        $extensionsUpdated = DB::update("
            UPDATE v_extensions AS e
            SET phonebook_contact_uuid = u.phonebook_contact_uuid
            FROM v_users AS u
            WHERE e.domain_uuid = u.domain_uuid
              AND e.extension_uuid = u.extension_uuid
              AND u.phonebook_contact_uuid IS NOT NULL
              AND e.phonebook_contact_uuid IS NULL
        ");

        echo "Linked phonebook_contact_uuid on {$extensionsUpdated} extension(s).\n";

        $clearedContactUuid = DB::table('v_users as u')
            ->whereNotNull('u.contact_uuid')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('v_contacts as c')
                    ->whereColumn('c.contact_uuid', 'u.contact_uuid');
            })
            ->update(['contact_uuid' => null]);

        echo "Cleared legacy phonebook contact_uuid on {$clearedContactUuid} user(s).\n";
    }

    private function backfillMessagesCrmContacts(): void
    {
        if (! Schema::hasTable('contacts')) {
            echo "Messages CRM contacts table not found; skipping CRM backfill.\n";

            return;
        }

        $exitCode = Artisan::call('phonebook-contacts:backfill-messages-crm');

        echo trim((string) Artisan::output()) . "\n";

        if ($exitCode !== 0) {
            throw new \RuntimeException('phonebook-contacts:backfill-messages-crm failed.');
        }
    }
}
