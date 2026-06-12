<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class Update202
{
    private const VERSION = '1.8.8.9';

    public function apply(): bool
    {
        try {
            $this->seedYealinkAndGrandstreamTemplates();
            $this->refreshProvisionTemplateLinks();
            echo 'Update ' . self::VERSION . " completed successfully.\n";

            return true;
        } catch (Throwable $exception) {
            echo 'Error applying update ' . self::VERSION . ": {$exception->getMessage()}\n";

            return false;
        }
    }

    private function seedYealinkAndGrandstreamTemplates(): void
    {
        foreach (['yealink', 'grandstream'] as $vendor) {
            $exitCode = Artisan::call('prov:templates:seed', [
                '--vendor' => $vendor,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException(trim(Artisan::output()) ?: "prov:templates:seed --vendor={$vendor} failed");
            }

            echo trim(Artisan::output()) . "\n";
        }

        echo "Reseeded Yealink and Grandstream default provisioning templates for /prov contact directory URLs.\n";
    }

    private function refreshProvisionTemplateLinks(): void
    {
        $exitCode = Artisan::call('provisioning:link-templates', [
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'provisioning:link-templates failed');
        }

        echo trim(Artisan::output()) . "\n";
        echo "Refreshed legacy provisioning template symlinks.\n";
    }
}
