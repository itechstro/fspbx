<?php

namespace App\Console\Commands\Updates;

use App\Models\DefaultSettings;
use App\Models\DeviceVendor;
use App\Models\Devices;
use App\Models\DomainSettings;
use App\Models\ProvisioningTemplate;
use App\Services\Provisioning\IntradeProvisionSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class Update239
{
    private const VERSION = '1.8.8.46';

    private const OLD_VENDOR = 'ibratro';

    private const NEW_VENDOR = 'intrade';

  /** @var array<string, string> */
    private const TEMPLATE_RENAMES = [
        'Intrade Entry' => 'Entry',
        'Intrade Standard' => 'Standard',
        'Intrade Advanced' => 'Advanced',
        'Intrade Video' => 'Video',
    ];

    public function apply(): bool
    {
        try {
            $this->renameVendor();
            $this->renameTemplates();
            $this->renameDevices();
            $this->migrateProvisionSettings();
            $this->seedIntradeDefaults();
            $this->seedIntradeTemplates();

            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function renameVendor(): void
    {
        if (! Schema::hasTable('v_device_vendors')) {
            echo "Device vendors table not found; skipping vendor rename.\n";

            return;
        }

        $existing = DeviceVendor::query()->where('name', self::OLD_VENDOR)->first();
        $target = DeviceVendor::query()->where('name', self::NEW_VENDOR)->first();

        if ($existing && $target) {
            $existing->forceFill([
                'enabled' => 'false',
                'description' => 'Deprecated alias of intrade',
            ])->save();
            echo "Disabled legacy vendor row '" . self::OLD_VENDOR . "'.\n";

            return;
        }

        if ($existing) {
            $existing->forceFill([
                'name' => self::NEW_VENDOR,
                'description' => 'Intrade phones (Fanvil OEM)',
            ])->save();
            echo "Renamed device vendor '" . self::OLD_VENDOR . "' to '" . self::NEW_VENDOR . "'.\n";

            return;
        }

        if (! $target) {
            DeviceVendor::query()->create([
                'device_vendor_uuid' => (string) Str::uuid(),
                'name' => self::NEW_VENDOR,
                'enabled' => 'true',
                'description' => 'Intrade phones (Fanvil OEM)',
            ]);
            echo "Created device vendor '" . self::NEW_VENDOR . "'.\n";
        }
    }

    private function renameTemplates(): void
    {
        if (! Schema::hasTable('provisioning_templates')) {
            echo "Provisioning templates table not found; skipping template rename.\n";

            return;
        }

        DB::transaction(function () {
            foreach (self::TEMPLATE_RENAMES as $oldName => $newName) {
                $defaultUpdated = ProvisioningTemplate::query()
                    ->where('vendor', self::OLD_VENDOR)
                    ->where('type', 'default')
                    ->where('name', $oldName)
                    ->update([
                        'vendor' => self::NEW_VENDOR,
                        'name' => $newName,
                    ]);

                if ($defaultUpdated > 0) {
                    echo "Renamed default template {$oldName} to {$newName}.\n";
                }

                $customUpdated = ProvisioningTemplate::query()
                    ->where('vendor', self::OLD_VENDOR)
                    ->where('type', 'custom')
                    ->where('base_template', $oldName)
                    ->update([
                        'vendor' => self::NEW_VENDOR,
                        'base_template' => $newName,
                    ]);

                if ($customUpdated > 0) {
                    echo "Updated {$customUpdated} custom template base reference(s) from {$oldName} to {$newName}.\n";
                }
            }

            $remaining = ProvisioningTemplate::query()
                ->where('vendor', self::OLD_VENDOR)
                ->update(['vendor' => self::NEW_VENDOR]);

            if ($remaining > 0) {
                echo "Moved {$remaining} remaining provisioning template row(s) to vendor '" . self::NEW_VENDOR . "'.\n";
            }
        });
    }

    private function renameDevices(): void
    {
        if (! Schema::hasTable('v_devices')) {
            echo "Devices table not found; skipping device rename.\n";

            return;
        }

        $vendorUpdated = Devices::query()
            ->where('device_vendor', self::OLD_VENDOR)
            ->update(['device_vendor' => self::NEW_VENDOR]);

        if ($vendorUpdated > 0) {
            echo "Updated {$vendorUpdated} device vendor row(s) to '" . self::NEW_VENDOR . "'.\n";
        }

        foreach (self::TEMPLATE_RENAMES as $oldName => $newName) {
            $legacyPath = self::OLD_VENDOR . '/' . $oldName;
            $newPath = self::NEW_VENDOR . '/' . $newName;

            $updated = Devices::query()
                ->where('device_template', $legacyPath)
                ->update(['device_template' => $newPath]);

            if ($updated > 0) {
                echo "Updated {$updated} device template path(s) from {$legacyPath} to {$newPath}.\n";
            }
        }
    }

    private function migrateProvisionSettings(): void
    {
        $defaultUpdated = DefaultSettings::query()
            ->where('default_setting_category', 'provision')
            ->where('default_setting_subcategory', 'like', self::OLD_VENDOR . '_%')
            ->get()
            ->each(function (DefaultSettings $setting) {
                $newSubcategory = 'intrade_' . substr((string) $setting->default_setting_subcategory, 8);
                $duplicate = DefaultSettings::query()
                    ->where('default_setting_category', 'provision')
                    ->where('default_setting_subcategory', $newSubcategory)
                    ->where('default_setting_name', $setting->default_setting_name)
                    ->where('default_setting_uuid', '!=', $setting->default_setting_uuid)
                    ->exists();

                if ($duplicate) {
                    $setting->delete();

                    return;
                }

                $setting->forceFill(['default_setting_subcategory' => $newSubcategory])->save();
            })
            ->count();

        if ($defaultUpdated > 0) {
            echo "Migrated {$defaultUpdated} default provision setting row(s) to intrade_* subcategories.\n";
        }

        if (! Schema::hasTable('v_domain_settings')) {
            return;
        }

        $domainUpdated = DomainSettings::query()
            ->where('domain_setting_category', 'provision')
            ->where('domain_setting_subcategory', 'like', self::OLD_VENDOR . '_%')
            ->get()
            ->each(function (DomainSettings $setting) {
                $newSubcategory = 'intrade_' . substr((string) $setting->domain_setting_subcategory, 8);
                $setting->forceFill(['domain_setting_subcategory' => $newSubcategory])->save();
            })
            ->count();

        if ($domainUpdated > 0) {
            echo "Migrated {$domainUpdated} domain provision setting override row(s) to intrade_* subcategories.\n";
        }
    }

    private function seedIntradeDefaults(): void
    {
        $inserted = 0;

        foreach (IntradeProvisionSettings::definitions() as $setting) {
            $created = DefaultSettings::query()->firstOrCreate(
                [
                    'default_setting_category' => $setting['default_setting_category'],
                    'default_setting_subcategory' => $setting['default_setting_subcategory'],
                    'default_setting_name' => $setting['default_setting_name'],
                ],
                $setting,
            );

            if ($created->wasRecentlyCreated) {
                $inserted++;
            }
        }

        echo "Seeded {$inserted} Intrade provision default settings.\n";
    }

    private function seedIntradeTemplates(): void
    {
        $exitCode = Artisan::call('prov:templates:seed', [
            '--vendor' => self::NEW_VENDOR,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'prov:templates:seed --vendor=intrade failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Re-seeded Intrade provisioning templates.\n";
    }
}
