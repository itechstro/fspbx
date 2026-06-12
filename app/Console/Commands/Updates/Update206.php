<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class Update206
{
    private const VERSION = '1.8.8.13';

    public function apply(): bool
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_06_13_000001_create_contact_sync_connections_table.php',
            ]);

            echo trim((string) Artisan::output()) . "\n";

            DB::transaction(function () {
                $this->ensureOAuthDefaultSettings();
            });

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function ensureOAuthDefaultSettings(): void
    {
        $settings = [
            [
                'subcategory' => 'google_oauth_client_id',
                'description' => 'Google OAuth client ID for Contacts sync.',
            ],
            [
                'subcategory' => 'google_oauth_client_secret',
                'description' => 'Google OAuth client secret for Contacts sync.',
            ],
            [
                'subcategory' => 'microsoft_oauth_client_id',
                'description' => 'Microsoft Entra application client ID for Contacts sync.',
            ],
            [
                'subcategory' => 'microsoft_oauth_client_secret',
                'description' => 'Microsoft Entra application client secret for Contacts sync.',
            ],
            [
                'subcategory' => 'microsoft_oauth_tenant_id',
                'description' => 'Microsoft Entra tenant ID. Use common for multi-tenant apps.',
                'value' => 'common',
            ],
        ];

        $created = 0;

        foreach ($settings as $setting) {
            $exists = DefaultSettings::query()
                ->where('default_setting_category', 'contact')
                ->where('default_setting_subcategory', $setting['subcategory'])
                ->exists();

            if ($exists) {
                continue;
            }

            DefaultSettings::create([
                'default_setting_uuid' => (string) Str::uuid(),
                'default_setting_category' => 'contact',
                'default_setting_subcategory' => $setting['subcategory'],
                'default_setting_name' => 'text',
                'default_setting_value' => $setting['value'] ?? '',
                'default_setting_order' => null,
                'default_setting_enabled' => true,
                'default_setting_description' => $setting['description'],
            ]);

            $created++;
        }

        echo "Seeded {$created} contact OAuth default setting(s).\n";
    }
}
