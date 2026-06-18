<?php

namespace App\Services\Provisioning;

class IntradeKeyXml
{
    /**
     * Fanvil/InTrade DSS key type code used in provisioning XML.
     *
     * Cleared => 0, SIP line => 2, built-in functions (MWI/headset) => 3,
     * BLF/speed dial/etc => 1.
     */
    public static function typeCode(?string $deviceKeyType): string
    {
        $type = (string) $deviceKeyType;

        if ($type === '3' || $type === '') {
            return '0';
        }

        if ($type === '1') {
            return '2';
        }

        if (in_array($type, ['mwi', 'headset'], true)) {
            return '3';
        }

        return '1';
    }

    public static function value(?array $row): string
    {
        if (! is_array($row)) {
            return '';
        }

        $deviceKeyType = (string) ($row['device_key_type'] ?? '');
        if ($deviceKeyType === '3') {
            return '';
        }

        if ($deviceKeyType === '1') {
            $line = (int) ($row['device_key_line'] ?? 0);

            return $line > 0 ? 'SIP' . $line : '';
        }

        if ($deviceKeyType === 'mwi') {
            return 'F_MWI';
        }

        if ($deviceKeyType === 'headset') {
            return 'F_HEADSET';
        }

        $value = trim((string) ($row['device_key_value'] ?? ''));
        $line = max(1, (int) ($row['device_key_line'] ?? 1));

        if ($deviceKeyType === 'f' && $value === '') {
            return '@' . $line . '/f';
        }

        if ($value === '') {
            return '';
        }

        $suffix = self::valueSuffix($deviceKeyType);

        return $value . '@' . $line . $suffix;
    }

    private static function valueSuffix(string $deviceKeyType): string
    {
        return match ($deviceKeyType) {
            'ba' => '/ba',
            'bb' => '/bb',
            'bf' => '/bf',
            'bc', 'bcV' => '/bc',
            'a' => '/a',
            'c' => '/c',
            'i' => '/i',
            'f' => '/f',
            default => '/' . $deviceKeyType,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rowsForPage(array $rows, int $page, int $perPage): array
    {
        $out = [];

        foreach (self::slotsForPage($rows, $page, $perPage) as $slot) {
            if (! is_array($slot['row'] ?? null)) {
                continue;
            }

            $row = $slot['row'];
            $row['page_index'] = $slot['index'];
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Return every slot on a page, including cleared positions.
     *
     * @return array<int, array{index: int, row: ?array<string, mixed>}>
     */
    public static function slotsForPage(array $rows, int $page, int $perPage): array
    {
        $start = (($page - 1) * $perPage) + 1;
        $slots = [];

        for ($position = 1; $position <= $perPage; $position++) {
            $globalId = $start + $position - 1;
            $row = $rows[$globalId] ?? null;

            $slots[] = [
                'index' => $position,
                'row' => is_array($row) ? $row : null,
            ];
        }

        return $slots;
    }

    /**
     * Emit only configured side-key slots (Fanvil x7 dssSide behavior).
     * Missing indexes are omitted so reprovision does not clear unused side slots.
     *
     * @return array<int, array{index: int, row: array<string, mixed>}>
     */
    public static function configuredSideSlots(array $rows, int $perPage): array
    {
        $slots = [];

        for ($index = 1; $index <= $perPage; $index++) {
            $row = $rows[$index] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $type = (string) ($row['device_key_type'] ?? '3');
            $slots[] = [
                'index' => $index,
                'row' => $type === '3' ? self::clearedRow() : $row,
            ];
        }

        return $slots;
    }

    /**
     * @return array<int, array{index: int, row: ?array<string, mixed>}>
     */
    public static function sideSlots(array $rows, int $perPage, int $pages = 1): array
    {
        $slots = [];

        for ($page = 1; $page <= $pages; $page++) {
            foreach (self::slotsForPage($rows, $page, $perPage) as $slot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Factory side-key defaults per InTrade model profile.
     *
     * @return ?array{sip_slots: array<int, int>, mwi_index: ?int, headset_index: ?int}
     */
    public static function sideKeyDefaultPlan(string $profile): ?array
    {
        return match ($profile) {
            // 3 physical side keys: SIP1-3 only.
            'entry' => [
                'sip_slots' => [1, 2, 3],
                'mwi_index' => null,
                'headset_index' => null,
            ],
            // 7 side keys on page 1: SIP1-6 plus voice mail on the last key.
            'standard' => [
                'sip_slots' => [1, 2, 3, 4, 5, 6],
                'mwi_index' => 7,
                'headset_index' => null,
            ],
            // 11 side keys: SIP1-6, voice mail, headset.
            'advanced' => [
                'sip_slots' => [1, 2, 3, 4, 5, 6],
                'mwi_index' => 7,
                'headset_index' => 8,
            ],
            // Screen DSS page 1 (internal keys): same first eight slots as Advanced.
            'video' => [
                'sip_slots' => [1, 2, 3, 4, 5, 6],
                'mwi_index' => 7,
                'headset_index' => 8,
            ],
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineKeys
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public static function applyProfileSideDefaults(string $profile, array $lineKeys, array $lines = []): array
    {
        $plan = self::sideKeyDefaultPlan($profile);
        if ($plan === null) {
            return $lineKeys;
        }

        foreach ($plan['sip_slots'] as $lineNumber => $index) {
            if (isset($lineKeys[$index])) {
                continue;
            }

            $lineKeys[$index] = self::defaultSipSideKey($index, $lineNumber + 1, $lines);
        }

        $mwiIndex = $plan['mwi_index'];
        if ($mwiIndex !== null && ! isset($lineKeys[$mwiIndex])) {
            $lineKeys[$mwiIndex] = self::legacyRow($mwiIndex, 'mwi', 'F_MWI', 0, 'Voice Mail');
        }

        $headsetIndex = $plan['headset_index'];
        if ($headsetIndex !== null && ! isset($lineKeys[$headsetIndex])) {
            $lineKeys[$headsetIndex] = self::legacyRow($headsetIndex, 'headset', 'F_HEADSET', 0, 'Headset');
        }

        ksort($lineKeys, SORT_NUMERIC);

        return $lineKeys;
    }

    /**
     * @deprecated Use applyProfileSideDefaults() with profile "advanced".
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public static function applyAdvancedSideDefaults(array $lineKeys, array $lines = []): array
    {
        return self::applyProfileSideDefaults('advanced', $lineKeys, $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private static function defaultSipSideKey(int $index, int $lineNumber, array $lines): array
    {
        $line = $lines[$lineNumber] ?? [];
        $label = (string) ($line['display_name'] ?? $line['auth_id'] ?? $line['user_id'] ?? '');

        return self::legacyRow($index, '1', '', $lineNumber, $label);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return ?array<string, mixed>
     */
    public static function defaultAdvancedSideKey(int $index, array $lines = []): ?array
    {
        $plan = self::sideKeyDefaultPlan('advanced');
        if ($plan === null) {
            return null;
        }

        if (in_array($index, $plan['sip_slots'], true)) {
            $lineNumber = array_search($index, $plan['sip_slots'], true);

            return self::defaultSipSideKey($index, $lineNumber + 1, $lines);
        }

        if ($index === $plan['mwi_index']) {
            return self::legacyRow($index, 'mwi', 'F_MWI', 0, 'Voice Mail');
        }

        if ($index === $plan['headset_index']) {
            return self::legacyRow($index, 'headset', 'F_HEADSET', 0, 'Headset');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function legacyRow(
        int $index,
        string $type,
        string $value = '',
        int $line = 0,
        string $label = ''
    ): array {
        return [
            'device_key_id' => $index,
            'device_key_category' => 'line',
            'device_key_vendor' => 'intrade',
            'device_key_type' => $type,
            'device_key_subtype' => '',
            'device_key_line' => $line,
            'device_key_value' => $value,
            'device_key_extension' => '',
            'device_key_protected' => '',
            'device_key_label' => $label,
            'device_key_icon' => 'Green',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clearedRow(): array
    {
        return [
            'device_key_type' => '3',
            'device_key_value' => '',
            'device_key_label' => '',
            'device_key_line' => 0,
            'device_key_icon' => 'Green',
        ];
    }

    public static function lineNumber(?array $row): int
    {
        if (! is_array($row)) {
            return 0;
        }

        return max(0, (int) ($row['device_key_line'] ?? 0));
    }
}
