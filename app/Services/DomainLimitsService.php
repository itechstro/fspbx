<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\MobileAppUsers;
use Illuminate\Support\Str;

class DomainLimitsService
{
    public function metrics(): array
    {
        $configured = (array) config('domain_limits.metrics', []);
        $knownKeys = array_keys($configured);

        $extraKeys = DefaultSettings::query()
            ->where('default_setting_category', 'limit')
            ->where('default_setting_name', 'numeric')
            ->whereNotIn('default_setting_subcategory', $knownKeys)
            ->orderBy('default_setting_subcategory')
            ->pluck('default_setting_subcategory')
            ->all();

        $extras = [];
        foreach ($extraKeys as $key) {
            $extras[$key] = [
                'group' => 'Other limits',
                'display' => $this->humanizeKey($key),
                'unit' => '',
                'usage_type' => 'unknown',
            ];
        }

        return array_merge($configured, $extras);
    }

    public function metric(string $limitKey): ?array
    {
        $metrics = $this->metrics();

        return $metrics[$limitKey] ?? null;
    }

    public function ledgerMetrics(): array
    {
        return collect($this->metrics())
            ->filter(fn (array $meta) => ($meta['usage_type'] ?? '') === 'ledger')
            ->mapWithKeys(function (array $meta, string $key) {
                return [
                    $key => [
                        'ledger_metric' => (string) ($meta['ledger_metric'] ?? ''),
                        'unit' => (string) ($meta['unit'] ?? ''),
                        'display' => (string) ($meta['display'] ?? $key),
                        'scale' => (float) ($meta['scale'] ?? 1),
                    ],
                ];
            })
            ->all();
    }

    public function resolveUsage(string $limitKey, string $domainUuid, ?string $period, DomainUsageService $domainUsageService): float
    {
        $meta = $this->metric($limitKey);
        if (! $meta) {
            return 0.0;
        }

        if (($meta['usage_type'] ?? '') === 'count') {
            $model = (string) ($meta['model'] ?? '');
            if ($model === '' || ! class_exists($model)) {
                return 0.0;
            }

            if ($limitKey === 'mobile_app_users') {
                return (float) MobileAppUsers::countActiveLicensesForDomain($domainUuid);
            }

            $query = $model::query()->where('domain_uuid', $domainUuid);

            $scope = (string) ($meta['count_scope'] ?? '');
            if ($scope !== '') {
                $scopeMethod = 'scope' . Str::studly($scope);
                if (method_exists($model, $scopeMethod)) {
                    $query->{$scope}();
                }
            }

            return (float) $query->count();
        }

        if (($meta['usage_type'] ?? '') === 'ledger') {
            $metric = (string) ($meta['ledger_metric'] ?? '');

            return $metric !== ''
                ? $domainUsageService->getUsage($metric, $domainUuid, $period)
                : 0.0;
        }

        return 0.0;
    }

    public function isMonthly(string $limitKey): bool
    {
        $meta = $this->metric($limitKey);

        return ($meta['usage_type'] ?? '') === 'ledger';
    }

    protected function humanizeKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }
}
