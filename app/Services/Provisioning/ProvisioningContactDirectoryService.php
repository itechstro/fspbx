<?php

namespace App\Services\Provisioning;

use App\Models\Devices;
use App\Models\ExtensionUser;
use App\Models\Extensions;
use App\Models\User;
use App\Services\ContactVisibilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProvisioningContactDirectoryService
{
    public function __construct(private ContactVisibilityService $visibility) {}

    /**
     * Build the legacy-shaped contacts array used by phonebook/directory templates.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function buildForDevice(Devices $device, array $lines): array
    {
        $domainUuid = (string) $device->domain_uuid;
        $deviceUserUuid = $this->resolvePortalUserUuidForDevice($device);
        $lineNumber = (int) (array_key_first($lines) ?? 1);

        $contacts = [];

        if (! $this->visibility->contactPermissionsEnabled($domainUuid)) {
            $contacts = array_merge(
                $contacts,
                $this->voiceContactsForCategory($domainUuid, $lineNumber, 'groups', null)
            );
        } else {
            if ($this->visibility->provisionContactGroupsEnabled($domainUuid)) {
                $contacts = array_merge(
                    $contacts,
                    $this->voiceContactsForCategory($domainUuid, $lineNumber, 'groups', $deviceUserUuid)
                );
            }

            if ($this->visibility->provisionContactUsersEnabled($domainUuid)) {
                $contacts = array_merge(
                    $contacts,
                    $this->voiceContactsForCategory($domainUuid, $lineNumber, 'users', $deviceUserUuid)
                );
            }
        }

        foreach ($this->visibility->directoryExtensions($domainUuid) as $extension) {
            $contacts[] = $extension;
        }

        return $this->dedupeVoiceContacts($contacts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function voiceContactsForCategory(
        string $domainUuid,
        int $lineNumber,
        string $category,
        ?string $deviceUserUuid
    ): array {
        if ($category !== 'all' && $deviceUserUuid && ! $this->isUuid($deviceUserUuid)) {
            return [];
        }

        $query = DB::table('v_contacts as c')
            ->join('v_contact_phones as p', 'c.contact_uuid', '=', 'p.contact_uuid')
            ->where('c.domain_uuid', $domainUuid)
            ->where('p.domain_uuid', $domainUuid)
            ->where('p.phone_type_voice', '1')
            ->select([
                'c.contact_uuid',
                'c.contact_organization',
                'c.contact_name_given',
                'c.contact_name_family',
                'c.contact_type',
                'c.contact_category',
                'p.phone_label',
                'p.phone_number',
                'p.phone_extension',
                'p.phone_primary',
            ])
            ->orderBy('c.contact_organization')
            ->orderBy('c.contact_name_family')
            ->orderBy('c.contact_name_given')
            ->orderBy('p.phone_primary', 'desc')
            ->orderBy('p.insert_date');

        if ($category === 'groups' && $deviceUserUuid) {
            $query->whereIn('c.contact_uuid', function ($subquery) use ($domainUuid, $deviceUserUuid) {
                $subquery->select('contact_uuid')
                    ->from('v_contact_groups')
                    ->where('domain_uuid', $domainUuid)
                    ->whereIn('group_uuid', function ($groupQuery) use ($domainUuid, $deviceUserUuid) {
                        $groupQuery->select('group_uuid')
                            ->from('v_user_groups')
                            ->where('user_uuid', $deviceUserUuid)
                            ->where('domain_uuid', $domainUuid);
                    });
            });
        } elseif ($category === 'users' && $deviceUserUuid) {
            $query->whereIn('c.contact_uuid', function ($subquery) use ($domainUuid, $deviceUserUuid) {
                $subquery->select('contact_uuid')
                    ->from('v_contact_users')
                    ->where('domain_uuid', $domainUuid)
                    ->where('user_uuid', $deviceUserUuid);
            });
        }

        return $this->mapRowsToContacts($query->get(), $lineNumber, $category === 'all' ? 'groups' : $category);
    }

    /**
     * Legacy devices store an explicit portal user on v_devices.device_user_uuid.
     * When that is blank, derive the user from the device's primary extension line.
     */
    public function resolvePortalUserUuidForDevice(Devices $device): ?string
    {
        if ($this->isUuid($device->device_user_uuid)) {
            return (string) $device->device_user_uuid;
        }

        $device->loadMissing('lines');

        foreach ($device->lines as $line) {
            $extensionNumber = trim((string) ($line->auth_id ?? $line->user_id ?? ''));

            if ($extensionNumber === '') {
                continue;
            }

            $extension = Extensions::query()
                ->where('domain_uuid', $device->domain_uuid)
                ->where('extension', $extensionNumber)
                ->first();

            if (! $extension) {
                continue;
            }

            $directUser = User::query()
                ->where('domain_uuid', $device->domain_uuid)
                ->where('extension_uuid', $extension->extension_uuid)
                ->value('user_uuid');

            if ($this->isUuid($directUser)) {
                return (string) $directUser;
            }

            $assignedUser = ExtensionUser::query()
                ->where('extension_uuid', $extension->extension_uuid)
                ->value('user_uuid');

            if ($this->isUuid($assignedUser)) {
                return (string) $assignedUser;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapRowsToContacts(Collection $rows, int $lineNumber, string $category): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->contact_uuid . ':' . $category;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'category' => $category,
                    'contact_uuid' => $row->contact_uuid,
                    'contact_type' => $row->contact_type,
                    'contact_category' => $row->contact_category,
                    'contact_organization' => $row->contact_organization,
                    'contact_name_given' => $row->contact_name_given,
                    'contact_name_family' => $row->contact_name_family,
                    'phone_label' => null,
                    'phone_number' => null,
                    'phone_extension' => null,
                    'numbers' => [],
                ];
            }

            $phoneLabel = strtolower(trim((string) ($row->phone_label ?? '')));
            $entry = &$grouped[$key];

            $entry['numbers'][] = [
                'line_number' => $lineNumber,
                'phone_label' => $phoneLabel,
                'phone_number' => $row->phone_number,
                'phone_extension' => $row->phone_extension,
                'phone_primary' => $row->phone_primary,
            ];

            if (($row->phone_primary == '1') || empty($entry['phone_number'])) {
                $entry['phone_label'] = $phoneLabel;
                $entry['phone_number'] = $row->phone_number;
                $entry['phone_extension'] = $row->phone_extension;
            }

            if ($phoneLabel !== '') {
                $entry['phone_number_' . $phoneLabel] = $row->phone_number;
            }

            unset($entry);
        }

        return array_values($grouped);
    }

    private function isUuid(?string $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-fA-F-]{36}$/', $value) === 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function dedupeVoiceContacts(array $contacts): array
    {
        $extensionContacts = [];
        $voiceContacts = [];

        foreach ($contacts as $contact) {
            $category = (string) ($contact['category'] ?? '');
            $uuid = (string) ($contact['contact_uuid'] ?? '');

            if ($category === 'extensions' || $uuid === '') {
                $extensionContacts[] = $contact;

                continue;
            }

            $voiceKey = $uuid . ':' . $category;

            if (! isset($voiceContacts[$voiceKey])) {
                $voiceContacts[$voiceKey] = $contact;

                continue;
            }

            $existing = $voiceContacts[$voiceKey];
            $existingNumbers = collect($existing['numbers'] ?? []);
            $incomingNumbers = collect($contact['numbers'] ?? []);

            $existing['numbers'] = $existingNumbers
                ->merge($incomingNumbers)
                ->unique(fn (array $number) => ($number['phone_label'] ?? '') . ':' . ($number['phone_number'] ?? ''))
                ->values()
                ->all();

            foreach ($contact as $key => $value) {
                if (! str_starts_with($key, 'phone_number_') || $value === null || $value === '') {
                    continue;
                }

                $existing[$key] = $existing[$key] ?? $value;
            }

            if (empty($existing['phone_number']) && ! empty($contact['phone_number'])) {
                $existing['phone_label'] = $contact['phone_label'] ?? $existing['phone_label'];
                $existing['phone_number'] = $contact['phone_number'];
                $existing['phone_extension'] = $contact['phone_extension'] ?? $existing['phone_extension'];
            }

            $voiceContacts[$voiceKey] = $existing;
        }

        return array_merge(array_values($voiceContacts), $extensionContacts);
    }
}
