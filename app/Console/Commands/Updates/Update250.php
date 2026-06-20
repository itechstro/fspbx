<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use Throwable;

class Update250
{
    private const VERSION = '1.9.0.8';

    public function apply(): bool
    {
        try {
            DefaultSettings::query()
                ->where('default_setting_category', 'mobile_apps')
                ->where('default_setting_subcategory', 'cloudplay_qr_format')
                ->update([
                    'default_setting_description' => 'CloudPLAY QR format: portal (get-qr-code token) or sessiontalk (JSON login + SIP).',
                ]);

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
                        'domain_setting_value' => 'sessiontalk',
                        'domain_setting_enabled' => 'true',
                        'domain_setting_description' => 'SessionTalk JSON QR for CP_Ibratro (profile 1576).',
                    ],
                );

                echo "Set cloudplay_qr_format=sessiontalk for ibratro.talk.cloudplay.cloud.\n";
            }

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
