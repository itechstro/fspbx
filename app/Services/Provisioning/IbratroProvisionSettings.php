<?php

namespace App\Services\Provisioning;

/**
 * @deprecated Use IntradeProvisionSettings. Kept for historical update classes.
 */
class IbratroProvisionSettings
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return IntradeProvisionSettings::definitions();
    }
}
