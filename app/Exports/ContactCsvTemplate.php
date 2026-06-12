<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactCsvTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
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
            'phone_label',
            'phone_number',
            'phone_extension',
            'phone_speed_dial',
            'email_label',
            'email_address',
            'assigned_user',
            'assigned_group',
        ];
    }

    public function collection(): Collection
    {
        return new Collection([
            [
                'individual',
                'Acme Corp',
                'Jane',
                'Doe',
                'Sales Manager',
                'Sales',
                'Customer',
                'Primary customer contact',
                'America/New_York',
                'https://example.com',
                'work',
                '5551234567',
                '',
                '10',
                'work',
                'jane.doe@example.com',
                'admin',
                'users',
            ],
        ]);
    }
}
