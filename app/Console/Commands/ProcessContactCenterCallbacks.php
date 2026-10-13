<?php

namespace App\Console\Commands;

use App\Services\ContactCenter\ContactCenterRuntime;
use Illuminate\Console\Command;

class ProcessContactCenterCallbacks extends Command
{
    protected $signature = 'contact-center:callbacks';

    protected $description = 'Call agents and then customers for due Contact Center callbacks.';

    public function handle(ContactCenterRuntime $runtime): int
    {
        $started = $runtime->processDue();
        $this->info('Started '.$started.' callback'.($started === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
