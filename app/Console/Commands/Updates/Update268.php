<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\FusionCache;
use App\Services\DialplanService;
use Throwable;

class Update268
{
    private const VERSION = '1.9.3.9';

    private const SETTING_DESCRIPTION = 'Enable the Recorder module and provision srs_recorder dialplans and the recorder conference profile.';

    public function apply(): bool
    {
        try {
            $removed = 0;

            Dialplans::query()
                ->where('dialplan_context', 'public')
                ->where('dialplan_name', 'like', 'recorder_catch_%')
                ->each(function (Dialplans $dialplan) use (&$removed) {
                    DialplanDetails::query()
                        ->where('dialplan_uuid', $dialplan->dialplan_uuid)
                        ->delete();
                    $dialplan->delete();
                    $removed++;
                });

            DefaultSettings::query()
                ->where('default_setting_category', 'xml_cdr')
                ->where('default_setting_subcategory', 'enable_recorder')
                ->update([
                    'default_setting_description' => self::SETTING_DESCRIPTION,
                ]);

            FusionCache::clear('dialplan:*');
            FusionCache::clear('dialplan:public');
            app(DialplanService::class)->clearDialplanCache('public');

            echo "Removed {$removed} legacy recorder_catch dialplan(s).\n";
            echo 'Updated xml_cdr.enable_recorder default setting description.'."\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
