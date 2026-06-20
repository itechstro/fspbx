<?php

namespace App\Console\Commands\Updates;

use App\Models\Domain;
use App\Models\DomainSettings;
use Throwable;

class Update252
{
    private const VERSION = '1.9.0.10';

    public function apply(): bool
    {
        try {
            $ibratroDomainUuid = Domain::query()
                ->where('domain_name', 'ibratro.talk.cloudplay.cloud')
                ->value('domain_uuid');

            if (is_string($ibratroDomainUuid) && $ibratroDomainUuid !== '') {
                DomainSettings::query()->updateOrCreate(
                    [
                        'domain_uuid' => $ibratroDomainUuid,
                        'domain_setting_category' => 'mobile_apps',
                        'domain_setting_subcategory' => 'cloudplay_qr_format',
                    ],
                    [
                        'domain_setting_name' => 'text',
                        'domain_setting_value' => 'portal',
                        'domain_setting_enabled' => 'true',
                        'domain_setting_description' => 'Portal get-qr-code token (same format as cpdevel).',
                    ],
                );

                echo "Set cloudplay_qr_format=portal for ibratro.talk.cloudplay.cloud.\n";
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
