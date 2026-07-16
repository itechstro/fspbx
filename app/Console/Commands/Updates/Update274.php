<?php

namespace App\Console\Commands\Updates;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\FusionCache;
use App\Services\SrsRecorderDialplanService;
use Throwable;

class Update274
{
    private const VERSION = '1.9.3.15';

    public function apply(): bool
    {
        try {
            $this->dedupeSiprecExtensions();

            FusionCache::clear('directory:*');

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function dedupeSiprecExtensions(): void
    {
        $extension = SrsRecorderDialplanService::SIPREC_EXTENSION;

        $domainUuids = Extensions::query()
            ->select('domain_uuid')
            ->where('extension', $extension)
            ->groupBy('domain_uuid')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('domain_uuid');

        if ($domainUuids->isEmpty()) {
            echo "No duplicate {$extension} extensions found.\n";

            return;
        }

        $cleaned = 0;

        Domain::query()
            ->whereIn('domain_uuid', $domainUuids)
            ->orderBy('domain_name')
            ->each(function (Domain $domain) use ($extension, &$cleaned) {
                $rows = Extensions::query()
                    ->where('domain_uuid', $domain->domain_uuid)
                    ->where('extension', $extension)
                    ->orderBy('insert_date')
                    ->orderBy('extension_uuid')
                    ->get();

                $keep = $rows->first(fn (Extensions $row) => filled($row->password))
                    ?? $rows->first();

                if (! $keep) {
                    return;
                }

                $deleted = Extensions::query()
                    ->where('domain_uuid', $domain->domain_uuid)
                    ->where('extension', $extension)
                    ->where('extension_uuid', '!=', $keep->extension_uuid)
                    ->delete();

                echo "Removed {$deleted} duplicate {$extension} row(s) on {$domain->domain_name}; kept {$keep->extension_uuid}.\n";
                $cleaned++;
            });

        echo "Cleaned duplicate {$extension} extensions on {$cleaned} domain(s).\n";
    }
}
