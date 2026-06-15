<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Throwable;

class Update228
{
    private const VERSION = '1.8.8.35';

    private const OLD_SUBCATEGORY = 'show_recorder_filter';

    private const NEW_SUBCATEGORY = 'enable_recorder';

    private const DESCRIPTION = 'Enable the Recorder module and provision srs_recorder, recorder_catch_<domain> dialplans, and the recorder conference profile.';

    public function apply(): bool
    {
        try {
            $defaultUpdated = DefaultSettings::query()
                ->where('default_setting_category', 'xml_cdr')
                ->where('default_setting_subcategory', self::OLD_SUBCATEGORY)
                ->update([
                    'default_setting_subcategory' => self::NEW_SUBCATEGORY,
                    'default_setting_description' => self::DESCRIPTION,
                ]);

            $domainUpdated = DomainSettings::query()
                ->where('domain_setting_category', 'xml_cdr')
                ->where('domain_setting_subcategory', self::OLD_SUBCATEGORY)
                ->update([
                    'domain_setting_subcategory' => self::NEW_SUBCATEGORY,
                ]);

            echo "Renamed xml_cdr." . self::OLD_SUBCATEGORY . ' to xml_cdr.' . self::NEW_SUBCATEGORY . ".\n";
            echo "Updated {$defaultUpdated} default setting row(s) and {$domainUpdated} domain setting row(s).\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
