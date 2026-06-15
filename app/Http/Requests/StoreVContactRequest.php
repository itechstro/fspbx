<?php

namespace App\Http\Requests;

use App\Services\Contacts\ContactCallingCardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('contact_add');
    }

    public function rules(): array
    {
        return array_merge($this->coreRules(), $this->nestedRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $organization = trim((string) $this->input('contact_organization', ''));
            $given = trim((string) $this->input('contact_name_given', ''));
            $family = trim((string) $this->input('contact_name_family', ''));

            if ($organization === '' && $given === '' && $family === '') {
                $validator->errors()->add(
                    'contact_organization',
                    'Enter an organization or a contact name.'
                );
            }

            $this->validateCallingCard($validator);
            $this->validatePhones($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_merge(
            $this->sanitizedCoreFields(),
            $this->sanitizedCallingCardFields(),
        ));
    }

    protected function coreRules(): array
    {
        return [
            'contact_type' => ['nullable', 'string', 'max:255'],
            'contact_organization' => ['nullable', 'string', 'max:255'],
            'contact_name_given' => ['nullable', 'string', 'max:255'],
            'contact_name_family' => ['nullable', 'string', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'contact_role' => ['nullable', 'string', 'max:255'],
            'contact_category' => ['nullable', 'string', 'max:255'],
            'contact_note' => ['nullable', 'string'],
            'contact_time_zone' => ['nullable', 'string', 'max:255'],
            'contact_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    protected function nestedRules(): array
    {
        return [
            'phones' => ['nullable', 'array'],
            'phones.*.contact_phone_uuid' => ['nullable', 'uuid'],
            'phones.*.phone_label' => ['nullable', 'string', 'max:255'],
            'phones.*.phone_number' => ['nullable', 'string', 'max:255'],
            'phones.*.phone_extension' => ['nullable', 'string', 'max:255'],
            'phones.*.phone_speed_dial' => ['nullable', 'string', 'max:255'],
            'phones.*.phone_primary' => ['nullable'],
            'phones.*.phone_type_voice' => ['nullable'],
            'phones.*.phone_type_fax' => ['nullable'],
            'phones.*.phone_type_video' => ['nullable'],
            'phones.*.phone_type_text' => ['nullable'],
            'phones.*.phone_description' => ['nullable', 'string', 'max:255'],

            'emails' => ['nullable', 'array'],
            'emails.*.contact_email_uuid' => ['nullable', 'uuid'],
            'emails.*.email_label' => ['nullable', 'string', 'max:255'],
            'emails.*.email_address' => ['nullable', 'string', 'max:255'],
            'emails.*.email_primary' => ['nullable'],
            'emails.*.email_description' => ['nullable', 'string', 'max:255'],

            'addresses' => ['nullable', 'array'],
            'addresses.*.contact_address_uuid' => ['nullable', 'uuid'],
            'addresses.*.address_label' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_street' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_extended' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_locality' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_region' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_postal_code' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_country' => ['nullable', 'string', 'max:255'],
            'addresses.*.address_primary' => ['nullable'],
            'addresses.*.address_description' => ['nullable', 'string', 'max:255'],

            'notes' => ['nullable', 'array'],
            'notes.*.contact_note_uuid' => ['nullable', 'uuid'],
            'notes.*.contact_note' => ['nullable', 'string'],

            'urls' => ['nullable', 'array'],
            'urls.*.contact_url_uuid' => ['nullable', 'uuid'],
            'urls.*.url_label' => ['nullable', 'string', 'max:255'],
            'urls.*.url_type' => ['nullable', 'string', 'max:255'],
            'urls.*.url_address' => ['nullable', 'string', 'max:2048'],
            'urls.*.url_primary' => ['nullable'],
            'urls.*.url_description' => ['nullable', 'string', 'max:255'],

            'times' => ['nullable', 'array'],
            'times.*.contact_time_uuid' => ['nullable', 'uuid'],
            'times.*.time_start' => ['nullable', 'string', 'max:255'],
            'times.*.time_stop' => ['nullable', 'string', 'max:255'],
            'times.*.time_description' => ['nullable', 'string', 'max:255'],

            'relations' => ['nullable', 'array'],
            'relations.*.contact_relation_uuid' => ['nullable', 'uuid'],
            'relations.*.relation_label' => ['nullable', 'string', 'max:255'],
            'relations.*.relation_contact_uuid' => ['nullable', 'uuid'],

            'attachments' => ['nullable', 'array'],
            'attachments.*.contact_attachment_uuid' => ['nullable', 'uuid'],
            'attachments.*.attachment_primary' => ['nullable'],
            'attachments.*.attachment_description' => ['nullable', 'string', 'max:255'],

            'contact_users' => ['nullable', 'array'],
            'contact_users.*' => ['nullable'],
            'contact_users.*.user_uuid' => ['nullable', 'uuid'],
            'contact_users.*.value' => ['nullable', 'uuid'],

            'contact_groups' => ['nullable', 'array'],
            'contact_groups.*' => ['nullable'],
            'contact_groups.*.group_uuid' => ['nullable', 'uuid'],
            'contact_groups.*.value' => ['nullable', 'uuid'],

            'phonebook_extension_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('v_extensions', 'extension_uuid')->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'));
                }),
            ],

            'calling_card_enabled' => ['nullable'],
            'calling_card_mode' => ['nullable', 'string', Rule::in(['pin_auth', 'pinless'])],
            'calling_card_username' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'calling_card_password' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'calling_card_pinless_number' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
        ];
    }

    private function sanitizedCoreFields(): array
    {
        $fields = [
            'contact_type',
            'contact_organization',
            'contact_name_given',
            'contact_name_family',
            'contact_title',
            'contact_role',
            'contact_category',
            'contact_time_zone',
            'contact_url',
        ];

        $sanitized = [];

        foreach ($fields as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = trim(strip_tags((string) $this->input($field)));
            $sanitized[$field] = $value === '' ? null : $value;
        }

        if ($this->has('contact_note')) {
            $sanitized['contact_note'] = trim((string) $this->input('contact_note')) ?: null;
        }

        return $sanitized;
    }

    private function sanitizedCallingCardFields(): array
    {
        $sanitized = [];

        foreach ([
            'calling_card_username',
            'calling_card_password',
            'calling_card_pinless_number',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = preg_replace('/\D+/', '', (string) $this->input($field, '')) ?? '';
            $sanitized[$field] = $value === '' ? null : $value;
        }

        return $sanitized;
    }

    private function validateCallingCard(Validator $validator): void
    {
        if (! $this->hasCallingCardPayload()) {
            return;
        }

        $enabled = in_array($this->input('calling_card_enabled'), [true, 1, '1', 'true'], true);

        if (! $enabled) {
            return;
        }

        $mode = $this->input('calling_card_mode', 'pin_auth') === 'pinless' ? 'pinless' : 'pin_auth';
        $domainUuid = session('domain_uuid');
        $contactUuid = $this->route('v_contact')?->contact_uuid;
        $service = app(ContactCallingCardService::class);

        if ($mode === 'pinless') {
            $pinlessNumber = preg_replace('/\D+/', '', (string) $this->input('calling_card_pinless_number', '')) ?? '';

            if ($pinlessNumber === '') {
                $validator->errors()->add('calling_card_pinless_number', 'Enter the caller ID number for pinless access.');

                return;
            }

            if ($service->isPinlessNumberTaken($domainUuid, $pinlessNumber, $contactUuid)) {
                $validator->errors()->add('calling_card_pinless_number', 'This caller ID number is already assigned to another calling card.');
            }

            return;
        }

        $username = preg_replace('/\D+/', '', (string) $this->input('calling_card_username', '')) ?? '';
        $password = preg_replace('/\D+/', '', (string) $this->input('calling_card_password', '')) ?? '';

        if ($username === '') {
            $validator->errors()->add('calling_card_username', 'Enter a reference number.');
        } elseif ($service->isReferenceNumberTaken($domainUuid, $username, $contactUuid)) {
            $validator->errors()->add('calling_card_username', 'This reference number is already assigned to another calling card.');
        }

        if ($password === '') {
            $validator->errors()->add('calling_card_password', 'Enter a PIN.');
        }
    }

    private function validatePhones(Validator $validator): void
    {
        foreach ($this->input('phones', []) as $index => $phone) {
            if (! is_array($phone)) {
                continue;
            }

            $number = trim((string) ($phone['phone_number'] ?? ''));
            $extension = trim((string) ($phone['phone_extension'] ?? ''));
            $speedDial = trim((string) ($phone['phone_speed_dial'] ?? ''));

            if ($number === '' && $extension === '' && $speedDial === '') {
                continue;
            }

            if ($number === '' && $extension === '') {
                $validator->errors()->add(
                    "phones.$index.phone_number",
                    'Enter a phone number or extension.'
                );
            }
        }
    }

    private function hasCallingCardPayload(): bool
    {
        foreach ([
            'calling_card_enabled',
            'calling_card_mode',
            'calling_card_username',
            'calling_card_password',
            'calling_card_pinless_number',
        ] as $field) {
            if ($this->has($field)) {
                return true;
            }
        }

        return false;
    }
}
