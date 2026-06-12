<?php

namespace App\Services\Contacts;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Support\Facades\Crypt;

class ContactSyncCredentialService
{
    public function googleClientId(?string $domainUuid = null): ?string
    {
        return $this->settingValue('google_oauth_client_id', $domainUuid);
    }

    public function googleClientSecret(?string $domainUuid = null): ?string
    {
        return $this->secretValue('google_oauth_client_secret', $domainUuid);
    }

    public function microsoftClientId(?string $domainUuid = null): ?string
    {
        return $this->settingValue('microsoft_oauth_client_id', $domainUuid);
    }

    public function microsoftClientSecret(?string $domainUuid = null): ?string
    {
        return $this->secretValue('microsoft_oauth_client_secret', $domainUuid);
    }

    public function microsoftTenantId(?string $domainUuid = null): string
    {
        return $this->settingValue('microsoft_oauth_tenant_id', $domainUuid) ?: 'common';
    }

    public function googleConfigured(?string $domainUuid = null): bool
    {
        return $this->googleClientId($domainUuid) && $this->googleClientSecret($domainUuid);
    }

    public function microsoftConfigured(?string $domainUuid = null): bool
    {
        return $this->microsoftClientId($domainUuid) && $this->microsoftClientSecret($domainUuid);
    }

    private function settingValue(string $subcategory, ?string $domainUuid): ?string
    {
        $domainUuid ??= session('domain_uuid');

        if ($domainUuid) {
            $domainValue = DomainSettings::query()
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'contact')
                ->where('domain_setting_subcategory', $subcategory)
                ->where('domain_setting_enabled', true)
                ->value('domain_setting_value');

            if (is_string($domainValue) && trim($domainValue) !== '') {
                return trim($domainValue);
            }
        }

        $default = DefaultSettings::query()
            ->where('default_setting_category', 'contact')
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        return is_string($default) && trim($default) !== '' ? trim($default) : null;
    }

    private function secretValue(string $subcategory, ?string $domainUuid): ?string
    {
        $value = $this->settingValue($subcategory, $domainUuid);

        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
