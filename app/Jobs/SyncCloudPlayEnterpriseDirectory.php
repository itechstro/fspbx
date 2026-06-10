<?php

namespace App\Jobs;

use App\Models\Extensions;
use App\Services\CloudPlayEnterpriseDirectorySync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCloudPlayEnterpriseDirectory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $extensionUuid,
        public ?bool $active = null,
    ) {}

    public function handle(CloudPlayEnterpriseDirectorySync $sync): void
    {
        $extension = Extensions::query()
            ->where('extension_uuid', $this->extensionUuid)
            ->first();

        if (!$extension) {
            return;
        }

        $sync->syncForExtension($extension, $this->active);
    }
}
