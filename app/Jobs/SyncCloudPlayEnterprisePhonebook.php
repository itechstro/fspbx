<?php

namespace App\Jobs;

use App\Services\CloudPlayEnterpriseDirectorySync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCloudPlayEnterprisePhonebook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $domainUuid,
    ) {}

    public function handle(CloudPlayEnterpriseDirectorySync $sync): array
    {
        return $sync->bulkSyncPhonebookOnlyExtensions($this->domainUuid);
    }
}
