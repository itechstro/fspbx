<?php

namespace App\Services\Contacts;

class ExternalContactMapper
{
    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function toContactServicePayload(array $contact): array
    {
        $phones = collect($contact['phones'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['phone_number'] ?? '')) !== '')
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'phone_label' => $row['phone_label'] ?? ($index === 0 ? 'work' : 'other'),
                    'phone_number' => trim((string) $row['phone_number']),
                    'phone_primary' => ($row['phone_primary'] ?? false) ? '1' : ($index === 0 ? '1' : '0'),
                    'phone_type_voice' => '1',
                ];
            })
            ->all();

        $emails = collect($contact['emails'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['email_address'] ?? '')) !== '')
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'email_label' => $row['email_label'] ?? ($index === 0 ? 'work' : 'other'),
                    'email_address' => trim((string) $row['email_address']),
                    'email_primary' => ($row['email_primary'] ?? false) ? '1' : ($index === 0 ? '1' : '0'),
                ];
            })
            ->all();

        $addresses = collect($contact['addresses'] ?? [])
            ->filter(fn ($row) => collect($row)->filter()->isNotEmpty())
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'address_label' => $row['address_label'] ?? ($index === 0 ? 'work' : 'other'),
                    'address_street' => $row['address_street'] ?? null,
                    'address_extended' => $row['address_extended'] ?? null,
                    'address_locality' => $row['address_locality'] ?? null,
                    'address_region' => $row['address_region'] ?? null,
                    'address_postal_code' => $row['address_postal_code'] ?? null,
                    'address_country' => $row['address_country'] ?? null,
                    'address_primary' => ($row['address_primary'] ?? false) ? '1' : ($index === 0 ? '1' : '0'),
                ];
            })
            ->all();

        $urls = collect($contact['urls'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['url_address'] ?? '')) !== '')
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'url_label' => $row['url_label'] ?? ($index === 0 ? 'work' : 'other'),
                    'url_address' => trim((string) $row['url_address']),
                    'url_primary' => ($row['url_primary'] ?? false) ? '1' : ($index === 0 ? '1' : '0'),
                ];
            })
            ->all();

        $given = trim((string) ($contact['contact_name_given'] ?? ''));
        $family = trim((string) ($contact['contact_name_family'] ?? ''));
        $organization = trim((string) ($contact['contact_organization'] ?? ''));

        return [
            'contact_type' => $organization !== '' && $given === '' && $family === '' ? 'organization' : 'individual',
            'contact_organization' => $organization !== '' ? $organization : null,
            'contact_name_given' => $given !== '' ? $given : null,
            'contact_name_family' => $family !== '' ? $family : null,
            'contact_title' => $this->blankToNull($contact['contact_title'] ?? null),
            'contact_category' => $this->blankToNull($contact['contact_category'] ?? null),
            'contact_note' => $this->blankToNull($contact['contact_note'] ?? null),
            'phones' => $phones,
            'emails' => $emails,
            'addresses' => $addresses,
            'urls' => $urls,
            'notes' => [],
            'times' => [],
            'relations' => [],
            'attachments' => [],
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
