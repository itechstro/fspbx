<?php

namespace App\Exports;

use App\Models\VContact;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactCsvExport implements FromCollection, WithHeadings
{
    private Collection $contacts;

    private int $maxPhones = 1;

    private int $maxEmails = 1;

    private int $maxUsers = 0;

    private int $maxGroups = 0;

    /**
     * @param  Collection<int, VContact>  $contacts
     */
    public function __construct(Collection $contacts)
    {
        $this->contacts = $contacts;

        $this->maxPhones = max(1, (int) $contacts->max(fn (VContact $contact) => $contact->phones->count()));
        $this->maxEmails = max(1, (int) $contacts->max(fn (VContact $contact) => $contact->emails->count()));
        $this->maxUsers = (int) $contacts->max(fn (VContact $contact) => $contact->contactUsers->count());
        $this->maxGroups = (int) $contacts->max(fn (VContact $contact) => $contact->contactGroups->count());
    }

    public function headings(): array
    {
        $headers = [
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

        for ($index = 1; $index <= $this->maxPhones; $index++) {
            $headers[] = "phone_{$index}_label";
            $headers[] = "phone_{$index}_number";
            $headers[] = "phone_{$index}_extension";
            $headers[] = "phone_{$index}_speed_dial";
        }

        for ($index = 1; $index <= $this->maxEmails; $index++) {
            $headers[] = "email_{$index}_label";
            $headers[] = "email_{$index}_address";
        }

        for ($index = 0; $index < $this->maxUsers; $index++) {
            $headers[] = 'assigned_user';
        }

        for ($index = 0; $index < $this->maxGroups; $index++) {
            $headers[] = 'assigned_group';
        }

        return $headers;
    }

    public function collection(): Collection
    {
        return $this->contacts->map(function (VContact $contact) {
            $row = [
                $contact->contact_type,
                $contact->contact_organization,
                $contact->contact_name_given,
                $contact->contact_name_family,
                $contact->contact_title,
                $contact->contact_role,
                $contact->contact_category,
                $contact->contact_note,
                $contact->contact_time_zone,
                $contact->contact_url,
            ];

            for ($index = 0; $index < $this->maxPhones; $index++) {
                $phone = $contact->phones[$index] ?? null;
                $row[] = $phone?->phone_label;
                $row[] = $phone?->phone_number;
                $row[] = $phone?->phone_extension;
                $row[] = $phone?->phone_speed_dial;
            }

            for ($index = 0; $index < $this->maxEmails; $index++) {
                $email = $contact->emails[$index] ?? null;
                $row[] = $email?->email_label;
                $row[] = $email?->email_address;
            }

            $usernames = $contact->contactUsers
                ->map(fn ($assignment) => $assignment->user?->username)
                ->filter()
                ->values();

            for ($index = 0; $index < $this->maxUsers; $index++) {
                $row[] = $usernames[$index] ?? '';
            }

            $groupNames = $contact->contactGroups
                ->map(fn ($assignment) => $assignment->group?->group_name)
                ->filter()
                ->values();

            for ($index = 0; $index < $this->maxGroups; $index++) {
                $row[] = $groupNames[$index] ?? '';
            }

            return $row;
        });
    }
}
