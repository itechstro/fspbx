<?php

namespace App\Services\Contacts;

use App\Models\VContact;
use App\Models\VContactSetting;
use Illuminate\Support\Str;

class ContactSyncMetadataService
{
    public function findContactUuidByExternalId(string $domainUuid, string $provider, string $externalId): ?string
    {
        return VContactSetting::query()
            ->where('domain_uuid', $domainUuid)
            ->where('contact_setting_category', $provider)
            ->where('contact_setting_subcategory', 'id')
            ->where('contact_setting_name', 'text')
            ->where('contact_setting_value', $externalId)
            ->where('contact_setting_enabled', 'true')
            ->value('contact_uuid');
    }

    public function upsertSyncMetadata(
        VContact $contact,
        string $provider,
        string $externalId,
        ?string $etag = null,
        ?string $updatedAt = null,
    ): void {
        $this->upsertSetting($contact, 'sync', 'source', 'array', $provider);
        $this->upsertSetting($contact, $provider, 'id', 'text', $externalId);

        if ($etag !== null && $etag !== '') {
            $this->upsertSetting($contact, $provider, 'etag', 'text', $etag);
        }

        if ($updatedAt !== null && $updatedAt !== '') {
            $this->upsertSetting($contact, $provider, 'updated', 'date', $updatedAt);
        }
    }

    public function contactHasSyncSource(VContact $contact, string $provider): bool
    {
        return VContactSetting::query()
            ->where('contact_uuid', $contact->contact_uuid)
            ->where('contact_setting_category', 'sync')
            ->where('contact_setting_subcategory', 'source')
            ->where('contact_setting_name', 'array')
            ->where('contact_setting_value', $provider)
            ->exists();
    }

    private function upsertSetting(
        VContact $contact,
        string $category,
        string $subcategory,
        string $name,
        string $value,
    ): void {
        $existing = VContactSetting::query()
            ->where('contact_uuid', $contact->contact_uuid)
            ->where('contact_setting_category', $category)
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
            'contact_setting_category' => $category,
            'contact_setting_subcategory' => $subcategory,
            'contact_setting_name' => $name,
            'contact_setting_value' => $value,
            'contact_setting_enabled' => 'true',
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ]);
    }
}
