<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Services\Settings\SettingsManagementService;
use InvalidArgumentException;

class PhoneFirmwareProvisionSettingsService
{
    /** @var array<string, array{label: string, settings: list<array{subcategory: string, value: string, description: string}>}> */
    private const VENDOR_PROFILES = [
        'intrade' => [
            'label' => 'Intrade',
            'settings' => [
                [
                    'subcategory' => 'intrade_enable_auto_upgrade',
                    'value' => '1',
                    'description' => 'Enable automatic firmware upgrade',
                ],
                [
                    'subcategory' => 'intrade_firmware_upgrade_server_1',
                    'value' => '{url}',
                    'description' => 'Firmware upgrade server 1',
                ],
            ],
        ],
        'fanvil' => [
            'label' => 'Fanvil',
            'settings' => [
                [
                    'subcategory' => 'fanvil_enable_auto_upgrade',
                    'value' => '1',
                    'description' => 'Enable automatic firmware upgrade',
                ],
                [
                    'subcategory' => 'fanvil_firmware_upgrade_server_1',
                    'value' => '{url}',
                    'description' => 'Firmware upgrade server 1',
                ],
            ],
        ],
        'grandstream' => [
            'label' => 'Grandstream',
            'settings' => [
                [
                    'subcategory' => 'grandstream_firmware_path',
                    'value' => '{grandstream_path}',
                    'description' => 'Firmware server path (host and path without protocol)',
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly PhoneFirmwareService $firmwareService,
        private readonly SettingsManagementService $settings,
    ) {}

    /**
     * @return array{
     *     supported: bool,
     *     vendor: string|null,
     *     label: string|null,
     *     public_url: string|null,
     *     settings: list<array{subcategory: string, value: string, description: string}>
     * }
     */
    public function preview(string $relativePath, string $publicBaseUrl): array
    {
        $vendor = $this->resolveVendor($relativePath);

        if ($vendor === null) {
            return [
                'supported' => false,
                'vendor' => null,
                'label' => null,
                'public_url' => null,
                'settings' => [],
            ];
        }

        $publicUrl = $this->firmwareService->publicUrl($relativePath, $publicBaseUrl);

        return [
            'supported' => true,
            'vendor' => $vendor,
            'label' => self::VENDOR_PROFILES[$vendor]['label'],
            'public_url' => $publicUrl,
            'settings' => $this->resolvedSettings($vendor, $publicUrl),
        ];
    }

    /**
     * @return array{
     *     vendor: string,
     *     label: string,
     *     scope: string,
     *     settings: list<array{subcategory: string, value: string}>
     * }
     */
    public function apply(string $scope, string $relativePath, string $publicBaseUrl, ?Domain $domain = null): array
    {
        $preview = $this->preview($relativePath, $publicBaseUrl);

        if (! $preview['supported'] || $preview['vendor'] === null) {
            throw new InvalidArgumentException('Open a supported vendor folder before applying provision settings.');
        }

        if ($scope === 'domain') {
            if (! $domain) {
                throw new InvalidArgumentException('Domain is required for domain-scoped settings.');
            }

            foreach ($preview['settings'] as $setting) {
                $this->upsertDomainSetting($domain, $setting['subcategory'], $setting['value'], $setting['description']);
            }
        } elseif ($scope === 'default') {
            foreach ($preview['settings'] as $setting) {
                $this->upsertDefaultSetting($setting['subcategory'], $setting['value'], $setting['description']);
            }
        } else {
            throw new InvalidArgumentException('Invalid settings scope.');
        }

        return [
            'vendor' => $preview['vendor'],
            'label' => $preview['label'],
            'scope' => $scope,
            'settings' => array_map(
                fn (array $setting) => [
                    'subcategory' => $setting['subcategory'],
                    'value' => $setting['value'],
                ],
                $preview['settings'],
            ),
        ];
    }

    private function resolveVendor(string $relativePath): ?string
    {
        $relativePath = $this->firmwareService->normalizeRelativePath($relativePath);
        $vendor = strtolower((string) strtok($relativePath, '/'));

        if ($vendor === '' || ! isset(self::VENDOR_PROFILES[$vendor])) {
            return null;
        }

        return $vendor;
    }

    /**
     * @return list<array{subcategory: string, value: string, description: string}>
     */
    private function resolvedSettings(string $vendor, string $publicUrl): array
    {
        return array_map(function (array $setting) use ($publicUrl) {
            return [
                'subcategory' => $setting['subcategory'],
                'value' => $this->resolveSettingValue($setting['value'], $publicUrl),
                'description' => $setting['description'],
            ];
        }, self::VENDOR_PROFILES[$vendor]['settings']);
    }

    private function resolveSettingValue(string $template, string $publicUrl): string
    {
        return match ($template) {
            '{url}' => $publicUrl,
            '{grandstream_path}' => $this->grandstreamFirmwarePath($publicUrl),
            default => $template,
        };
    }

    private function grandstreamFirmwarePath(string $publicUrl): string
    {
        $path = trim(str_replace(['http://', 'https://'], '', $publicUrl), '/');

        return $path === '' ? '' : $path . '/';
    }

    private function upsertDefaultSetting(string $subcategory, string $value, string $description): void
    {
        $existing = DefaultSettings::query()
            ->where('default_setting_category', 'provision')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_name', 'text')
            ->first();

        if ($existing) {
            $this->settings->saveDefault([
                'default_setting_category' => 'provision',
                'default_setting_subcategory' => $subcategory,
                'default_setting_name' => 'text',
                'default_setting_value' => $value,
                'default_setting_order' => $existing->default_setting_order,
                'default_setting_enabled' => true,
                'default_setting_description' => $existing->default_setting_description ?: $description,
            ], $existing);

            return;
        }

        $this->settings->saveDefault([
            'default_setting_category' => 'provision',
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => 'text',
            'default_setting_value' => $value,
            'default_setting_order' => null,
            'default_setting_enabled' => true,
            'default_setting_description' => $description,
        ]);
    }

    private function upsertDomainSetting(Domain $domain, string $subcategory, string $value, string $description): void
    {
        $existing = DomainSettings::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->where('domain_setting_category', 'provision')
            ->where('domain_setting_subcategory', $subcategory)
            ->where('domain_setting_name', 'text')
            ->first();

        if ($existing) {
            $this->settings->saveDomainOverride($domain, [
                'domain_setting_category' => 'provision',
                'domain_setting_subcategory' => $subcategory,
                'domain_setting_name' => 'text',
                'domain_setting_value' => $value,
                'domain_setting_order' => $existing->domain_setting_order,
                'domain_setting_enabled' => true,
                'domain_setting_description' => $existing->domain_setting_description ?: $description,
            ], $existing);

            return;
        }

        $default = DefaultSettings::query()
            ->where('default_setting_category', 'provision')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_name', 'text')
            ->first();

        $this->settings->saveDomainOverride($domain, [
            'domain_setting_category' => 'provision',
            'domain_setting_subcategory' => $subcategory,
            'domain_setting_name' => 'text',
            'domain_setting_value' => $value,
            'domain_setting_order' => $default?->default_setting_order,
            'domain_setting_enabled' => true,
            'domain_setting_description' => $default?->default_setting_description ?: $description,
        ]);
    }
}
