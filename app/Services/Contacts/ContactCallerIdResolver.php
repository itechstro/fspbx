<?php

namespace App\Services\Contacts;

use App\Models\CDR;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContactCallerIdResolver
{
    private const SUFFIX_MATCH_LENGTH = 8;

    /**
     * @param  Collection<int, CDR>  $cdrs
     */
    public function enrichCollection(Collection $cdrs): void
    {
        if ($cdrs->isEmpty()) {
            return;
        }

        $lookupsByDomain = [];
        $callerPending = [];
        $dialedPending = [];
        $recipientPending = [];

        foreach ($cdrs as $cdr) {
            $dialed = trim((string) ($cdr->caller_destination ?? ''));

            if ($dialed !== '') {
                $dialedPending[] = $cdr;

                foreach ($this->lookupKeysForNumber((string) $cdr->domain_uuid, $dialed) as $key) {
                    $lookupsByDomain[$key] = true;
                }
            }

            $recipientNumbers = $this->recipientLookupNumbers($cdr);

            if ($recipientNumbers !== []) {
                $recipientPending[] = [$cdr, $recipientNumbers];

                foreach ($recipientNumbers as $number) {
                    foreach ($this->lookupKeysForNumber((string) $cdr->domain_uuid, $number) as $key) {
                        $lookupsByDomain[$key] = true;
                    }
                }
            }

            if (! $this->needsContactLookup($cdr)) {
                continue;
            }

            $numbers = $this->lookupNumbersForCdr($cdr);

            if ($numbers === []) {
                continue;
            }

            $callerPending[] = [$cdr, $numbers];

            foreach ($numbers as $number) {
                foreach ($this->lookupKeysForNumber((string) $cdr->domain_uuid, $number) as $key) {
                    $lookupsByDomain[$key] = true;
                }
            }
        }

        if ($lookupsByDomain === []) {
            return;
        }

        $lookupKeys = array_keys($lookupsByDomain);
        $nameMap = $this->buildExtensionNameMap($lookupKeys);

        if ($this->contactsAvailable()) {
            $nameMap += $this->buildContactNameMap($lookupKeys);
        }

        foreach ($callerPending as [$cdr, $numbers]) {
            foreach ($numbers as $number) {
                $name = $this->resolveNameFromMap(
                    (string) $cdr->domain_uuid,
                    $number,
                    $nameMap
                );

                if ($name !== null) {
                    $cdr->setAttribute('resolved_caller_id_name', $name);
                    break;
                }
            }
        }

        foreach ($dialedPending as $cdr) {
            $dialed = trim((string) ($cdr->caller_destination ?? ''));
            $name = $this->resolveNameFromMap((string) $cdr->domain_uuid, $dialed, $nameMap);

            if ($name !== null) {
                $cdr->setAttribute('resolved_dialed_name', $name);
            }
        }

        foreach ($recipientPending as [$cdr, $numbers]) {
            foreach ($numbers as $number) {
                $name = $this->resolveNameFromMap((string) $cdr->domain_uuid, $number, $nameMap);

                if ($name !== null) {
                    $cdr->setAttribute('resolved_recipient_name', $name);
                    break;
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function recipientLookupNumbers(CDR $cdr): array
    {
        $recipient = trim((string) ($cdr->destination_number ?? ''));

        if ($recipient === '') {
            return [];
        }

        $numbers = [$recipient];

        if (str_starts_with($recipient, '*99')) {
            $extension = substr($recipient, 3);

            if ($extension !== '') {
                $numbers[] = $extension;
            }
        }

        return array_values(array_unique($numbers));
    }

    private function contactsAvailable(): bool
    {
        return Schema::hasTable('v_contacts') && Schema::hasTable('v_contact_phones');
    }

    public function needsContactLookup(CDR $cdr): bool
    {
        $name = trim((string) ($cdr->caller_id_name ?? ''));

        if ($name === '') {
            return true;
        }

        if ($this->nameLooksLikePhone($name)) {
            return true;
        }

        if ($this->isNumericCallerIdName($name)) {
            return true;
        }

        return $this->nameMatchesCdrNumber($cdr, $name);
    }

    /**
     * @return array<int, string>
     */
    private function lookupNumbersForCdr(CDR $cdr): array
    {
        $numbers = [];

        foreach (['caller_id_number', 'caller_destination'] as $field) {
            $value = trim((string) ($cdr->{$field} ?? ''));

            if ($value !== '') {
                $numbers[] = $value;
            }
        }

        $name = trim((string) ($cdr->caller_id_name ?? ''));

        if ($name !== '' && ($this->isNumericCallerIdName($name) || $this->nameLooksLikePhone($name))) {
            $numbers[] = $name;
        }

        return array_values(array_unique($numbers));
    }

    /**
     * @param  array<int, string>  $lookupKeys
     * @return array<string, string>
     */
    private function buildExtensionNameMap(array $lookupKeys): array
    {
        if (! Schema::hasTable('v_extensions')) {
            return [];
        }

        $targetsByDomain = $this->groupLookupTargetsByDomain($lookupKeys);
        $map = [];

        foreach ($targetsByDomain as $domainUuid => $digitsSet) {
            $targets = array_keys($digitsSet);
            $fullDigits = [];
            $suffixDigits = [];

            foreach ($targets as $digits) {
                $fullDigits[] = $digits;

                if (strlen($digits) >= self::SUFFIX_MATCH_LENGTH) {
                    $suffixDigits[] = substr($digits, -self::SUFFIX_MATCH_LENGTH);
                }
            }

            $fullDigits = array_values(array_unique($fullDigits));
            $suffixDigits = array_values(array_unique($suffixDigits));

            $query = DB::table('v_extensions')
                ->where('domain_uuid', $domainUuid)
                ->select([
                    'extension',
                    'number_alias',
                    'effective_caller_id_name',
                    'effective_caller_id_number',
                ]);

            $query->where(function ($where) use ($targets, $fullDigits, $suffixDigits) {
                $hasClause = false;

                if ($targets !== []) {
                    $where->where(function ($extensionQuery) use ($targets) {
                        $extensionQuery->whereIn('extension', $targets)
                            ->orWhereIn('number_alias', $targets);
                    });
                    $hasClause = true;
                }

                if ($fullDigits !== []) {
                    $method = $hasClause ? 'orWhereIn' : 'whereIn';
                    $where->{$method}(
                        DB::raw("regexp_replace(effective_caller_id_number, '[^0-9]', '', 'g')"),
                        $fullDigits
                    );
                    $hasClause = true;
                }

                if ($suffixDigits !== []) {
                    $method = $hasClause ? 'orWhereIn' : 'whereIn';
                    $where->{$method}(
                        DB::raw('right(regexp_replace(effective_caller_id_number, \'[^0-9]\', \'\', \'g\'), ' . self::SUFFIX_MATCH_LENGTH . ')'),
                        $suffixDigits
                    );
                }
            });

            foreach ($query->get() as $row) {
                $displayName = $this->formatExtensionDisplayName($row);

                if ($displayName === '') {
                    continue;
                }

                foreach ($this->extensionLookupValues($row) as $value) {
                    $this->indexPhoneDigits((string) $domainUuid, phoneNumberDigits($value), $displayName, $map);

                    if (trim((string) $value) !== phoneNumberDigits($value)) {
                        $map[$this->mapKey((string) $domainUuid, trim((string) $value))] = $displayName;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $lookupKeys
     * @return array<string, string>
     */
    private function buildContactNameMap(array $lookupKeys): array
    {
        $targetsByDomain = $this->groupLookupTargetsByDomain($lookupKeys);
        $map = [];

        foreach ($targetsByDomain as $domainUuid => $digitsSet) {
            $targets = array_keys($digitsSet);
            $fullDigits = [];
            $suffixDigits = [];

            foreach ($targets as $digits) {
                $fullDigits[] = $digits;

                if (strlen($digits) >= self::SUFFIX_MATCH_LENGTH) {
                    $suffixDigits[] = substr($digits, -self::SUFFIX_MATCH_LENGTH);
                }
            }

            $fullDigits = array_values(array_unique($fullDigits));
            $suffixDigits = array_values(array_unique($suffixDigits));

            $query = DB::table('v_contact_phones as p')
                ->join('v_contacts as c', 'c.contact_uuid', '=', 'p.contact_uuid')
                ->where('p.domain_uuid', $domainUuid)
                ->where('c.domain_uuid', $domainUuid)
                ->select([
                    'p.phone_number',
                    'p.phone_extension',
                    'c.contact_name_given',
                    'c.contact_name_family',
                    'c.contact_organization',
                    'p.phone_primary',
                    'p.insert_date',
                ])
                ->orderByDesc('p.phone_primary')
                ->orderBy('p.insert_date');

            $query->where(function ($where) use ($targets, $fullDigits, $suffixDigits) {
                $hasClause = false;

                if ($targets !== []) {
                    $where->where(function ($targetQuery) use ($targets) {
                        $targetQuery->whereIn('p.phone_extension', $targets)
                            ->orWhereIn(
                                DB::raw("regexp_replace(p.phone_number, '[^0-9]', '', 'g')"),
                                $targets
                            );
                    });
                    $hasClause = true;
                }

                if ($fullDigits !== []) {
                    $method = $hasClause ? 'orWhere' : 'where';
                    $where->{$method}(function ($digitQuery) use ($fullDigits) {
                        $digitQuery->whereIn(
                            DB::raw("regexp_replace(p.phone_number, '[^0-9]', '', 'g')"),
                            $fullDigits
                        )->orWhereIn(
                            DB::raw("regexp_replace(p.phone_extension, '[^0-9]', '', 'g')"),
                            $fullDigits
                        )->orWhereIn('p.phone_extension', $fullDigits);
                    });
                    $hasClause = true;
                }

                if ($suffixDigits !== []) {
                    $method = $hasClause ? 'orWhere' : 'where';
                    $where->{$method}(function ($suffixQuery) use ($suffixDigits) {
                        $suffixQuery->whereIn(
                            DB::raw('right(regexp_replace(p.phone_number, \'[^0-9]\', \'\', \'g\'), ' . self::SUFFIX_MATCH_LENGTH . ')'),
                            $suffixDigits
                        )->orWhereIn(
                            DB::raw('right(regexp_replace(p.phone_extension, \'[^0-9]\', \'\', \'g\'), ' . self::SUFFIX_MATCH_LENGTH . ')'),
                            $suffixDigits
                        );
                    });
                }
            });

            foreach ($query->get() as $row) {
                $displayName = $this->formatContactDisplayName($row);

                if ($displayName === '') {
                    continue;
                }

                foreach ($this->contactPhoneLookupValues($row) as $value) {
                    $this->indexPhoneDigits((string) $domainUuid, phoneNumberDigits($value), $displayName, $map);

                    if (trim($value) !== phoneNumberDigits($value)) {
                        $map[$this->mapKey((string) $domainUuid, trim($value))] = $displayName;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $lookupKeys
     * @return array<string, array<string, bool>>
     */
    private function groupLookupTargetsByDomain(array $lookupKeys): array
    {
        $targetsByDomain = [];

        foreach ($lookupKeys as $key) {
            [$domainUuid, $digits] = explode(':', $key, 2);
            $targetsByDomain[$domainUuid][$digits] = true;
        }

        return $targetsByDomain;
    }

    /**
     * @return array<int, string>
     */
    private function extensionLookupValues(object $row): array
    {
        return array_values(array_filter([
            trim((string) ($row->extension ?? '')),
            trim((string) ($row->number_alias ?? '')),
            trim((string) ($row->effective_caller_id_number ?? '')),
        ]));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function resolveNameFromMap(string $domainUuid, string $number, array $map): ?string
    {
        foreach ($this->lookupKeysForNumber($domainUuid, $number) as $key) {
            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function lookupKeysForNumber(string $domainUuid, string $number): array
    {
        $keys = [];
        $variants = [
            phoneNumberDigits($number),
            ltrim(formatContactPhoneE164($number, $domainUuid), '+'),
        ];

        foreach ($variants as $digits) {
            if ($digits === '') {
                continue;
            }

            $keys[] = $this->mapKey($domainUuid, $digits);

            if (strlen($digits) >= self::SUFFIX_MATCH_LENGTH) {
                $keys[] = $this->mapKey($domainUuid, substr($digits, -self::SUFFIX_MATCH_LENGTH));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function indexPhoneDigits(string $domainUuid, string $phoneDigits, string $displayName, array &$map): void
    {
        if ($phoneDigits === '') {
            return;
        }

        $keys = [$this->mapKey($domainUuid, $phoneDigits)];

        if (strlen($phoneDigits) >= self::SUFFIX_MATCH_LENGTH) {
            $keys[] = $this->mapKey($domainUuid, substr($phoneDigits, -self::SUFFIX_MATCH_LENGTH));
        }

        foreach ($keys as $key) {
            if (! isset($map[$key])) {
                $map[$key] = $displayName;
            }
        }
    }

    private function mapKey(string $domainUuid, string $digits): string
    {
        return $domainUuid . ':' . $digits;
    }

    private function formatContactDisplayName(object $row): string
    {
        $name = trim(trim((string) ($row->contact_name_given ?? '')) . ' ' . trim((string) ($row->contact_name_family ?? '')));

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($row->contact_organization ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private function contactPhoneLookupValues(object $row): array
    {
        return array_values(array_filter([
            trim((string) ($row->phone_number ?? '')),
            trim((string) ($row->phone_extension ?? '')),
        ]));
    }

    private function formatExtensionDisplayName(object $row): string
    {
        $extension = trim((string) ($row->extension ?? ''));
        $name = trim((string) ($row->effective_caller_id_name ?? ''));

        if ($name !== '') {
            return $extension !== '' ? $extension . ' - ' . $name : $name;
        }

        return $extension;
    }

    private function nameLooksLikePhone(?string $name): bool
    {
        $name = trim((string) $name);

        if ($name === '') {
            return false;
        }

        if (preg_match('/^(?:\+?1)?[2-9]\d{9}$/', $name)) {
            return true;
        }

        $digits = phoneNumberDigits($name);

        return (bool) preg_match('/^1?[2-9]\d{9}$/', $digits);
    }

    private function isNumericCallerIdName(string $name): bool
    {
        $digits = phoneNumberDigits($name);

        return $digits !== '' && ctype_digit($digits);
    }

    private function nameMatchesCdrNumber(CDR $cdr, string $name): bool
    {
        $nameDigits = phoneNumberDigits($name);

        if ($nameDigits === '') {
            return false;
        }

        foreach (['caller_id_number', 'caller_destination'] as $field) {
            $fieldDigits = phoneNumberDigits((string) ($cdr->{$field} ?? ''));

            if ($fieldDigits === '') {
                continue;
            }

            if ($nameDigits === $fieldDigits) {
                return true;
            }

            if (
                strlen($nameDigits) >= self::SUFFIX_MATCH_LENGTH
                && strlen($fieldDigits) >= self::SUFFIX_MATCH_LENGTH
                && substr($nameDigits, -self::SUFFIX_MATCH_LENGTH) === substr($fieldDigits, -self::SUFFIX_MATCH_LENGTH)
            ) {
                return true;
            }
        }

        return false;
    }
}
