<?php

namespace App\Services\Provisioning;

/**
 * Resolves TalkPhone account options.
 *
 * Device settings win, then domain settings named talkphone_*, then defaults.
 * An explicit empty voicemail number is kept so the phone can hide the button.
 */
class TalkPhoneSettings
{
    public const DEFAULT_CODECS = ['opus', 'G722', 'PCMU', 'PCMA'];

    public const DEVICE_KEYS = [
        'transport',
        'port',
        'ws_path',
        'media_transport',
        'voicemail_number',
        'codecs',
        'allow_bad_certificate',
        'register_expires',
    ];

    private const CODEC_ALIASES = [
        'opus' => 'opus',
        'g722' => 'G722',
        'g.722' => 'G722',
        'pcmu' => 'PCMU',
        'g711u' => 'PCMU',
        'g.711u' => 'PCMU',
        'pcma' => 'PCMA',
        'g711a' => 'PCMA',
        'g.711a' => 'PCMA',
    ];

    /**
     * @param  array<string, mixed>  $device  enabled device setting subcategory => value
     * @param  array<string, mixed>  $domain  domain setting subcategory => value
     * @return array{
     *     transport: string,
     *     port: int,
     *     wsPath: string,
     *     mediaTransport: string,
     *     voicemailNumber: string,
     *     codecs: list<string>,
     *     allowBadCertificate: bool,
     *     registerExpires: int
     * }
     */
    public static function resolve(array $device, array $domain): array
    {
        $transport = self::transport(self::pick($device, 'transport', $domain));
        $port = self::port(self::pick($device, 'port', $domain), $transport);
        $wsPath = self::wsPath(self::pick($device, 'ws_path', $domain));
        $media = self::mediaTransport(self::pick($device, 'media_transport', $domain));
        $voicemail = self::pick($device, 'voicemail_number', $domain);
        $codecs = self::codecs(self::pick($device, 'codecs', $domain));
        $allowBad = self::pick($device, 'allow_bad_certificate', $domain);
        $expires = self::pick($device, 'register_expires', $domain);

        return [
            'transport' => $transport,
            'port' => $port,
            'wsPath' => $wsPath,
            'mediaTransport' => $media,
            'voicemailNumber' => $voicemail['set'] ? trim((string) $voicemail['value']) : '*97',
            'codecs' => $codecs,
            'allowBadCertificate' => $allowBad['set'] ? self::truthy($allowBad['value']) : false,
            'registerExpires' => self::expires($expires),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings  resolved options from self::resolve()
     * @return array<string, mixed>
     */
    public static function payload(
        string $displayName,
        string $sipUri,
        string $password,
        string $server,
        string $mac,
        array $settings
    ): array {
        return [
            'v' => 1,
            'app' => 'talkphone',
            'displayName' => $displayName,
            'sipUri' => $sipUri,
            'password' => $password,
            'server' => $server,
            'mac' => $mac,
            'port' => $settings['port'],
            'transport' => $settings['transport'],
            'wsPath' => $settings['wsPath'],
            'mediaTransport' => $settings['mediaTransport'],
            'pbxType' => 'fspbx',
            'voicemailNumber' => $settings['voicemailNumber'],
            'codecs' => $settings['codecs'],
            'allowBadCertificate' => $settings['allowBadCertificate'],
            'registerExpires' => $settings['registerExpires'],
        ];
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return array<string, mixed>
     */
    public static function mapDeviceSettings(iterable $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $enabled = $row->device_setting_enabled ?? 'true';
            if (is_string($enabled) && ! in_array(strtolower($enabled), ['true', '1'], true)) {
                continue;
            }
            if ($enabled === false || $enabled === 0) {
                continue;
            }
            $key = strtolower(trim((string) ($row->device_setting_subcategory ?? '')));
            if ($key === '') {
                continue;
            }
            $map[$key] = $row->device_setting_value;
        }

        return $map;
    }

    /**
     * @return array{set: bool, value: mixed}
     */
    private static function pick(array $device, string $key, array $domain): array
    {
        foreach ([$key, 'talkphone_'.$key] as $candidate) {
            if (! array_key_exists($candidate, $device)) {
                continue;
            }
            if ($key !== 'voicemail_number' && self::blank($device[$candidate])) {
                continue;
            }

            return ['set' => true, 'value' => $device[$candidate]];
        }
        $prefixed = 'talkphone_'.$key;
        if (array_key_exists($prefixed, $domain) && ($key === 'voicemail_number' || ! self::blank($domain[$prefixed]))) {
            return ['set' => true, 'value' => $domain[$prefixed]];
        }

        return ['set' => false, 'value' => null];
    }

    private static function blank(mixed $value): bool
    {
        return trim((string) $value) === '';
    }

    private static function transport(array $picked): string
    {
        $value = strtoupper(trim((string) ($picked['value'] ?? '')));

        return in_array($value, ['WSS', 'WS', 'TLS', 'TCP'], true) ? $value : 'WSS';
    }

    private static function port(array $picked, string $transport): int
    {
        if ($picked['set'] && is_numeric($picked['value'])) {
            $port = (int) $picked['value'];
            if ($port >= 1 && $port <= 65535) {
                return $port;
            }
        }

        return match ($transport) {
            'WS' => 5066,
            'TCP' => 5060,
            'TLS' => 5061,
            default => 7443,
        };
    }

    private static function wsPath(array $picked): string
    {
        if (! $picked['set']) {
            return '';
        }
        $path = trim((string) $picked['value']);
        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    private static function mediaTransport(array $picked): string
    {
        $value = strtoupper(trim((string) ($picked['value'] ?? '')));

        return $value === 'RTP' ? 'RTP' : 'SRTP';
    }

    /**
     * @return list<string>
     */
    private static function codecs(array $picked): array
    {
        if (! $picked['set']) {
            return self::DEFAULT_CODECS;
        }

        $raw = $picked['value'];
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/\s*,\s*/', trim((string) $raw)) ?: [];
        }

        $codecs = [];
        foreach ($parts as $part) {
            $alias = self::CODEC_ALIASES[strtolower(trim((string) $part))] ?? null;
            if ($alias !== null && ! in_array($alias, $codecs, true)) {
                $codecs[] = $alias;
            }
        }

        return $codecs === [] ? self::DEFAULT_CODECS : $codecs;
    }

    private static function expires(array $picked): int
    {
        if ($picked['set'] && is_numeric($picked['value'])) {
            $expires = (int) $picked['value'];
            if ($expires >= 60 && $expires <= 86400) {
                return $expires;
            }
        }

        return 600;
    }

    /**
     * 12 lowercase hex digits, or null when the value is not a MAC.
     */
    public static function normalizeMac(string $raw): ?string
    {
        $hex = strtolower((string) preg_replace('/[^0-9a-f]/i', '', $raw));
        if (! preg_match('/^[0-9a-f]{12}$/', $hex)) {
            return null;
        }
        if ($hex === '000000000000' || $hex === '020000000000') {
            return null;
        }

        return $hex;
    }

    /**
     * Locally administered unicast address. The phone adopts this value
     * from the provisioning link and sends it on the phone-book download.
     */
    public static function generateMac(): string
    {
        $bytes = random_bytes(6);
        $bytes[0] = chr((ord($bytes[0]) | 0x02) & 0xFE);

        return bin2hex($bytes);
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
