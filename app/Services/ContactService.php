<?php

namespace App\Services;

use App\Models\VContact;
use App\Models\VContactAddress;
use App\Models\VContactAttachment;
use App\Models\VContactEmail;
use App\Models\VContactNote;
use App\Models\VContactPhone;
use App\Models\VContactGroup;
use App\Models\VContactRelation;
use App\Models\VContactTime;
use App\Models\VContactUrl;
use App\Models\SpeedDialUser;
use App\Services\Contacts\ContactCallingCardService;
use App\Services\Contacts\ContactUserLinkService;
use App\Models\VContactSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactService
{
    public function __construct(
        private ContactUserLinkService $contactUserLinkService,
        private ContactCallingCardService $contactCallingCardService,
    ) {}
    private const CORE_FIELDS = [
        'contact_type',
        'contact_organization',
        'contact_name_given',
        'contact_name_family',
        'contact_title',
        'contact_role',
        'contact_category',
        'contact_note',
        'contact_time_zone',
        'contact_url',
    ];

    public function save(array $validated, ?VContact $contact = null): VContact
    {
        $contact = DB::transaction(function () use ($validated, $contact) {
            $contact ??= new VContact();
            $isNew = ! $contact->exists;
            $domainUuid = session('domain_uuid');
            $userUuid = session('user_uuid');

            $contact->forceFill(array_merge(
                collect($validated)->only(self::CORE_FIELDS)->all(),
                [
                    'domain_uuid' => $domainUuid,
                    'contact_uuid' => $contact->contact_uuid ?: (string) Str::uuid(),
                    $isNew ? 'insert_date' : 'update_date' => now(),
                    $isNew ? 'insert_user' : 'update_user' => $userUuid,
                ]
            ))->save();

            $this->syncPhones($contact, $validated['phones'] ?? []);
            $this->syncEmails($contact, $validated['emails'] ?? []);
            $this->syncAddresses($contact, $validated['addresses'] ?? []);
            $this->syncNotes($contact, $validated['notes'] ?? []);
            $this->syncUrls($contact, $validated['urls'] ?? []);
            $this->syncTimes($contact, $validated['times'] ?? []);
            $this->syncRelations($contact, $validated['relations'] ?? []);
            $this->syncAttachmentMetadata($contact, $validated['attachments'] ?? []);

            if (array_key_exists('contact_users', $validated)) {
                $this->syncContactUsers($contact, $validated['contact_users'] ?? []);
            }

            if (array_key_exists('contact_groups', $validated)) {
                $this->syncContactGroups($contact, $validated['contact_groups'] ?? []);
            }

            if (array_key_exists('phonebook_extension_uuid', $validated)) {
                $this->contactUserLinkService->syncExtensionPhonebookContactAssignment(
                    $contact,
                    $validated['phonebook_extension_uuid'] ?: null,
                );
            }

            if ($this->shouldSyncCallingCard($validated)) {
                $this->contactCallingCardService->sync($contact, $validated);
            }

            return $contact->fresh([
                'phones',
                'emails',
                'addresses',
                'notes',
                'urls',
                'times',
                'relations',
                'attachments',
                'contactUsers.user',
                'contactGroups.group',
            ]);
        });

        try {
            $this->contactUserLinkService->syncCloudPlayForContact($contact);
        } catch (\Throwable $e) {
            logger('CloudPLAY contact sync failed after save: ' . $e->getMessage());
        }

        return $contact;
    }

    public function delete(VContact $contact): void
    {
        DB::transaction(function () use ($contact) {
            $this->contactUserLinkService->cleanupBeforeContactDelete($contact);

            $uuid = $contact->contact_uuid;

            foreach ([
                VContactPhone::class,
                VContactEmail::class,
                VContactAddress::class,
                VContactNote::class,
                VContactUrl::class,
                VContactTime::class,
                VContactRelation::class,
                VContactAttachment::class,
            ] as $model) {
                $model::query()->where('contact_uuid', $uuid)->delete();
            }

            SpeedDialUser::query()->where('contact_uuid', $uuid)->delete();
            VContactGroup::query()->where('contact_uuid', $uuid)->delete();
            VContactSetting::query()->where('contact_uuid', $uuid)->delete();
            $contact->delete();
        });
    }

    private function syncPhones(VContact $contact, array $rows): void
    {
        $rows = $this->ensureSinglePrimary($rows, 'phone_primary');

        $this->syncRows(
            VContactPhone::class,
            'contact_phone_uuid',
            $contact,
            $rows,
            fn (array $row) => ! $this->contactPhoneRowHasValue($row),
            fn (array $row) => [
                'phone_label' => $this->blankToNull($row['phone_label'] ?? null),
                'phone_number' => $this->blankToNull($row['phone_number'] ?? null),
                'phone_extension' => $this->blankToNull($row['phone_extension'] ?? null),
                'phone_speed_dial' => $this->blankToNull($row['phone_speed_dial'] ?? null),
                'phone_primary' => $this->toFlag($row['phone_primary'] ?? null),
                'phone_type_voice' => $this->toFlag($row['phone_type_voice'] ?? (
                    $this->contactPhoneRowHasValue($row) ? '1' : null
                )),
                'phone_type_fax' => $this->toFlag($row['phone_type_fax'] ?? null),
                'phone_type_video' => $this->toFlag($row['phone_type_video'] ?? null),
                'phone_type_text' => $this->toFlag($row['phone_type_text'] ?? null),
                'phone_description' => $this->blankToNull($row['phone_description'] ?? null),
            ]
        );
    }

    private function contactPhoneRowHasValue(array $row): bool
    {
        foreach (['phone_number', 'phone_extension', 'phone_speed_dial'] as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function syncEmails(VContact $contact, array $rows): void
    {
        $rows = $this->ensureSinglePrimary($rows, 'email_primary');

        $this->syncRows(
            VContactEmail::class,
            'contact_email_uuid',
            $contact,
            $rows,
            fn (array $row) => trim((string) ($row['email_address'] ?? '')) === '',
            fn (array $row) => [
                'email_label' => $this->blankToNull($row['email_label'] ?? null),
                'email_address' => trim((string) $row['email_address']),
                'email_primary' => $this->toFlag($row['email_primary'] ?? null),
                'email_description' => $this->blankToNull($row['email_description'] ?? null),
            ]
        );
    }

    private function syncAddresses(VContact $contact, array $rows): void
    {
        $this->syncRows(
            VContactAddress::class,
            'contact_address_uuid',
            $contact,
            $rows,
            fn (array $row) => $this->addressIsEmpty($row),
            fn (array $row) => [
                'address_label' => $this->blankToNull($row['address_label'] ?? null),
                'address_street' => $this->blankToNull($row['address_street'] ?? null),
                'address_extended' => $this->blankToNull($row['address_extended'] ?? null),
                'address_locality' => $this->blankToNull($row['address_locality'] ?? null),
                'address_region' => $this->blankToNull($row['address_region'] ?? null),
                'address_postal_code' => $this->blankToNull($row['address_postal_code'] ?? null),
                'address_country' => $this->blankToNull($row['address_country'] ?? null),
                'address_primary' => $this->toFlag($row['address_primary'] ?? null),
                'address_description' => $this->blankToNull($row['address_description'] ?? null),
            ]
        );
    }

    private function syncNotes(VContact $contact, array $rows): void
    {
        $this->syncRows(
            VContactNote::class,
            'contact_note_uuid',
            $contact,
            $rows,
            fn (array $row) => trim((string) ($row['contact_note'] ?? '')) === '',
            fn (array $row) => [
                'contact_note' => trim((string) $row['contact_note']),
            ]
        );
    }

    private function syncUrls(VContact $contact, array $rows): void
    {
        $this->syncRows(
            VContactUrl::class,
            'contact_url_uuid',
            $contact,
            $rows,
            fn (array $row) => trim((string) ($row['url_address'] ?? '')) === '',
            fn (array $row) => [
                'url_label' => $this->blankToNull($row['url_label'] ?? null),
                'url_type' => $this->blankToNull($row['url_type'] ?? null),
                'url_address' => trim((string) $row['url_address']),
                'url_primary' => $this->toFlag($row['url_primary'] ?? null),
                'url_description' => $this->blankToNull($row['url_description'] ?? null),
            ]
        );
    }

    private function syncTimes(VContact $contact, array $rows): void
    {
        $this->syncRows(
            VContactTime::class,
            'contact_time_uuid',
            $contact,
            $rows,
            fn (array $row) => empty($row['time_start']) && empty($row['time_stop']),
            fn (array $row) => [
                'time_start' => $this->blankToNull($row['time_start'] ?? null),
                'time_stop' => $this->blankToNull($row['time_stop'] ?? null),
                'time_description' => $this->blankToNull($row['time_description'] ?? null),
            ]
        );
    }

    private function syncRelations(VContact $contact, array $rows): void
    {
        $this->syncRows(
            VContactRelation::class,
            'contact_relation_uuid',
            $contact,
            $rows,
            fn (array $row) => empty($row['relation_contact_uuid']),
            fn (array $row) => [
                'relation_label' => $this->blankToNull($row['relation_label'] ?? null),
                'relation_contact_uuid' => $row['relation_contact_uuid'],
            ]
        );
    }

    private function syncAttachmentMetadata(VContact $contact, array $rows): void
    {
        foreach ($rows as $row) {
            $uuid = $row['contact_attachment_uuid'] ?? null;

            if (! $uuid || ! Str::isUuid($uuid)) {
                continue;
            }

            VContactAttachment::query()
                ->where('contact_uuid', $contact->contact_uuid)
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($uuid)
                ->update([
                    'attachment_primary' => $this->toFlag($row['attachment_primary'] ?? null),
                    'attachment_description' => $this->blankToNull($row['attachment_description'] ?? null),
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ]);
        }
    }

    private function syncRows(
        string $modelClass,
        string $uuidColumn,
        VContact $contact,
        array $rows,
        callable $isEmpty,
        callable $mapFields
    ): void {
        $kept = [];
        $domainUuid = session('domain_uuid');
        $userUuid = session('user_uuid');

        foreach ($rows as $row) {
            if (! is_array($row) || $isEmpty($row)) {
                continue;
            }

            $uuid = $row[$uuidColumn] ?? null;
            $uuid = ($uuid && Str::isUuid($uuid)) ? $uuid : (string) Str::uuid();

            $existing = $modelClass::query()->whereKey($uuid)->first();
            $attributes = array_merge($mapFields($row), [
                'domain_uuid' => $domainUuid,
                'contact_uuid' => $contact->contact_uuid,
            ]);

            if ($existing) {
                $existing->forceFill(array_merge($attributes, [
                    'update_date' => now(),
                    'update_user' => $userUuid,
                ]))->save();
                $kept[] = $existing->{$uuidColumn};
            } else {
                $model = new $modelClass();
                $model->{$uuidColumn} = $uuid;
                $model->forceFill(array_merge($attributes, [
                    'insert_date' => now(),
                    'insert_user' => $userUuid,
                ]))->save();
                $kept[] = $model->{$uuidColumn};
            }
        }

        $deleteQuery = $modelClass::query()
            ->where('contact_uuid', $contact->contact_uuid)
            ->where('domain_uuid', $domainUuid);

        if (! empty($kept)) {
            $deleteQuery->whereNotIn($uuidColumn, $kept);
        }

        $deleteQuery->delete();
    }

    private function addressIsEmpty(array $row): bool
    {
        foreach (['address_street', 'address_locality', 'address_region', 'address_postal_code', 'address_country'] as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toFlag(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false || $value === 'false' || $value === 0 || $value === '0') {
            return null;
        }

        return 1;
    }

    private function syncContactUsers(VContact $contact, array $rows): void
    {
        $domainUuid = session('domain_uuid');
        $userUuid = session('user_uuid');
        $assignedUserUuids = [];

        SpeedDialUser::query()
            ->where('contact_uuid', $contact->contact_uuid)
            ->where('domain_uuid', $domainUuid)
            ->delete();

        foreach ($rows as $row) {
            $assignedUserUuid = $this->extractUuid($row, 'user_uuid');

            if (! $assignedUserUuid) {
                continue;
            }

            $assignedUserUuids[] = $assignedUserUuid;

            $model = new SpeedDialUser();
            $model->contact_user_uuid = (string) Str::uuid();
            $model->forceFill([
                'domain_uuid' => $domainUuid,
                'contact_uuid' => $contact->contact_uuid,
                'user_uuid' => $assignedUserUuid,
                'insert_date' => now(),
                'insert_user' => $userUuid,
            ])->save();
        }

        $this->contactUserLinkService->syncUserContactUuidAssignments($contact, $assignedUserUuids);
    }

    private function syncContactGroups(VContact $contact, array $rows): void
    {
        $domainUuid = session('domain_uuid');
        $userUuid = session('user_uuid');

        VContactGroup::query()
            ->where('contact_uuid', $contact->contact_uuid)
            ->where('domain_uuid', $domainUuid)
            ->delete();

        foreach ($rows as $row) {
            $groupUuid = $this->extractUuid($row, 'group_uuid');

            if (! $groupUuid) {
                continue;
            }

            $model = new VContactGroup();
            $model->contact_group_uuid = (string) Str::uuid();
            $model->forceFill([
                'domain_uuid' => $domainUuid,
                'contact_uuid' => $contact->contact_uuid,
                'group_uuid' => $groupUuid,
                'insert_date' => now(),
                'insert_user' => $userUuid,
            ])->save();
        }
    }

    private function extractUuid(mixed $row, string $field): ?string
    {
        if (is_string($row) && Str::isUuid($row)) {
            return $row;
        }

        if (! is_array($row)) {
            return null;
        }

        $uuid = $row[$field] ?? $row['value'] ?? null;

        return (is_string($uuid) && Str::isUuid($uuid)) ? $uuid : null;
    }

    private function ensureSinglePrimary(array $rows, string $field): array
    {
        $winner = null;

        foreach ($rows as $index => $row) {
            if ($this->isPrimary($row[$field] ?? null)) {
                $winner = $index;
            }
        }

        if ($winner === null) {
            return $rows;
        }

        foreach ($rows as $index => &$row) {
            if ($index !== $winner) {
                $row[$field] = '0';
            }
        }
        unset($row);

        return $rows;
    }

    private function isPrimary(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }

    private function shouldSyncCallingCard(array $validated): bool
    {
        foreach ([
            'calling_card_enabled',
            'calling_card_mode',
            'calling_card_username',
            'calling_card_password',
            'calling_card_pinless_number',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                return true;
            }
        }

        return false;
    }
}
