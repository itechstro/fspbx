<?php

namespace App\Services\Contacts;

use App\Models\Contact as MessagesCrmContact;
use App\Models\VContact;
use App\Models\VContactAddress;
use App\Models\VContactEmail;
use App\Models\VContactPhone;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MessagesCrmContactBackfillService
{
    public function backfill(?string $domainUuid = null, bool $dryRun = false): array
    {
        if (! Schema::hasTable('contacts') || ! Schema::hasColumn('v_contacts', 'messages_crm_contact_uuid')) {
            return [
                'created' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        $query = MessagesCrmContact::query()->with(['phones', 'emails', 'addresses']);

        if ($domainUuid) {
            $query->where('domain_uuid', $domainUuid);
        }

        $created = 0;
        $skipped = 0;
        $errors = 0;

        $query->orderBy('contact_uuid')->chunkById(100, function ($crmContacts) use (&$created, &$skipped, &$errors, $dryRun) {
            foreach ($crmContacts as $crmContact) {
                try {
                    if ($this->phonebookContactExistsForCrmContact($crmContact)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    $this->createPhonebookContactFromCrmContact($crmContact);
                    $created++;
                } catch (\Throwable $exception) {
                    $errors++;
                    logger('Messages CRM contact backfill error: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());
                }
            }
        }, 'contact_uuid');

        return compact('created', 'skipped', 'errors');
    }

    private function phonebookContactExistsForCrmContact(MessagesCrmContact $crmContact): bool
    {
        return VContact::query()
            ->where('domain_uuid', $crmContact->domain_uuid)
            ->where('messages_crm_contact_uuid', $crmContact->contact_uuid)
            ->exists();
    }

    private function createPhonebookContactFromCrmContact(MessagesCrmContact $crmContact): VContact
    {
        $contact = VContact::query()->create([
            'contact_uuid' => (string) Str::uuid(),
            'domain_uuid' => $crmContact->domain_uuid,
            'contact_type' => 'individual',
            'contact_name_given' => $this->blankToNull($crmContact->first_name),
            'contact_name_family' => $this->blankToNull($crmContact->last_name),
            'contact_title' => $this->blankToNull($crmContact->title),
            'contact_role' => $this->blankToNull($crmContact->department),
            'contact_note' => $this->blankToNull($crmContact->notes),
            'messages_crm_contact_uuid' => $crmContact->contact_uuid,
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ]);

        foreach ($crmContact->phones as $index => $phone) {
            $number = trim((string) $phone->phone_number);

            if ($number === '') {
                continue;
            }

            VContactPhone::query()->create([
                'contact_phone_uuid' => (string) Str::uuid(),
                'domain_uuid' => $crmContact->domain_uuid,
                'contact_uuid' => $contact->contact_uuid,
                'phone_label' => $this->blankToNull($phone->label) ?: 'main',
                'phone_number' => $number,
                'phone_primary' => $index === 0 ? 1 : null,
                'phone_type_voice' => 1,
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }

        foreach ($crmContact->emails as $index => $email) {
            $address = trim((string) $email->email_address);

            if ($address === '') {
                continue;
            }

            VContactEmail::query()->create([
                'contact_email_uuid' => (string) Str::uuid(),
                'domain_uuid' => $crmContact->domain_uuid,
                'contact_uuid' => $contact->contact_uuid,
                'email_label' => $this->blankToNull($email->label) ?: 'main',
                'email_address' => $address,
                'email_primary' => $index === 0 ? 1 : null,
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }

        foreach ($crmContact->addresses as $addressRow) {
            if ($this->addressIsEmpty($addressRow)) {
                continue;
            }

            VContactAddress::query()->create([
                'contact_address_uuid' => (string) Str::uuid(),
                'domain_uuid' => $crmContact->domain_uuid,
                'contact_uuid' => $contact->contact_uuid,
                'address_label' => $this->blankToNull($addressRow->label) ?: 'main',
                'address_street' => $this->blankToNull($addressRow->street),
                'address_extended' => $this->blankToNull($addressRow->extended),
                'address_locality' => $this->blankToNull($addressRow->city),
                'address_region' => $this->blankToNull($addressRow->region),
                'address_postal_code' => $this->blankToNull($addressRow->postal_code),
                'address_country' => $this->blankToNull($addressRow->country_code),
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }

        return $contact;
    }

    private function addressIsEmpty(object $addressRow): bool
    {
        foreach (['street', 'extended', 'city', 'region', 'postal_code', 'country_code'] as $field) {
            if (trim((string) ($addressRow->{$field} ?? '')) !== '') {
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
}
