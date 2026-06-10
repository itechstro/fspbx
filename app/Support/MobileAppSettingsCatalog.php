<?php

namespace App\Support;

class MobileAppSettingsCatalog
{
    public static function scope(string $subcategory): string
    {
        $scopes = config('mobile_app_settings.scopes', []);

        foreach ($scopes as $scope => $subcategories) {
            if (in_array($subcategory, $subcategories, true)) {
                return $scope;
            }
        }

        return 'legacy';
    }

    public static function isHidden(string $subcategory): bool
    {
        return in_array($subcategory, config('mobile_app_settings.hidden_subcategories', []), true);
    }

    public static function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'shared' => 'Shared',
            'cloudplay' => 'CloudPLAY',
            default => '',
        };
    }

    public static function label(string $subcategory): string
    {
        return config("mobile_app_settings.labels.{$subcategory}")
            ?? str($subcategory)->replace(['_', '-'], ' ')->title()->toString();
    }

    public static function description(string $subcategory, ?string $existing = null): ?string
    {
        $configured = config("mobile_app_settings.descriptions.{$subcategory}");

        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        return $existing;
    }
}
