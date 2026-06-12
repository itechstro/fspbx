<?php

namespace App\Services\Provisioning;

class IbratroKeyXml
{
    /**
     * Fanvil/InTrade DSS key type code used in provisioning XML.
     */
    public static function typeCode(?string $deviceKeyType): string
    {
        return match ((string) $deviceKeyType) {
            '3' => '0',
            '1' => '2',
            default => '1',
        };
    }

    public static function value(?array $row): string
    {
        if (! is_array($row)) {
            return '';
        }

        $type = (string) ($row['device_key_type'] ?? '');
        if ($type === '3') {
            return '';
        }

        if ($type === '1') {
            $line = (int) ($row['device_key_line'] ?? 0);

            return $line > 0 ? 'SIP' . $line : '';
        }

        $value = (string) ($row['device_key_value'] ?? '');
        $line = (string) ($row['device_key_line'] ?? '');

        if ($value === '' && $line === '') {
            return '';
        }

        return $value . '@' . $line . '/' . $type;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rowsForPage(array $rows, int $page, int $perPage): array
    {
        $start = (($page - 1) * $perPage) + 1;
        $end = $page * $perPage;
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['device_key_id'] ?? 0);
            if ($id < $start || $id > $end) {
                continue;
            }

            $row['page_index'] = $id - $start + 1;
            $out[] = $row;
        }

        return $out;
    }
}
