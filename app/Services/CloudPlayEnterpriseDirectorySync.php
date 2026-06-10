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

    public function syncPhonebookOnlyExtension(Extensions $extension): bool
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return false;
        }

        $extension->loadMissing(['mobile_app', 'voicemail']);

        if ($extension->mobile_app) {
            return false;
        }

        $edId = $this->cloudPlay->syncEnterpriseDirectory($extension->domain_uuid, [
            'ed_id' => $extension->cloudplay_ed_id,
            'name' => $extension->effective_caller_id_name,
            'email' => $extension->email ?: '',
            'extension' => $extension->extension,
            'caller_id_number' => $extension->effective_caller_id_number,
            'mobile' => $this->cloudPlay->resolveExtensionMobileNumber($extension),
            'active' => true,
        ]);

        if ($edId > 0 && (int) $extension->cloudplay_ed_id !== $edId) {
            $extension->cloudplay_ed_id = $edId;
            $extension->save();
        }

        return $edId > 0;
    }

    public function bulkSyncPhonebookOnlyExtensions(string $domainUuid): array
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            throw new \RuntimeException('CloudPLAY is not the configured mobile app provider.');
        }

        $credentials = $this->cloudPlay->getCustomerCredentials($domainUuid);

        if ($credentials['username'] === '' || $credentials['password'] === '') {
            throw new \RuntimeException('CloudPLAY customer credentials are not configured for this tenant.');
        }

        $reconcile = $this->reconcileStalePhonebookExtensions($domainUuid);

        $extensions = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('directory_visible', ['true', '1'])
            ->whereDoesntHave('mobile_app')
            ->orderBy('extension')
            ->get();

        $synced = 0;
        $skipped = 0;
        $failed = [];

        foreach ($extensions as $extension) {
            try {
                if ($this->syncPhonebookOnlyExtension($extension)) {
                    $synced++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed[] = [
                    'extension' => $extension->extension,
                    'message' => $e->getMessage(),
                ];
                logger('CloudPLAY enterprise phonebook bulk sync failed for extension ' . $extension->extension . ': ' . $e->getMessage());
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'removed' => $reconcile['removed'],
            'failed' => array_merge($reconcile['failed'], $failed),
        ];
    }

    public function reconcileStalePhonebookExtensions(string $domainUuid): array
    {
        $removed = 0;
        $failed = [];

        $staleExtensions = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereNotNull('cloudplay_ed_id')
            ->where(function ($query) {
                $query->whereNotIn('directory_visible', ['true', '1'])
                    ->orWhereHas('mobile_app');
            })
            ->get();

        foreach ($staleExtensions as $extension) {
            try {
                $this->cloudPlay->deleteEnterpriseDirectory($domainUuid, (int) $extension->cloudplay_ed_id);
                $extension->cloudplay_ed_id = null;
                $extension->save();
                $removed++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'extension' => $extension->extension,
                    'message' => $e->getMessage(),
                ];
                logger('CloudPLAY enterprise phonebook reconcile failed for extension ' . $extension->extension . ': ' . $e->getMessage());
            }
        }

        $extensionNumbers = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->pluck('extension_uuid', 'extension');

        $phonebookOnlyNumbers = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('directory_visible', ['true', '1'])
            ->whereDoesntHave('mobile_app')
            ->pluck('extension')
            ->flip();

        $mobileAppEdIdsByExtension = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereHas('mobile_app', fn ($query) => $query->whereNotNull('cloudplay_ed_id'))
            ->with('mobile_app')
            ->get()
            ->mapWithKeys(fn (Extensions $extension) => [
                (string) $extension->extension => (int) $extension->mobile_app->cloudplay_ed_id,
            ]);

        try {
            $entries = $this->cloudPlay->listEnterpriseDirectory($domainUuid);
        } catch (\Throwable $e) {
            logger('CloudPLAY enterprise phonebook list failed during reconcile: ' . $e->getMessage());

            return [
                'removed' => $removed,
                'failed' => array_merge($failed, [[
                    'extension' => '*',
                    'message' => 'Could not list CloudPLAY enterprise directory: ' . $e->getMessage(),
                ]]),
            ];
        }

        foreach ($entries as $entry) {
            $edId = (int) ($entry['ed_id'] ?? 0);
            $extensionNumber = (string) ($entry['ed_extension'] ?? $entry['ed_blf_prefix'] ?? '');

            if ($edId <= 0 || $extensionNumber === '') {
                continue;
            }

            if ($mobileAppEdIdsByExtension->has($extensionNumber)
                && $mobileAppEdIdsByExtension->get($extensionNumber) === $edId) {
                continue;
            }

            $shouldRemove = !$extensionNumbers->has($extensionNumber)
                || !$phonebookOnlyNumbers->has($extensionNumber);

            if (!$shouldRemove) {
                continue;
            }

            try {
                $this->cloudPlay->deleteEnterpriseDirectory($domainUuid, $edId);
                $removed++;

                Extensions::query()
                    ->where('domain_uuid', $domainUuid)
                    ->where('extension', $extensionNumber)
                    ->where('cloudplay_ed_id', $edId)
                    ->update(['cloudplay_ed_id' => null]);
            } catch (\Throwable $e) {
                $failed[] = [
                    'extension' => $extensionNumber,
                    'message' => $e->getMessage(),
                ];
                logger('CloudPLAY enterprise phonebook reconcile failed for extension ' . $extensionNumber . ': ' . $e->getMessage());
            }
        }

        return [
            'removed' => $removed,
            'failed' => $failed,
        ];
    }

    public function adoptExtensionEdId(Extensions $extension, MobileAppUsers $mobileApp): void
    {
        if (empty($extension->cloudplay_ed_id)) {
            return;
        }

        $mobileApp->cloudplay_ed_id = $extension->cloudplay_ed_id;
        $extension->cloudplay_ed_id = null;
        $extension->save();
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
