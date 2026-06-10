<?php

namespace App\Services;

use App\Contracts\MobileAppProviderInterface;
use App\Models\Extensions;
use App\Models\MobileAppUsers;

class CloudPlayEnterpriseDirectorySync
{
    public function __construct(
        protected CloudPlayApiService $cloudPlay,
    ) {}

    public function sync(
        MobileAppProviderInterface $provider,
        ?Extensions $extension,
        ?MobileAppUsers $mobileApp,
        ?bool $active = null,
    ): void {
        if ($provider->getProviderKey() !== 'cloudplay' || !$mobileApp) {
            return;
        }

        $active ??= (int) $mobileApp->status === 1;

        if (!$active && empty($mobileApp->cloudplay_ed_id)) {
            return;
        }

        if (!$extension) {
            $extension = Extensions::query()
                ->where('extension_uuid', $mobileApp->extension_uuid)
                ->first();
        }

        if (!$extension) {
            return;
        }

        try {
            $edId = $this->cloudPlay->syncEnterpriseDirectory($mobileApp->domain_uuid, [
                'ed_id' => $mobileApp->cloudplay_ed_id,
                'name' => $extension->effective_caller_id_name,
                'email' => $extension->email ?: '',
                'extension' => $extension->extension,
                'caller_id_number' => $extension->effective_caller_id_number,
                'mobile' => $this->cloudPlay->resolveExtensionMobileNumber($extension),
                'active' => $active,
            ]);

            if ($edId > 0 && (int) $mobileApp->cloudplay_ed_id !== $edId) {
                $mobileApp->cloudplay_ed_id = $edId;
                $mobileApp->save();
            }
        } catch (\Throwable $e) {
            logger('CloudPLAY enterprise phonebook sync failed: ' . $e->getMessage());
        }
    }

    public function syncForExtension(Extensions $extension, ?bool $active = null): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        $extension->loadMissing(['mobile_app']);

        if (!$extension->mobile_app) {
            return;
        }

        $this->sync(
            provider: app(CloudPlayApiService::class),
            extension: $extension,
            mobileApp: $extension->mobile_app,
            active: $active,
        );
    }

    public function remove(MobileAppProviderInterface $provider, ?MobileAppUsers $mobileApp): void
    {
        if ($provider->getProviderKey() !== 'cloudplay' || !$mobileApp || empty($mobileApp->cloudplay_ed_id)) {
            return;
        }

        try {
            $this->cloudPlay->deleteEnterpriseDirectory(
                $mobileApp->domain_uuid,
                (int) $mobileApp->cloudplay_ed_id
            );
        } catch (\Throwable $e) {
            logger('CloudPLAY enterprise phonebook delete failed: ' . $e->getMessage());
        }
    }
}
