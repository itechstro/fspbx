<?php

namespace App\Services\Provisioning;

use Illuminate\Http\Request;

class ProvisionDeviceResolver
{
    public static function tokenFromRequest(Request $request): ?string
    {
        foreach (['mac', 'address'] as $param) {
            $token = self::normalizeMac((string) $request->query($param, ''));
            if ($token) {
                return $token;
            }
        }

        $name = (string) $request->query('name', '');
        if (str_starts_with(strtoupper($name), 'SEP') && strlen($name) >= 15) {
            $token = self::normalizeMac(substr($name, 3, 12));
            if ($token) {
                return $token;
            }
        }

        return self::tokenFromUserAgent((string) $request->userAgent());
    }

    public static function normalizeMac(string $value): ?string
    {
        $compact = strtolower(preg_replace('/[^a-f0-9]/', '', $value));

        return preg_match('/^[0-9a-f]{12}$/', $compact) ? $compact : null;
    }

    public static function tokenFromUserAgent(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        if (str_starts_with($userAgent, 'Aastra') && preg_match('/MAC:([A-F0-9-]{17})/', $userAgent, $matches)) {
            return self::normalizeMac($matches[1]);
        }

        if (str_starts_with($userAgent, 'AUDC-IPPhone')) {
            return self::normalizeMac(substr($userAgent, -13));
        }

        if (str_starts_with($userAgent, 'Fanvil') || str_starts_with($userAgent, 'Ibratro')) {
            return self::normalizeMac(substr($userAgent, -13));
        }

        if (str_starts_with(strtolower($userAgent), 'flyingvoice')) {
            return self::normalizeMac(substr($userAgent, -17));
        }

        if (str_starts_with($userAgent, 'Grandstream')) {
            return self::normalizeMac(substr($userAgent, -12));
        }

        if (str_starts_with($userAgent, 'Htek')) {
            return self::normalizeMac(substr($userAgent, -17));
        }

        if (str_starts_with($userAgent, 'Panasonic')) {
            return self::normalizeMac(substr($userAgent, -14));
        }

        if (str_starts_with(strtolower($userAgent), 'yealink') || str_starts_with(strtolower($userAgent), 'vp530')) {
            if (str_contains(substr($userAgent, -4), ':')) {
                return self::normalizeMac(substr($userAgent, -17));
            }

            return self::normalizeMac(substr($userAgent, -12));
        }

        return null;
    }

    public static function isContactDirectoryId(string $id): bool
    {
        $id = strtolower(basename($id));

        return in_array($id, ['directory', 'phonebook'], true)
            || preg_match('/^[0-9a-f]{12}-(?:directory|phonebook)$/', $id) === 1;
    }
}
