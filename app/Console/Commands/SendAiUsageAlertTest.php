<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DomainUsageLimitAlertService;
use Illuminate\Console\Command;

class SendAiUsageAlertTest extends Command
{
    protected $signature = 'fspbx:send-ai-usage-alert-test
                            {domain_uuid : Tenant domain UUID}
                            {email : Recipient email address}
                            {--level=approaching : Alert level to preview (approaching or reached)}';

    protected $description = 'Send a sample AI usage limit alert email for testing';

    public function handle(DomainUsageLimitAlertService $alertService): int
    {
        $domainUuid = (string) $this->argument('domain_uuid');
        $email = (string) $this->argument('email');
        $level = (string) $this->option('level');

        if (! Domain::query()->where('domain_uuid', $domainUuid)->exists()) {
            $this->error("Domain not found: {$domainUuid}");

            return self::FAILURE;
        }

        if (! in_array($level, ['approaching', 'reached'], true)) {
            $this->error('Level must be approaching or reached.');

            return self::FAILURE;
        }

        try {
            $alertService->sendTestAlert($domainUuid, $email, $level);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Test {$level} alert sent to {$email}.");

        return self::SUCCESS;
    }
}
