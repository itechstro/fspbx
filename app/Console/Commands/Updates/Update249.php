<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use Throwable;

class Update249
{
    private const VERSION = '1.9.0.7';

    public function apply(): bool
    {
        try {
            DefaultSettings::query()->updateOrCreate(
                [
                    'default_setting_category' => 'mobile_apps',
                    'default_setting_subcategory' => 'cloudplay_qr_format',
                ],
                [
                    'default_setting_name' => 'text',
                    'default_setting_value' => 'portal',
                    'default_setting_enabled' => 'true',
                    'default_setting_description' => 'CloudPLAY QR format: portal (get-qr-code token) or csc (login credentials).',
                ],
            );

            DefaultSettings::query()
                ->where('default_setting_category', 'mobile_apps')
                ->where('default_setting_subcategory', 'cloudplay_qr_format')
                ->update([
                    'default_setting_enabled' => 'true',
                    'default_setting_value' => 'portal',
                    'default_setting_description' => 'CloudPLAY QR format: portal (get-qr-code token) or csc (login credentials).',
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
                        'domain_setting_value' => 'csc',
                        'domain_setting_enabled' => 'true',
                        'domain_setting_description' => 'Use CSC QR codes for the CP_Ibratro CloudPLAY profile.',
                    ],
                );

                echo "Set cloudplay_qr_format=csc for ibratro.talk.cloudplay.cloud.\n";
            }

            echo "Re-enabled mobile_apps.cloudplay_qr_format default setting (portal).\n";
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }
}
