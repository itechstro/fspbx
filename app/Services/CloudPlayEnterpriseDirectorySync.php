<?php

namespace App\Services;

use App\Contracts\MobileAppProviderInterface;
use App\Models\Extensions;
use App\Models\MobileAppUsers;
use App\Models\VContact;
use App\Services\Contacts\ContactUserLinkService;

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
            $resolvedEdId = $this->cloudPlay->resolveEnterpriseDirectoryId(
                $mobileApp->domain_uuid,
                (string) $extension->extension,
                (int) $mobileApp->cloudplay_ed_id,
            );

            if ($resolvedEdId === 0 && ! empty($mobileApp->cloudplay_ed_id)) {
                $mobileApp->cloudplay_ed_id = null;
                $mobileApp->save();
            }

            $edId = $this->cloudPlay->syncEnterpriseDirectory($mobileApp->domain_uuid, [
                'ed_id' => $resolvedEdId,
                'name' => $extension->effective_caller_id_name,
                'email' => app(ContactUserLinkService::class)->resolveEmailForExtension($extension),
                'extension' => $extension->extension,
                'caller_id_number' => $extension->effective_caller_id_number,
                'business_phone' => $this->cloudPlay->resolveExtensionBusinessPhoneNumber($extension),
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

        if (! app(ContactUserLinkService::class)->extensionHasDirectLinkedContactPhones($extension)) {
            $this->removePhonebookOnlyEnterpriseEntry($extension);

            return false;
        }

        $resolvedEdId = $this->cloudPlay->resolveEnterpriseDirectoryId(
            $extension->domain_uuid,
            (string) $extension->extension,
            (int) $extension->cloudplay_ed_id,
        );

        if ($resolvedEdId === 0 && ! empty($extension->cloudplay_ed_id)) {
            $extension->cloudplay_ed_id = null;
            $extension->save();
        }

        $edId = $this->cloudPlay->syncEnterpriseDirectory($extension->domain_uuid, [
            'ed_id' => $resolvedEdId,
            'name' => $extension->effective_caller_id_name,
            'email' => app(ContactUserLinkService::class)->resolveEmailForExtension($extension),
            'extension' => $extension->extension,
            'caller_id_number' => $extension->effective_caller_id_number,
            'business_phone' => $this->cloudPlay->resolveExtensionBusinessPhoneNumber($extension),
            'mobile' => $this->cloudPlay->resolveExtensionMobileNumber($extension),
            'active' => true,
        ]);

        if ($edId > 0) {
            $extension->cloudplay_ed_id = $edId;
            $extension->save();
        }

        return $edId > 0;
    }

    public function removePhonebookOnlyEnterpriseEntry(Extensions $extension): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        $extension->loadMissing('mobile_app');

        if ($extension->mobile_app) {
            return;
        }

        $edIds = $this->cloudPlay->findEnterpriseDirectoryIdsByExtension(
            $extension->domain_uuid,
            (string) $extension->extension,
        );

        if ((int) ($extension->cloudplay_ed_id ?? 0) > 0) {
            $edIds[] = (int) $extension->cloudplay_ed_id;
        }

        $edIds = array_values(array_unique(array_filter($edIds)));

        if ($edIds === []) {
            if (! empty($extension->cloudplay_ed_id)) {
                $extension->cloudplay_ed_id = null;
                $extension->save();
            }

            return;
        }

        foreach ($edIds as $edId) {
            try {
                // CloudPLAY does not support enterprise delete (HTTP 405); deactivate instead.
                $this->cloudPlay->syncEnterpriseDirectory($extension->domain_uuid, [
                    'ed_id' => $edId,
                    'name' => $extension->effective_caller_id_name,
                    'email' => $extension->email ?: '',
                    'extension' => (string) $extension->extension,
                    'caller_id_number' => $extension->effective_caller_id_number,
                    'business_phone' => '',
                    'mobile' => '',
                    'active' => false,
                ]);
            } catch (\Throwable $e) {
                logger('CloudPLAY enterprise phonebook deactivate failed for extension ' . $extension->extension . ': ' . $e->getMessage());
            }
        }

        $extension->cloudplay_ed_id = null;
        $extension->save();
    }

    public function syncPhonebookOnlyContact(VContact $contact): bool
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return false;
        }

        $link = app(ContactUserLinkService::class);
        $contact->loadMissing(['phones', 'emails']);

        if (! $link->contactHasLinkedUsers($contact)) {
            $this->removePhonebookOnlyContactEntry($contact);

            return false;
        }

        $mobile = $link->resolveMobileNumberForContact($contact);
        $work = $link->resolveWorkNumberForContact($contact);

        if ($mobile === '' && $work === '') {
            $this->removePhonebookOnlyContactEntry($contact);

            return false;
        }

        $this->deactivateLegacyContactEnterpriseEntries($contact);

        $resolvedEdId = (int) ($contact->cloudplay_ed_id ?? 0);
        $email = $link->resolveEmailForContact($contact);

        try {
            $edId = $this->cloudPlay->syncEnterpriseDirectory($contact->domain_uuid, [
                'ed_id' => $resolvedEdId,
                'name' => $contact->display_name,
                'email' => $email,
                'extension' => '',
                'caller_id_number' => $work !== '' ? $work : $mobile,
                'business_phone' => $work,
                'mobile' => $mobile,
                'active' => true,
            ]);
        } catch (\Throwable $e) {
            logger('CloudPLAY enterprise phonebook contact sync failed: ' . $e->getMessage());

            return false;
        }

        if ($edId > 0) {
            $contact->cloudplay_ed_id = $edId;
            $contact->save();
            $this->deactivateOrphanContactEnterpriseEntries($contact, $edId, $mobile);
        }

        return $edId > 0;
    }

    protected function deactivateOrphanContactEnterpriseEntries(VContact $contact, int $keepEdId, string $mobile): void
    {
        if ($mobile === '' || $keepEdId <= 0) {
            return;
        }

        $claimedEdIds = VContact::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->whereNotNull('cloudplay_ed_id')
            ->pluck('cloudplay_ed_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($this->cloudPlay->listEnterpriseDirectory($contact->domain_uuid) as $entry) {
            $edId = (int) ($entry['ed_id'] ?? 0);

            if ($edId <= 0 || $edId === $keepEdId) {
                continue;
            }

            if (($entry['ed_status'] ?? 'Y') !== 'Y') {
                continue;
            }

            if (trim((string) ($entry['ed_extension'] ?? '')) !== '') {
                continue;
            }

            $entryMobile = phoneNumberDigits((string) ($entry['ed_mobile'] ?? ''));

            if ($entryMobile === '' || $entryMobile !== phoneNumberDigits($mobile)) {
                continue;
            }

            if (in_array($edId, $claimedEdIds, true)) {
                continue;
            }

            try {
                $this->cloudPlay->syncEnterpriseDirectory($contact->domain_uuid, [
                    'ed_id' => $edId,
                    'name' => (string) ($entry['ed_name'] ?? ''),
                    'email' => '',
                    'extension' => '',
                    'caller_id_number' => '',
                    'business_phone' => '',
                    'mobile' => '',
                    'active' => false,
                ]);
            } catch (\Throwable $e) {
                logger('CloudPLAY orphan contact enterprise entry cleanup failed: ' . $e->getMessage());
            }
        }
    }

    public function removePhonebookOnlyContactEntry(VContact $contact): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        $contact->loadMissing('phones');
        $mobile = app(ContactUserLinkService::class)->resolveMobileNumberForContact($contact);

        $edIds = [];

        if ((int) ($contact->cloudplay_ed_id ?? 0) > 0) {
            $edIds[] = (int) $contact->cloudplay_ed_id;
        }

        $edIds = array_values(array_unique(array_filter($edIds)));

        if ($edIds === [] && $mobile === '') {
            if (! empty($contact->cloudplay_ed_id)) {
                $contact->cloudplay_ed_id = null;
                $contact->save();
            }

            return;
        }

        foreach ($edIds as $edId) {
            try {
                $this->cloudPlay->syncEnterpriseDirectory($contact->domain_uuid, [
                    'ed_id' => $edId,
                    'name' => $contact->display_name,
                    'email' => '',
                    'extension' => '',
                    'caller_id_number' => '',
                    'business_phone' => '',
                    'mobile' => '',
                    'active' => false,
                ]);
            } catch (\Throwable $e) {
                logger('CloudPLAY enterprise phonebook contact deactivate failed: ' . $e->getMessage());
            }
        }

        if ($mobile !== '') {
            $this->deactivateOrphanContactEnterpriseEntries($contact, 0, $mobile);
        }

        $contact->cloudplay_ed_id = null;
        $contact->save();
    }

    protected function deactivateLegacyContactEnterpriseEntries(VContact $contact): void
    {
        $legacyKey = $this->legacyContactEnterpriseExtensionKey($contact);
        $keepEdId = (int) ($contact->cloudplay_ed_id ?? 0);

        foreach ($this->cloudPlay->findEnterpriseDirectoryIdsByExtension($contact->domain_uuid, $legacyKey) as $edId) {
            if ($keepEdId > 0 && $edId === $keepEdId) {
                continue;
            }

            try {
                $this->cloudPlay->syncEnterpriseDirectory($contact->domain_uuid, [
                    'ed_id' => $edId,
                    'name' => $contact->display_name,
                    'email' => '',
                    'extension' => $legacyKey,
                    'caller_id_number' => '',
                    'business_phone' => '',
                    'mobile' => '',
                    'active' => false,
                ]);
            } catch (\Throwable $e) {
                logger('CloudPLAY legacy contact enterprise entry cleanup failed: ' . $e->getMessage());
            }
        }
    }

    protected function legacyContactEnterpriseExtensionKey(VContact $contact): string
    {
        $bucket = abs(crc32((string) $contact->contact_uuid)) % 1000;

        return '8' . str_pad((string) $bucket, 3, '0', STR_PAD_LEFT);
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
        $duplicateCleanup = $this->removeDuplicateEnterpriseEntries($domainUuid);

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
            'removed' => $reconcile['removed'] + $duplicateCleanup['removed'],
            'failed' => array_merge($reconcile['failed'], $duplicateCleanup['failed'], $failed),
        ];
    }

    /**
     * @return array{removed: int, failed: array<int, array{extension: string, message: string}>}
     */
    public function removeDuplicateEnterpriseEntries(string $domainUuid): array
    {
        $removed = 0;
        $failed = [];

        $canonicalEdIds = [];

        Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereNotNull('cloudplay_ed_id')
            ->get(['extension', 'cloudplay_ed_id'])
            ->each(function (Extensions $extension) use (&$canonicalEdIds) {
                $canonicalEdIds[(string) $extension->extension] = (int) $extension->cloudplay_ed_id;
            });

        Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereHas('mobile_app', fn ($query) => $query->whereNotNull('cloudplay_ed_id'))
            ->with('mobile_app')
            ->get()
            ->each(function (Extensions $extension) use (&$canonicalEdIds) {
                $canonicalEdIds[(string) $extension->extension] = (int) $extension->mobile_app->cloudplay_ed_id;
            });

        foreach ($this->cloudPlay->duplicateEnterpriseDirectoryEntries($domainUuid) as $duplicate) {
            $extension = $duplicate['extension'];
            $edId = $duplicate['ed_id'];
            $canonicalEdId = $canonicalEdIds[$extension] ?? 0;

            if ($canonicalEdId > 0 && $canonicalEdId === $edId) {
                continue;
            }

            try {
                $this->cloudPlay->deleteEnterpriseDirectory($domainUuid, $edId);
                $removed++;

                Extensions::query()
                    ->where('domain_uuid', $domainUuid)
                    ->where('extension', $extension)
                    ->where('cloudplay_ed_id', $edId)
                    ->update(['cloudplay_ed_id' => null]);

                MobileAppUsers::query()
                    ->where('domain_uuid', $domainUuid)
                    ->where('cloudplay_ed_id', $edId)
                    ->update(['cloudplay_ed_id' => null]);
            } catch (\Throwable $e) {
                $failed[] = [
                    'extension' => $extension,
                    'message' => $e->getMessage(),
                ];
                logger('CloudPLAY enterprise phonebook duplicate cleanup failed for extension ' . $extension . ': ' . $e->getMessage());
            }
        }

        return [
            'removed' => $removed,
            'failed' => $failed,
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

    public function clearStaleExtensionPhonebookEdId(Extensions $extension): void
    {
        if (empty($extension->cloudplay_ed_id)) {
            return;
        }

        try {
            $this->cloudPlay->deleteEnterpriseDirectory(
                $extension->domain_uuid,
                (int) $extension->cloudplay_ed_id,
            );
        } catch (\Throwable $e) {
            logger('CloudPLAY enterprise phonebook stale extension ed_id cleanup failed: ' . $e->getMessage());
        }

        $extension->cloudplay_ed_id = null;
        $extension->save();
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
