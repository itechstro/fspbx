<?php

namespace App\Imports;

use App\Services\Contacts\ContactImportService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithGroupedHeadingRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ContactCsvImport implements ToCollection, WithHeadingRow, WithGroupedHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    private int $imported = 0;

    public function __construct(
        private ContactImportService $importService,
    ) {}

    public function rules(): array
    {
        return [
            '*.contact_type' => ['nullable', 'string', Rule::in(['individual', 'organization'])],
            '*.contact_organization' => ['nullable', 'string', 'max:255'],
            '*.contact_name_given' => ['nullable', 'string', 'max:255'],
            '*.contact_name_family' => ['nullable', 'string', 'max:255'],
            '*.phone_number' => ['nullable', 'string', 'max:255'],
            '*.email_address' => ['nullable', 'string', 'max:255'],
            '*.assigned_user' => ['nullable', 'array'],
            '*.assigned_group' => ['nullable', 'array'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            '*.contact_type.in' => 'Contact type must be individual or organization.',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        foreach ([
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
        ] as $field) {
            if (isset($data[$field])) {
                $data[$field] = trim((string) $data[$field]);
            }
        }

        foreach (['assigned_user', 'assigned_group'] as $field) {
            if (! isset($data[$field])) {
                continue;
            }

            if (is_string($data[$field])) {
                $data[$field] = [trim($data[$field])];
            }

            if (is_array($data[$field])) {
                $data[$field] = array_values(array_filter(array_map(
                    fn ($value) => trim((string) $value),
                    $data[$field]
                )));
            }
        }

        return $data;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($validator->getData() as $index => $row) {
                $organization = trim((string) ($row['contact_organization'] ?? ''));
                $given = trim((string) ($row['contact_name_given'] ?? ''));
                $family = trim((string) ($row['contact_name_family'] ?? ''));

                if ($organization === '' && $given === '' && $family === '') {
                    $validator->errors()->add($index . '.contact_organization', 'Enter an organization or a contact name.');
                }
            }
        });
    }

    public function collection(Collection $rows): void
    {
        $result = $this->importService->importCsvRows($rows->toArray());
        $this->imported = $result['imported'];
    }

    public function importedCount(): int
    {
        return $this->imported;
    }
}
