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

        if (in_array($type, ['mwi', 'headset', 'redial'], true)) {
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

        if ($deviceKeyType === 'redial') {
            return 'F_REDIAL';
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
            'bd' => '/bd',
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
     * Merge keyed provisioning rows without PHP array_merge() renumbering.
     * Later row sets override earlier rows at the same index.
     *
     * @param  array<int, array<string, mixed>>  ...$rowSets
     * @return array<int, array<string, mixed>>
     */
    public static function mergeKeyedRows(array ...$rowSets): array
    {
        $merged = [];

        foreach ($rowSets as $rows) {
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $merged[(int) $index] = $row;
            }
        }

        ksort($merged, SORT_NUMERIC);

        return $merged;
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

            if (! is_array($row)) {
                $slots[] = [
                    'index' => $position,
                    'row' => null,
                ];
                continue;
            }

            // Blank value/label with a leftover type (e.g. park) must clear on the phone.
            $computedValue = self::value($row);
            $slots[] = [
                'index' => $position,
                'row' => $computedValue === '' ? null : $row,
            ];
        }

        return $slots;
    }

    /**
     * Build side-key slots for one page. Empty / unset indexes emit a clear
     * (Type 0) so removing a key in admin actually clears it on the phone.
     * Fanvil/Intrade keep the previous value when a key is omitted from the cfg.
     *
     * @return array<int, array{index: int, row: array<string, mixed>}>
     */
    public static function configuredSideSlots(array $rows, int $perPage): array
    {
        $slots = [];

        for ($index = 1; $index <= $perPage; $index++) {
            $row = $rows[$index] ?? null;
            if (! is_array($row)) {
                $slots[] = [
                    'index' => $index,
                    'row' => self::clearedRow(),
                ];
                continue;
            }

            $type = (string) ($row['device_key_type'] ?? '3');
            $computedValue = self::value($row);
            $shouldClear = $type === '3' || $type === '' || $computedValue === '';

            $slots[] = [
                'index' => $index,
                'row' => $shouldClear ? self::clearedRow() : $row,
            ];
        }

        return $slots;
    }

    /**
     * Entry pages 2–3: admin memory indexes pack 2 configurable keys per page
     * (1→2-1, 2→2-2, 3→3-1, 4→3-2). Phone Fkey 3 is the page switch — never emitted.
     *
     * @param  array<int, array<string, mixed>>  $memoryKeys
     * @return array<int, array{index: int, row: array<string, mixed>}>
     */
    public static function entryExtraSideSlots(array $memoryKeys, int $page, int $configurablePerPage = 2): array
    {
        $base = ($page - 2) * $configurablePerPage;
        $slots = [];

        for ($position = 1; $position <= $configurablePerPage; $position++) {
            $globalId = $base + $position;
            $row = $memoryKeys[$globalId] ?? null;

            if (! is_array($row)) {
                $slots[] = [
                    'index' => $position,
                    'row' => self::clearedRow(),
                ];
                continue;
            }

            $type = (string) ($row['device_key_type'] ?? '3');
            $computedValue = self::value($row);
            $shouldClear = $type === '3' || $type === '' || $computedValue === '';

            $slots[] = [
                'index' => $position,
                'row' => $shouldClear ? self::clearedRow() : $row,
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
     * @return ?array{sip_slots: array<int, int>, mwi_index: ?int, headset_index: ?int, redial_index?: ?int}
     */
    public static function sideKeyDefaultPlan(string $profile): ?array
    {
        return match ($profile) {
            // Keys 1–2 are SIP defaults; key 3 on each page is the page switch.
            'entry' => [
                'sip_slots' => [1, 2],
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
            // Factory Intrade Video (2.6): SIP1–SIP5, key 6 Headset, key 7 Redial.
            'video' => [
                'sip_slots' => [1, 2, 3, 4, 5],
                'mwi_index' => null,
                'headset_index' => 6,
                'redial_index' => 7,
            ],
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineKeys
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, bool>  $protectedIndexes  Side-key indexes with user-defined labels to keep
     * @return array<int, array<string, mixed>>
     */
    public static function applyProfileSideDefaults(
        string $profile,
        array $lineKeys,
        array $lines = [],
        array $protectedIndexes = []
    ): array {
        $plan = self::sideKeyDefaultPlan($profile);
        if ($plan === null) {
            return $lineKeys;
        }

        foreach ($plan['sip_slots'] as $lineNumber => $index) {
            $sipLine = $lineNumber + 1;

            // Only create SIP keys for configured lines (avoids inactive SIP2+ on Video).
            if (! self::lineIsConfigured($lines[$sipLine] ?? null)) {
                continue;
            }

            $defaultRow = self::defaultSipSideKey($index, $sipLine, $lines);

            if (! isset($lineKeys[$index])) {
                $lineKeys[$index] = $defaultRow;
                continue;
            }

            if (! empty($protectedIndexes[$index])) {
                continue;
            }

            $row = $lineKeys[$index];
            $type = (string) ($row['device_key_type'] ?? '');
            // Explicit clear stays cleared — do not re-apply SIP.
            if ($type === '3' || $type === '') {
                continue;
            }
            if ($type !== '1') {
                continue;
            }

            $rowLine = (int) ($row['device_key_line'] ?? 0);
            if ($rowLine !== 0 && $rowLine !== $sipLine) {
                continue;
            }

            $lineKeys[$index]['device_key_line'] = $sipLine;
            $lineKeys[$index]['device_key_label'] = $defaultRow['device_key_label'];
        }

        $mwiIndex = $plan['mwi_index'];
        if ($mwiIndex !== null && ! isset($lineKeys[$mwiIndex])) {
            $lineKeys[$mwiIndex] = self::legacyRow($mwiIndex, 'mwi', 'F_MWI', 0, 'Voice Mail');
        }

        $headsetIndex = $plan['headset_index'];
        if ($headsetIndex !== null && ! isset($lineKeys[$headsetIndex])) {
            $lineKeys[$headsetIndex] = self::legacyRow($headsetIndex, 'headset', 'F_HEADSET', 0, 'Headset');
        }

        $redialIndex = $plan['redial_index'] ?? null;
        if ($redialIndex !== null && ! isset($lineKeys[$redialIndex])) {
            $lineKeys[$redialIndex] = self::legacyRow($redialIndex, 'redial', 'F_REDIAL', 0, 'Redial');
        }

        ksort($lineKeys, SORT_NUMERIC);

        return $lineKeys;
    }

    /**
     * @param  array<string, mixed>|null  $line
     */
    private static function lineIsConfigured(?array $line): bool
    {
        if ($line === null) {
            return false;
        }

        return trim((string) ($line['user_id'] ?? '')) !== ''
            || trim((string) ($line['auth_id'] ?? '')) !== ''
            || trim((string) ($line['password'] ?? '')) !== '';
    }

    /**
     * Label for a SIP line side key from the current line account data.
     *
     * @param  array<string, mixed>  $line
     */
    public static function lineSideKeyLabel(array $line, ?int $lineNumber = null): string
    {
        $displayName = trim((string) ($line['display_name'] ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }

        $authId = trim((string) ($line['auth_id'] ?? $line['user_id'] ?? ''));
        if ($authId !== '') {
            return $authId;
        }

        return $lineNumber ? 'SIP' . $lineNumber : '';
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
        $label = self::lineSideKeyLabel($line, $lineNumber);

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
