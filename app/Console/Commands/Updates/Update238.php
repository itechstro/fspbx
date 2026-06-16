<?php

namespace App\Console\Commands\Updates;

use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Facades\File;
use Throwable;

class Update238
{
    private const VERSION = '1.8.8.45';

    private const DIALPLAN_SOURCE = '/var/www/fspbx/resources/dialplans/021_check-billing-suspension.xml';

    private const DIALPLAN_TARGET = '/var/www/fspbx/public/app/dialplans/resources/switch/conf/dialplan/021_check-billing-suspension.xml';

    public function apply(): bool
    {
        try {
            $this->deployBillingSuspensionDialplan();
            $this->patchBillingSuspensionDialplans();
            FusionCache::clear('dialplan.*');

            if (file_exists('/var/www/fspbx/public/core/upgrade/upgrade.php')) {
                shell_exec('cd /var/www/fspbx && /usr/bin/php /var/www/fspbx/public/core/upgrade/upgrade.php > /dev/null 2>&1');
                echo "Ran upgrade defaults to refresh dialplan imports.\n";
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function deployBillingSuspensionDialplan(): void
    {
        if (! file_exists(self::DIALPLAN_SOURCE)) {
            throw new \RuntimeException('Missing 021_check-billing-suspension.xml source file.');
        }

        File::ensureDirectoryExists(dirname(self::DIALPLAN_TARGET));
        File::copy(self::DIALPLAN_SOURCE, self::DIALPLAN_TARGET);
        echo "Deployed billing suspension dialplan XML.\n";
    }

    private function patchBillingSuspensionDialplans(): void
    {
        $dialplans = Dialplans::query()
            ->where('dialplan_xml', 'like', '%CHECK_BILLING_SUSPENSION%')
            ->where('dialplan_xml', 'like', '%911%')
            ->get(['dialplan_uuid', 'dialplan_xml']);

        $updated = 0;

        foreach ($dialplans as $dialplan) {
            $xml = (string) $dialplan->dialplan_xml;
            $updatedXml = preg_replace(
                '/expression="\^\(\?!933\\\\\.\?\$|911\\\\\.\?\$\)"/',
                'expression="^.+$"',
                $xml,
                1,
                $count,
            );

            if ($count < 1 || $updatedXml === $xml) {
                continue;
            }

            $dialplan->dialplan_xml = $updatedXml;
            $dialplan->update_date = now();
            $dialplan->save();
            $updated++;
        }

        echo "Patched {$updated} billing suspension dialplan row(s) to use domain emergency numbers.\n";
    }
}
