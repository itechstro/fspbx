<?php

namespace App\Services;

use Carbon\Carbon;

class DomainPresentationService
{
    public const DATE_FORMAT_SETTING = 'date_format';

    public const TIME_FORMAT_SETTING = 'time_format';

    /**
     * ISO 3166-1 alpha-2 defaults when no manual override is set.
     *
     * @var array<string, array{date: string, time: string, locale: string}>
     */
    private const COUNTRY_DEFAULTS = [
        'US' => ['date' => 'M j, Y', 'time' => 'g:i A', 'locale' => 'en-US'],
        'CA' => ['date' => 'Y-m-d', 'time' => 'g:i A', 'locale' => 'en-CA'],
        'PH' => ['date' => 'm/d/Y', 'time' => 'g:i A', 'locale' => 'en-PH'],
        'GB' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'en-GB'],
        'IE' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'en-IE'],
        'AU' => ['date' => 'd/m/Y', 'time' => 'h:mm a', 'locale' => 'en-AU'],
        'NZ' => ['date' => 'd/m/Y', 'time' => 'h:mm a', 'locale' => 'en-NZ'],
        'SG' => ['date' => 'd/m/Y', 'time' => 'g:i A', 'locale' => 'en-SG'],
        'MY' => ['date' => 'd/m/Y', 'time' => 'g:i A', 'locale' => 'en-MY'],
        'HK' => ['date' => 'd/m/Y', 'time' => 'g:i A', 'locale' => 'en-HK'],
        'IN' => ['date' => 'd/m/Y', 'time' => 'g:i A', 'locale' => 'en-IN'],
        'ZA' => ['date' => 'Y/m/d', 'time' => 'g:i A', 'locale' => 'en-ZA'],
        'DE' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'de-DE'],
        'AT' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'de-AT'],
        'CH' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'de-CH'],
        'FR' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'fr-FR'],
        'ES' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'es-ES'],
        'IT' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'it-IT'],
        'NL' => ['date' => 'd-m-Y', 'time' => 'H:i', 'locale' => 'nl-NL'],
        'BE' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'nl-BE'],
        'SE' => ['date' => 'Y-m-d', 'time' => 'H:i', 'locale' => 'sv-SE'],
        'NO' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'nb-NO'],
        'DK' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'da-DK'],
        'FI' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'fi-FI'],
        'PL' => ['date' => 'd.m.Y', 'time' => 'H:i', 'locale' => 'pl-PL'],
        'PT' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'pt-PT'],
        'BR' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'pt-BR'],
        'MX' => ['date' => 'd/m/Y', 'time' => 'g:i A', 'locale' => 'es-MX'],
        'JP' => ['date' => 'Y/m/d', 'time' => 'H:i', 'locale' => 'ja-JP'],
        'KR' => ['date' => 'Y-m-d', 'time' => 'g:i A', 'locale' => 'ko-KR'],
        'CN' => ['date' => 'Y-m-d', 'time' => 'H:i', 'locale' => 'zh-CN'],
        'TW' => ['date' => 'Y/m/d', 'time' => 'g:i A', 'locale' => 'zh-TW'],
        'TH' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'th-TH'],
        'VN' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'vi-VN'],
        'ID' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'id-ID'],
        'AE' => ['date' => 'd/m/Y', 'time' => 'g:i A', 'locale' => 'en-AE'],
        'IL' => ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'he-IL'],
    ];

    private const FALLBACK = ['date' => 'd/m/Y', 'time' => 'H:i', 'locale' => 'en-GB'];

    /**
     * @return array{
     *     country: string,
     *     locale: string,
     *     date_format: string,
     *     time_format: string,
     *     datetime_format: string,
     *     datepicker_format: string,
     *     date_format_source: string,
     *     time_format_source: string,
     * }
     */
    public function resolve(?string $domainUuid = null): array
    {
        $country = $this->countryCode($domainUuid);
        $defaults = self::COUNTRY_DEFAULTS[$country] ?? self::FALLBACK;

        $dateOverride = $this->settingValue(self::DATE_FORMAT_SETTING, $domainUuid);
        $timeOverride = $this->settingValue(self::TIME_FORMAT_SETTING, $domainUuid);

        $dateFormat = $dateOverride ?? $defaults['date'];
        $timeFormat = $timeOverride ?? $defaults['time'];
        $locale = $defaults['locale'];

        if ($country !== 'US' && ! isset(self::COUNTRY_DEFAULTS[$country])) {
            $locale = 'en-' . $country;
        }

        return [
            'country' => $country,
            'locale' => $locale,
            'date_format' => $dateFormat,
            'time_format' => $timeFormat,
            'datetime_format' => trim($dateFormat . ' ' . $timeFormat),
            'datepicker_format' => $this->datepickerFormatFromPhp($dateFormat),
            'date_format_source' => $dateOverride ? 'override' : 'country',
            'time_format_source' => $timeOverride ? 'override' : 'country',
        ];
    }

    public function formatTimestamp(?int $epoch, ?string $domainUuid, string $part = 'datetime'): ?string
    {
        if (! $epoch) {
            return null;
        }

        $presentation = $this->resolve($domainUuid);
        $timezone = get_local_time_zone($domainUuid);
        $carbon = Carbon::createFromTimestamp($epoch, 'UTC')->setTimezone($timezone);

        return match ($part) {
            'date' => $carbon->format($presentation['date_format']),
            'time' => $carbon->format($presentation['time_format']),
            default => $carbon->format($presentation['datetime_format']),
        };
    }

    public function formatCarbon(?Carbon $carbon, ?string $domainUuid, string $part = 'datetime'): ?string
    {
        if (! $carbon) {
            return null;
        }

        $presentation = $this->resolve($domainUuid);

        return match ($part) {
            'date' => $carbon->format($presentation['date_format']),
            'time' => $carbon->format($presentation['time_format']),
            default => $carbon->format($presentation['datetime_format']),
        };
    }

    protected function countryCode(?string $domainUuid): string
    {
        $code = strtoupper(trim((string) (get_domain_setting('country', $domainUuid) ?? 'US')));

        if (strlen($code) === 2 && ctype_alpha($code)) {
            return $code;
        }

        return 'US';
    }

    protected function settingValue(string $subcategory, ?string $domainUuid): ?string
    {
        $value = trim((string) (get_domain_setting($subcategory, $domainUuid) ?? ''));

        return $value !== '' ? $value : null;
    }

    protected function datepickerFormatFromPhp(string $phpFormat): string
    {
        $known = [
            'd/m/Y' => 'dd/MM/yyyy',
            'm/d/Y' => 'MM/dd/yyyy',
            'M j, Y' => 'MMM d, yyyy',
            'M d, Y' => 'MMM dd, yyyy',
            'Y-m-d' => 'yyyy-MM-dd',
            'd-m-Y' => 'dd-MM-yyyy',
            'd.m.Y' => 'dd.MM.yyyy',
            'Y/m/d' => 'yyyy/MM/dd',
            'j/n/Y' => 'd/M/yyyy',
        ];

        if (isset($known[$phpFormat])) {
            return $known[$phpFormat];
        }

        return strtr($phpFormat, [
            'Y' => 'yyyy',
            'y' => 'yy',
            'm' => 'MM',
            'n' => 'M',
            'd' => 'dd',
            'j' => 'd',
            'F' => 'MMMM',
            'M' => 'MMM',
        ]);
    }
}
