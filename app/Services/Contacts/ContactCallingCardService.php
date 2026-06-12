<?php

namespace App\Services\Contacts;

use App\Models\VContact;
use App\Models\VContactSetting;
use Illuminate\Support\Str;

class ContactCallingCardService
{
    public const CATEGORY = 'calling card';

    public function formatForForm(?string $contactUuid): array
    {
        if (! $contactUuid) {
            return $this->emptyFormState();
        }

        $settings = VContactSetting::query()
            ->where('contact_uuid', $contactUuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->where('contact_setting_enabled', 'true')
            ->get();

        $username = $this->settingValue($settings, 'authentication', 'username');
        $password = $this->settingValue($settings, 'authentication', 'password');
        $pinlessNumber = $this->settingValue($settings, 'pinless', 'phonenumber');

        if ($pinlessNumber !== null) {
            return [
                'calling_card_enabled' => true,
                'calling_card_mode' => 'pinless',
                'calling_card_username' => null,
                'calling_card_password' => null,
                'calling_card_pinless_number' => $pinlessNumber,
            ];
        }

        if ($username !== null || $password !== null) {
            return [
                'calling_card_enabled' => true,
                'calling_card_mode' => 'pin_auth',
                'calling_card_username' => $username,
                'calling_card_password' => $password,
                'calling_card_pinless_number' => null,
            ];
        }

        return $this->emptyFormState();
    }

    public function sync(VContact $contact, array $data): void
    {
        $enabled = $this->toBool($data['calling_card_enabled'] ?? false);

        if (! $enabled) {
            $this->deleteCallingCardSettings($contact->contact_uuid);

            return;
        }

        $mode = ($data['calling_card_mode'] ?? 'pin_auth') === 'pinless' ? 'pinless' : 'pin_auth';

        if ($mode === 'pinless') {
            $this->deleteAuthSettings($contact->contact_uuid);
            $this->upsertSetting(
                $contact,
                'pinless',
                'phonenumber',
                $this->normalizeDigits((string) ($data['calling_card_pinless_number'] ?? '')),
            );

            return;
        }

        $this->deletePinlessSettings($contact->contact_uuid);
        $this->upsertSetting(
            $contact,
            'authentication',
            'username',
            $this->normalizeDigits((string) ($data['calling_card_username'] ?? '')),
        );
        $this->upsertSetting(
            $contact,
            'authentication',
            'password',
            $this->normalizeDigits((string) ($data['calling_card_password'] ?? '')),
        );
    }

    public function deleteCallingCardSettings(string $contactUuid): void
    {
        VContactSetting::query()
            ->where('contact_uuid', $contactUuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->delete();
    }

    public function isReferenceNumberTaken(
        string $domainUuid,
        string $referenceNumber,
        ?string $excludeContactUuid = null,
    ): bool {
        $query = VContactSetting::query()
            ->where('domain_uuid', $domainUuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->where('contact_setting_subcategory', 'authentication')
            ->where('contact_setting_name', 'username')
            ->where('contact_setting_value', $referenceNumber)
            ->where('contact_setting_enabled', 'true');

        if ($excludeContactUuid) {
            $query->where('contact_uuid', '!=', $excludeContactUuid);
        }

        return $query->exists();
    }

    public function isPinlessNumberTaken(
        string $domainUuid,
        string $phoneNumber,
        ?string $excludeContactUuid = null,
    ): bool {
        $query = VContactSetting::query()
            ->where('domain_uuid', $domainUuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->where('contact_setting_subcategory', 'pinless')
            ->where('contact_setting_name', 'phonenumber')
            ->where('contact_setting_value', $phoneNumber)
            ->where('contact_setting_enabled', 'true');

        if ($excludeContactUuid) {
            $query->where('contact_uuid', '!=', $excludeContactUuid);
        }

        return $query->exists();
    }

    private function deleteAuthSettings(string $contactUuid): void
    {
        VContactSetting::query()
            ->where('contact_uuid', $contactUuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->where('contact_setting_subcategory', 'authentication')
            ->delete();
    }

    private function deletePinlessSettings(string $contactUuid): void
    {
        VContactSetting::query()
            ->where('contact_uuid', $contactUuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->where('contact_setting_subcategory', 'pinless')
            ->delete();
    }

    private function upsertSetting(
        VContact $contact,
        string $subcategory,
        string $name,
        string $value,
    ): void {
        $existing = VContactSetting::query()
            ->where('contact_uuid', $contact->contact_uuid)
            ->where('contact_setting_category', self::CATEGORY)
            ->where('contact_setting_subcategory', $subcategory)
            ->where('contact_setting_name', $name)
            ->first();

        if ($existing) {
            $existing->update([
                'contact_setting_value' => $value,
                'contact_setting_enabled' => 'true',
                'update_date' => now(),
                'update_user' => session('user_uuid'),
            ]);

            return;
        }

        VContactSetting::create([
            'contact_setting_uuid' => (string) Str::uuid(),
            'domain_uuid' => $contact->domain_uuid,
            'contact_uuid' => $contact->contact_uuid,
            'contact_setting_category' => self::CATEGORY,
            'contact_setting_subcategory' => $subcategory,
            'contact_setting_name' => $name,
            'contact_setting_value' => $value,
            'contact_setting_enabled' => 'true',
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ]);
    }

    private function settingValue($settings, string $subcategory, string $name): ?string
    {
        $row = $settings->first(function ($setting) use ($subcategory, $name) {
            return $setting->contact_setting_subcategory === $subcategory
                && $setting->contact_setting_name === $name;
        });

        $value = trim((string) ($row->contact_setting_value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }

    private function emptyFormState(): array
    {
        return [
            'calling_card_enabled' => false,
            'calling_card_mode' => 'pin_auth',
            'calling_card_username' => null,
            'calling_card_password' => null,
            'calling_card_pinless_number' => null,
        ];
    }
}
