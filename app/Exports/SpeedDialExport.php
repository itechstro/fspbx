<?php

namespace App\Exports;

use App\Models\SpeedDial;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SpeedDialExport implements FromCollection, WithHeadings
{
    protected Collection $contacts;

    protected int $maxUsers;

    protected $searchable = ['contact_organization', 'primaryPhone.phone_number', 'primaryPhone.phone_speed_dial'];

    public function __construct(?Collection $contacts = null)
    {
        if ($contacts !== null) {
            $this->contacts = $contacts;
            $this->maxUsers = (int) $this->contacts->max(
                fn ($contact) => $contact->relationLoaded('contactUsers')
                    ? $contact->contactUsers->count()
                    : ($contact->speedDialUser?->count() ?? 0)
            );

            return;
        }

        $domainUuid = session('domain_uuid');
        $sortField = request()->get('sortField', 'contact_organization');
        $sortOrder = request()->get('sortOrder', 'asc');

        $query = SpeedDial::query()
            ->where('domain_uuid', $domainUuid)
            ->with([
                'primaryPhone' => function ($query) {
                    $query->select('contact_uuid', 'phone_number', 'phone_speed_dial');
                },
                'speedDialUser.user' => function ($query) {
                    $query->select('user_uuid', 'username');
                },
            ])
            ->orderBy($sortField, $sortOrder);

        $filterData = request('filterData', []);
        if (! empty($filterData['search'])) {
            $value = $filterData['search'];
            $query->where(function ($query) use ($value) {
                foreach ($this->searchable as $field) {
                    if (strpos($field, '.') !== false) {
                        [$relation, $nestedField] = explode('.', $field, 2);
                        $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                            $query->where($nestedField, 'ilike', '%' . $value . '%');
                        });
                    } else {
                        $query->orWhere($field, 'ilike', '%' . $value . '%');
                    }
                }
            });
        }

        $this->contacts = $query->get();
        $this->maxUsers = (int) $this->contacts->max(
            fn ($contact) => $contact->speedDialUser ? $contact->speedDialUser->count() : 0
        );
    }
    

    public function headings(): array
    {
        // Basic columns first
        $headers = ['contact_name', 'destination_number', 'speed_dial_code'];
        // Add a duplicate header "assigned_user" for each possible user.
        for ($i = 0; $i < $this->maxUsers; $i++) {
            $headers[] = 'assigned_user';
        }
        return $headers;
    }

    public function collection(): Collection
    {
        // Map each contact to a row matching the upload template format.
        $exportData = $this->contacts->map(function ($contact) {
            $destinationNumber = $contact->primaryPhone ? $contact->primaryPhone->phone_number : '';
            $speedDialCode = $contact->primaryPhone ? $contact->primaryPhone->phone_speed_dial : '';
            $row = [
                'contact_name' => $contact->contact_organization,
                'destination_number' => $destinationNumber,
                'speed_dial_code' => $speedDialCode,
            ];

            $assignments = $contact->relationLoaded('contactUsers')
                ? $contact->contactUsers
                : $contact->speedDialUser;

            $usernames = [];
            if ($assignments && $assignments->isNotEmpty()) {
                foreach ($assignments as $contactUser) {
                    if ($contactUser->user) {
                        $usernames[] = $contactUser->user->username;
                    }
                }
            }

            // Add as many "assigned_user" fields as the maximum count.
            for ($i = 0; $i < $this->maxUsers; $i++) {
                $row[] = $usernames[$i] ?? '';
            }
            return $row;
        });

        return $exportData;
    }
}
