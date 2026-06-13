<?php

namespace App\Services;

use App\Models\DeviceKey;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\DeviceLines;
use App\Services\DeviceCloudProvisioningService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DeviceService
{
    public function create(array $validated): Devices
    {
        return DB::transaction(function () use ($validated) {
            $inputs = $validated;
            $inputs['device_enabled'] = $inputs['device_enabled'] ?? 'true';
            $inputs['device_enabled'] = $this->normalizeEnabledValue($inputs['device_enabled']);
            $inputs['device_address'] = $inputs['device_address_modified'];
            $this->normalizeKeyTemplateValue($inputs);

            $domainUuid = (string) ($inputs['domain_uuid'] ?? '');

            $device = new Devices();
            $device->fill($inputs);
            $device->save();

            $deviceLines = $inputs['device_lines'] ?? null;
            if (is_array($deviceLines) && ! empty($deviceLines)) {
                $this->syncDeviceLines($device, $deviceLines, $domainUuid);
            }

            return $device->fresh();
        });
    }

    public function update(Devices $device, array $validated): Devices
    {
        return DB::transaction(function () use ($device, $validated) {
            $inputs = $validated;

            if (array_key_exists('device_enabled', $inputs)) {
                $inputs['device_enabled'] = $this->normalizeEnabledValue($inputs['device_enabled']);
            }

            if (array_key_exists('device_address_modified', $inputs)) {
                $inputs['device_address'] = $inputs['device_address_modified'];
            }

            $this->normalizeKeyTemplateValue($inputs);

            $domainUuid = (string) ($inputs['domain_uuid'] ?? $device->domain_uuid);

            $device->update($inputs);

            if (array_key_exists('device_lines', $inputs)) {
                $this->syncDeviceLines($device, $inputs['device_lines'], $domainUuid);
            }

            if (array_key_exists('device_settings', $inputs)) {
                $this->syncDeviceSettings($device, $inputs['device_settings']);
            }

            if (array_key_exists('device_keys', $inputs)) {
                $this->syncDeviceKeys($device, $inputs['device_keys']);
            }

            return $device->fresh();
        });
    }

    public function delete(Devices $device): void
    {
        DB::transaction(function () use ($device) {
            $device = $this->loadDeleteSnapshot($device);

            if ($device->cloudProvisioning) {
                $params = [
                    'device_uuid' => $device->device_uuid,
                    'domain_uuid' => $device->domain_uuid,
                    'device_vendor' => $device->device_vendor,
                    'device_address' => $device->device_address,
                ];

                $deregisterJob = app(DeviceCloudProvisioningService::class)->deregister($params);
                $resetJob = app(DeviceCloudProvisioningService::class)->reset($params);

                if ($deregisterJob) {
                    dispatch($deregisterJob->chain([$resetJob]));
                } else {
                    dispatch($resetJob);
                }
            }

            if ($device->lines()) {
                $device->lines()->delete();
            }

            if ($device->settings()) {
                $device->settings()->delete();
            }

            if ($device->keys()) {
                $device->keys()->delete();
            }

            if ($device->legacy_keys()) {
                $device->legacy_keys()->delete();
            }

            $device->delete();
        });
    }

    private function normalizeKeyTemplateValue(array &$inputs): void
    {
        if (array_key_exists('device_key_template_uuid', $inputs) && $inputs['device_key_template_uuid'] === 'NULL') {
            $inputs['device_key_template_uuid'] = null;
        }
    }

    private function loadDeleteSnapshot(Devices $device): Devices
    {
        $needsReload = empty($device->domain_uuid)
            || $device->device_vendor === null
            || $device->device_address === null;

        if ($needsReload) {
            $reloaded = Devices::query()
                ->where('device_uuid', $device->device_uuid)
                ->first([
                    'device_uuid',
                    'domain_uuid',
                    'device_vendor',
                    'device_address',
                ]);

            if ($reloaded) {
                $device = $reloaded;
            }
        }

        $device->loadMissing('cloudProvisioning');

        return $device;
    }

    private function normalizeEnabledValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function syncDeviceLines(Devices $device, mixed $deviceLines, string $domainUuid): void
    {
        if (empty($deviceLines) || ! is_array($deviceLines)) {
            $device->lines()->delete();
            return;
        }

        $device->lines()->delete();

        foreach ($deviceLines as $line) {
            $isExternalLineType = ($line['line_type_id'] ?? null) === 'externalline';

            $extension = null;
            if (! empty($line['auth_id'])) {
                $extension = Extensions::where('extension', $line['auth_id'])
                    ->where('domain_uuid', $domainUuid)
                    ->first();
            }

            $usesManualCredentials = $isExternalLineType || ($extension === null && ! empty($line['auth_id']));

            $deviceLineData = [
                'device_uuid' => $device->device_uuid,
                'line_number' => $line['line_number'],
                'server_address' => $line['server_address'] ?? null,
                'server_address_primary' => $line['server_address_primary'] ?? null,
                'server_address_secondary' => $line['server_address_secondary'] ?? null,
                'outbound_proxy_primary' => $line['outbound_proxy_primary'] ?? null,
                'outbound_proxy_secondary' => $line['outbound_proxy_secondary'] ?? null,
                'display_name' => $line['display_name'] ?? null,
                'user_id' => $usesManualCredentials
                    ? ($line['user_id'] ?? $line['auth_id'] ?? null)
                    : ($extension->extension ?? ($line['user_id'] ?? null)),
                'auth_id' => $line['auth_id'] ?? null,
                'password' => $usesManualCredentials
                    ? ($line['password'] ?? null)
                    : ($extension->password ?? null),
                'label' => $line['display_name'] ?? null,
                'sip_port' => $line['sip_port'] ?? null,
                'sip_transport' => $line['sip_transport'] ?? null,
                'register_expires' => $line['register_expires'] ?? null,
                'shared_line' => ($line['line_type_id'] ?? null) === 'sharedline' ? '1' : '',
                'external_line' => $usesManualCredentials,
                'device_line_uuid' => $line['device_line_uuid'] ?? (string) Str::uuid(),
                'domain_uuid' => $device->domain_uuid,
                'enabled' => 'true',
            ];

            $deviceLine = new DeviceLines();
            $deviceLine->fill($deviceLineData);
            $deviceLine->save();
        }
    }

    private function syncDeviceSettings(Devices $device, mixed $deviceSettings): void
    {
        if (empty($deviceSettings) || ! is_array($deviceSettings)) {
            $device->settings()->delete();
            return;
        }

        $device->settings()->delete();

        foreach ($deviceSettings as $item) {
            $payload = [
                'device_uuid' => $device->device_uuid,
                'domain_uuid' => $device->domain_uuid,
                'device_setting_category' => $item['device_setting_category'] ?? null,
                'device_setting_subcategory' => $item['device_setting_subcategory'] ?? null,
                'device_setting_name' => $item['device_setting_name'] ?? null,
                'device_setting_value' => $item['device_setting_value'] ?? null,
                'device_setting_enabled' => $item['device_setting_enabled'] ?? 'false',
                'device_setting_description' => $item['device_setting_description'] ?? null,
            ];

            $device->settings()->create($payload);
        }
    }

    private function syncDeviceKeys(Devices $device, mixed $deviceKeys): void
    {
        if (empty($deviceKeys) || ! is_array($deviceKeys)) {
            $device->keys()->delete();
            return;
        }

        $device->keys()->delete();

        foreach ($deviceKeys as $key) {
            $deviceKey = new DeviceKey();
            $deviceKey->device_uuid = $device->device_uuid;
            $deviceKey->key_area = $key['key_area'] ?? 'main';
            $deviceKey->key_index = $key['key_index'];
            $deviceKey->key_type = $key['key_type'] ?? null;
            $deviceKey->key_value = $key['key_value'] ?? null;
            $deviceKey->key_label = $key['key_label'] ?? null;
            $deviceKey->save();
        }
    }
}
