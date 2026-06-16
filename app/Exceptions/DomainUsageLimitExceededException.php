<?php

namespace App\Exceptions;

use RuntimeException;

class DomainUsageLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $metric,
        public readonly string $limitKey,
        public readonly float $limitValue,
        public readonly float $currentUsage,
        public readonly float $proposedAmount = 0,
        ?string $message = null,
    ) {
        parent::__construct($message ?: sprintf(
            'Domain usage limit exceeded for %s (limit: %s, current: %s).',
            $metric,
            $this->formatAmount($limitValue, $limitKey),
            $this->formatAmount($currentUsage, $limitKey),
        ));
    }

    public function toErrorBag(): array
    {
        return [
            $this->limitKey => [$this->getMessage()],
        ];
    }

    protected function formatAmount(float $value, string $limitKey): string
    {
        $config = config("domain_limits.metrics.{$limitKey}", []);
        $unit = (string) ($config['unit'] ?? '');
        $scale = (float) ($config['scale'] ?? 1);

        if ($unit === 'minutes' && $scale > 0) {
            return number_format($value / $scale, 2) . ' min';
        }

        if ($unit === 'usd') {
            return '$' . number_format($value, 4);
        }

        return number_format($value, 2);
    }
}
