<?php

namespace App\Services\Provisioning;

/**
 * Map Intrade provision flash protocol settings to phone FlashProtocol values.
 *
 * Admin / settings codes (friendly): 0=TFTP, 1=FTP, 2=HTTP, 3=HTTPS
 * Phone firmware codes: 1=FTP, 2=TFTP, 4=HTTP, 5=HTTPS
 *
 * Writing admin "2" raw makes the phone show TFTP — map it to phone "4".
 */
class IntradeFlashProtocol
{
    public static function toPhone(string|int|null $value): string
    {
        $v = trim((string) ($value ?? ''));

        if ($v === '4') {
            return '4';
        }

        if ($v === '5') {
            return '5';
        }

        return match ($v) {
            '0' => '2', // TFTP
            '1' => '1', // FTP
            '2' => '4', // HTTP
            '3' => '5', // HTTPS
            default => '4',
        };
    }
}
