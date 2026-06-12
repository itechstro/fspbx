<?php

namespace App\Services\Contacts;

use App\Models\VContact;
use Illuminate\Support\Collection;

class ContactVcardService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $blocks = preg_split('/\R?BEGIN:VCARD\R/i', $content) ?: [];
        $contacts = [];

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '' || ! preg_match('/END:VCARD/i', $block)) {
                continue;
            }

            $payload = $this->parseCard($block);

            if ($this->cardIsEmpty($payload)) {
                continue;
            }

            $contacts[] = $payload;
        }

        return $contacts;
    }

    public function build(VContact $contact): string
    {
        return $this->buildMany(collect([$contact]));
    }

    public function buildMany(Collection $contacts): string
    {
        $cards = $contacts->map(fn (VContact $contact) => $this->buildCard($contact))->all();

        return implode("\r\n", $cards);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCard(string $block): array
    {
        $payload = [
            'contact_type' => 'individual',
            'contact_organization' => null,
            'contact_name_given' => null,
            'contact_name_family' => null,
            'contact_title' => null,
            'contact_role' => null,
            'contact_note' => null,
            'contact_time_zone' => null,
            'contact_url' => null,
            'phones' => [],
            'emails' => [],
            'addresses' => [],
            'urls' => [],
        ];

        foreach ($this->unfoldLines($block) as $line) {
            if (stripos($line, 'END:VCARD') === 0) {
                break;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$rawProperty, $value] = explode(':', $line, 2);
            $value = $this->unescape($value);
            $property = strtoupper(strtok($rawProperty, ';'));
            $params = $this->parseParams($rawProperty);

            match ($property) {
                'FN' => $this->applyFormattedName($payload, $value),
                'N' => $this->applyStructuredName($payload, $value),
                'ORG' => $payload['contact_organization'] = trim(str_replace(';', ' ', $value)) ?: null,
                'TITLE' => $payload['contact_title'] = $value ?: null,
                'ROLE' => $payload['contact_role'] = $value ?: null,
                'NOTE' => $payload['contact_note'] = $value ?: null,
                'TZ' => $payload['contact_time_zone'] = $value ?: null,
                'URL' => $this->appendUrl($payload, $value, $params),
                'EMAIL' => $this->appendEmail($payload, $value, $params),
                'TEL' => $this->appendPhone($payload, $value, $params),
                'ADR' => $this->appendAddress($payload, $value, $params),
                default => null,
            };
        }

        if ($payload['contact_organization'] && ! $payload['contact_name_given'] && ! $payload['contact_name_family']) {
            $payload['contact_type'] = 'organization';
        }

        return $payload;
    }

    private function buildCard(VContact $contact): string
    {
        $contact->loadMissing(['phones', 'emails', 'addresses', 'urls', 'notes']);

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $this->escape($contact->display_name),
            'N:' . $this->escape(implode(';', [
                $contact->contact_name_family ?? '',
                $contact->contact_name_given ?? '',
            ])),
        ];

        if ($contact->contact_organization) {
            $lines[] = 'ORG:' . $this->escape($contact->contact_organization);
        }

        foreach ([
            'contact_title' => 'TITLE',
            'contact_role' => 'ROLE',
            'contact_note' => 'NOTE',
            'contact_time_zone' => 'TZ',
            'contact_url' => 'URL',
        ] as $field => $property) {
            if (! empty($contact->{$field})) {
                $lines[] = $property . ':' . $this->escape((string) $contact->{$field});
            }
        }

        foreach ($contact->notes as $note) {
            if ($note->contact_note && $note->contact_note !== $contact->contact_note) {
                $lines[] = 'NOTE:' . $this->escape($note->contact_note);
            }
        }

        foreach ($contact->phones as $phone) {
            $type = $this->phoneLabelToVcardType($phone->phone_label);
            $lines[] = 'TEL;TYPE=' . $type . ':' . $this->escape($phone->phone_number);
        }

        foreach ($contact->emails as $email) {
            $suffix = $this->toFlag($email->email_primary) ? ';TYPE=INTERNET,PREF' : ';TYPE=INTERNET';
            $lines[] = 'EMAIL' . $suffix . ':' . $this->escape($email->email_address);
        }

        foreach ($contact->addresses as $address) {
            $type = $address->address_label ?: 'work';
            $lines[] = 'ADR;TYPE=' . strtoupper($type) . ':;;'
                . $this->escape((string) ($address->address_street ?? '')) . ';'
                . $this->escape((string) ($address->address_locality ?? '')) . ';'
                . $this->escape((string) ($address->address_region ?? '')) . ';'
                . $this->escape((string) ($address->address_postal_code ?? '')) . ';'
                . $this->escape((string) ($address->address_country ?? ''));
        }

        foreach ($contact->urls as $url) {
            if ($url->url_address) {
                $lines[] = 'URL:' . $this->escape($url->url_address);
            }
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyFormattedName(array &$payload, string $value): void
    {
        if ($payload['contact_name_given'] || $payload['contact_name_family'] || $payload['contact_organization']) {
            return;
        }

        $parts = preg_split('/\s+/', trim($value)) ?: [];

        if (count($parts) === 1) {
            $payload['contact_organization'] = $parts[0];

            return;
        }

        $payload['contact_name_family'] = array_pop($parts);
        $payload['contact_name_given'] = implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyStructuredName(array &$payload, string $value): void
    {
        $parts = explode(';', $value);
        $payload['contact_name_family'] = trim($parts[0] ?? '') ?: null;
        $payload['contact_name_given'] = trim($parts[1] ?? '') ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $params
     */
    private function appendPhone(array &$payload, string $value, array $params): void
    {
        $number = trim($value);

        if ($number === '') {
            return;
        }

        $payload['phones'][] = [
            'phone_number' => $number,
            'phone_label' => $this->vcardTypeToPhoneLabel($params),
            'phone_type_voice' => 1,
            'phone_primary' => empty($payload['phones']) ? 1 : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $params
     */
    private function appendEmail(array &$payload, string $value, array $params): void
    {
        $address = trim($value);

        if ($address === '') {
            return;
        }

        $payload['emails'][] = [
            'email_address' => $address,
            'email_label' => $this->hasParam($params, 'HOME') ? 'home' : 'work',
            'email_primary' => $this->hasParam($params, 'PREF') || empty($payload['emails']) ? 1 : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $params
     */
    private function appendAddress(array &$payload, string $value, array $params): void
    {
        $parts = explode(';', $value);
        $street = trim($parts[2] ?? '');
        $locality = trim($parts[3] ?? '');
        $region = trim($parts[4] ?? '');
        $postal = trim($parts[5] ?? '');
        $country = trim($parts[6] ?? '');

        if ($street === '' && $locality === '' && $region === '' && $postal === '' && $country === '') {
            return;
        }

        $payload['addresses'][] = [
            'address_label' => $this->hasParam($params, 'HOME') ? 'home' : 'work',
            'address_street' => $street ?: null,
            'address_locality' => $locality ?: null,
            'address_region' => $region ?: null,
            'address_postal_code' => $postal ?: null,
            'address_country' => $country ?: null,
            'address_primary' => empty($payload['addresses']) ? 1 : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $params
     */
    private function appendUrl(array &$payload, string $value, array $params): void
    {
        $address = trim($value);

        if ($address === '') {
            return;
        }

        if (! $payload['contact_url']) {
            $payload['contact_url'] = $address;
        }

        $payload['urls'][] = [
            'url_address' => $address,
            'url_type' => $this->hasParam($params, 'HOME') ? 'home' : 'work',
            'url_primary' => empty($payload['urls']) ? 1 : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function unfoldLines(string $content): array
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $unfolded = [];
        $current = '';

        foreach ($lines as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $current .= substr($line, 1);

                continue;
            }

            if ($current !== '') {
                $unfolded[] = $current;
            }

            $current = $line;
        }

        if ($current !== '') {
            $unfolded[] = $current;
        }

        return $unfolded;
    }

    /**
     * @return array<int, string>
     */
    private function parseParams(string $rawProperty): array
    {
        $parts = explode(';', $rawProperty);
        array_shift($parts);

        $params = [];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [, $value] = explode('=', $part, 2);
                foreach (explode(',', strtoupper($value)) as $token) {
                    $params[] = trim($token);
                }
            } else {
                $params[] = strtoupper(trim($part));
            }
        }

        return $params;
    }

    /**
     * @param  array<int, string>  $params
     */
    private function hasParam(array $params, string $needle): bool
    {
        return in_array(strtoupper($needle), $params, true);
    }

    /**
     * @param  array<int, string>  $params
     */
    private function vcardTypeToPhoneLabel(array $params): ?string
    {
        if ($this->hasParam($params, 'CELL') || $this->hasParam($params, 'MOBILE')) {
            return 'mobile';
        }

        if ($this->hasParam($params, 'HOME')) {
            return 'home';
        }

        if ($this->hasParam($params, 'WORK')) {
            return 'work';
        }

        if ($this->hasParam($params, 'FAX')) {
            return 'fax';
        }

        return 'other';
    }

    private function phoneLabelToVcardType(?string $label): string
    {
        return match (strtolower((string) $label)) {
            'mobile' => 'CELL',
            'home' => 'HOME',
            'work' => 'WORK',
            'fax' => 'FAX',
            default => 'VOICE',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cardIsEmpty(array $payload): bool
    {
        $organization = trim((string) ($payload['contact_organization'] ?? ''));
        $given = trim((string) ($payload['contact_name_given'] ?? ''));
        $family = trim((string) ($payload['contact_name_family'] ?? ''));

        return $organization === '' && $given === '' && $family === '' && empty($payload['phones']) && empty($payload['emails']);
    }

    private function escape(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r", ';', ','], ['\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\n', '\\N', '\\;', '\\,'], ["\n", "\n", ';', ','], $value);
    }

    private function toFlag(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }
}
