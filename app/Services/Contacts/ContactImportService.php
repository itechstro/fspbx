<?php

namespace App\Services\Contacts;

use App\Models\Groups;
use App\Models\User;
use App\Services\ContactService;

class ContactImportService
{
    public function __construct(
        private ContactService $contactService,
        private ContactVcardService $vcardService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int}
     */
    public function importCsvRows(array $rows): array
    {
        $imported = 0;

        foreach ($rows as $row) {
            $payload = $this->normalizeCsvRow($row);

            if ($payload === null) {
                continue;
            }

            $this->contactService->save($payload);
            $imported++;
        }

        return ['imported' => $imported];
    }

    /**
     * @return array{imported: int}
     */
    public function importVcardContent(string $content): array
    {
        $cards = $this->vcardService->parse($content);
        $imported = 0;

        foreach ($cards as $card) {
            $this->contactService->save($card);
            $imported++;
        }

        return ['imported' => $imported];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeCsvRow(array $row): ?array
    {
        $organization = $this->stringValue($row['contact_organization'] ?? null);
        $given = $this->stringValue($row['contact_name_given'] ?? null);
        $family = $this->stringValue($row['contact_name_family'] ?? null);

        if ($organization === null && $given === null && $family === null) {
            return null;
        }

        $payload = [
            'contact_type' => $this->stringValue($row['contact_type'] ?? null) ?: 'individual',
            'contact_organization' => $organization,
            'contact_name_given' => $given,
            'contact_name_family' => $family,
            'contact_title' => $this->stringValue($row['contact_title'] ?? null),
            'contact_role' => $this->stringValue($row['contact_role'] ?? null),
            'contact_category' => $this->stringValue($row['contact_category'] ?? null),
            'contact_note' => $this->stringValue($row['contact_note'] ?? null),
            'contact_time_zone' => $this->stringValue($row['contact_time_zone'] ?? null),
            'contact_url' => $this->stringValue($row['contact_url'] ?? null),
            'phones' => $this->phonesFromCsvRow($row),
            'emails' => $this->emailsFromCsvRow($row),
            'contact_users' => $this->usersFromCsvRow($row),
            'contact_groups' => $this->groupsFromCsvRow($row),
        ];

        if ($payload['contact_type'] === 'organization' || ($organization && ! $given && ! $family)) {
            $payload['contact_type'] = 'organization';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    private function phonesFromCsvRow(array $row): array
    {
        $phones = [];

        for ($index = 1; $index <= 5; $index++) {
            $number = $this->stringValue($row["phone_{$index}_number"] ?? null);

            if ($number === null && $index === 1) {
                $number = $this->stringValue($row['phone_number'] ?? null);
            }

            if ($number === null) {
                continue;
            }

            $label = $this->stringValue($row["phone_{$index}_label"] ?? null)
                ?? ($index === 1 ? $this->stringValue($row['phone_label'] ?? null) : null);

            $phones[] = [
                'phone_number' => $number,
                'phone_label' => $label,
                'phone_extension' => $this->stringValue($row["phone_{$index}_extension"] ?? null)
                    ?? ($index === 1 ? $this->stringValue($row['phone_extension'] ?? null) : null),
                'phone_speed_dial' => $this->stringValue($row["phone_{$index}_speed_dial"] ?? null)
                    ?? ($index === 1 ? $this->stringValue($row['phone_speed_dial'] ?? null) : null),
                'phone_type_voice' => 1,
                'phone_primary' => empty($phones) ? 1 : null,
            ];
        }

        return $phones;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    private function emailsFromCsvRow(array $row): array
    {
        $emails = [];

        for ($index = 1; $index <= 3; $index++) {
            $address = $this->stringValue($row["email_{$index}_address"] ?? null);

            if ($address === null && $index === 1) {
                $address = $this->stringValue($row['email_address'] ?? null);
            }

            if ($address === null) {
                continue;
            }

            $emails[] = [
                'email_address' => $address,
                'email_label' => $this->stringValue($row["email_{$index}_label"] ?? null)
                    ?? ($index === 1 ? $this->stringValue($row['email_label'] ?? null) : null),
                'email_primary' => empty($emails) ? 1 : null,
            ];
        }

        return $emails;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function usersFromCsvRow(array $row): array
    {
        $raw = $row['assigned_user'] ?? [];
        $usernames = collect(is_array($raw) ? $raw : [$raw])
            ->map(fn ($username) => trim((string) $username))
            ->filter()
            ->values();

        if ($usernames->isEmpty()) {
            return [];
        }

        $domainUuid = session('domain_uuid');

        return User::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('username', $usernames->all())
            ->pluck('user_uuid')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function groupsFromCsvRow(array $row): array
    {
        $raw = $row['assigned_group'] ?? [];
        $groupNames = collect(is_array($raw) ? $raw : [$raw])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        if ($groupNames->isEmpty()) {
            return [];
        }

        $domainUuid = session('domain_uuid');

        return Groups::query()
            ->where(function ($query) use ($domainUuid) {
                $query->whereNull('domain_uuid')
                    ->orWhere('domain_uuid', $domainUuid);
            })
            ->whereIn('group_name', $groupNames->all())
            ->pluck('group_uuid')
            ->all();
    }

    private function stringValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
