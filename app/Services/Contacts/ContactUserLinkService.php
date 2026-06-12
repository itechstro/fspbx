<?php

namespace App\Services\Contacts;

use App\Models\ExtensionUser;
use App\Models\Extensions;
use App\Models\SpeedDialUser;
use App\Models\User;
use App\Models\VContact;
use App\Services\CloudPlayApiService;
use App\Services\CloudPlayEnterpriseDirectorySync;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContactUserLinkService
{
    public function resolvePhonebookContactForUser(User $user): ?VContact
    {
        if (! empty($user->contact_uuid)) {
            $contact = VContact::query()
                ->where('domain_uuid', $user->domain_uuid)
                ->whereKey($user->contact_uuid)
                ->with('phones')
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        $assignment = SpeedDialUser::query()
            ->where('domain_uuid', $user->domain_uuid)
            ->where('user_uuid', $user->user_uuid)
            ->whereIn('contact_uuid', function ($query) use ($user) {
                $query->select('contact_uuid')
                    ->from('v_contacts')
                    ->where('domain_uuid', $user->domain_uuid);
            })
            ->orderBy('insert_date')
            ->first();

        if (! $assignment) {
            return null;
        }

        return VContact::query()
            ->where('domain_uuid', $user->domain_uuid)
            ->whereKey($assignment->contact_uuid)
            ->with('phones')
            ->first();
    }

    public function resolveMobileNumberForUser(User $user): string
    {
        $contact = $this->resolvePhonebookContactForUser($user);

        return $contact ? $this->resolveMobileNumberForContact($contact) : '';
    }

    public function resolveWorkNumberForUser(User $user): string
    {
        $contact = $this->resolvePhonebookContactForUser($user);

        return $contact ? $this->resolveWorkNumberForContact($contact) : '';
    }

    public function resolveMobileNumberForExtension(Extensions $extension): string
    {
        return $this->resolvePhoneNumberForExtension($extension, 'resolveMobileNumberForUser');
    }

    public function resolveWorkNumberForExtension(Extensions $extension): string
    {
        return $this->resolvePhoneNumberForExtension($extension, 'resolveWorkNumberForUser');
    }

    public function resolveMobileNumberForContact(VContact $contact): string
    {
        return $this->resolvePhoneNumberForContactByLabel($contact, 'mobile');
    }

    public function resolveWorkNumberForContact(VContact $contact): string
    {
        return $this->resolvePhoneNumberForContactByLabel($contact, 'work');
    }

    public function resolvePhoneNumberForContactByLabel(VContact $contact, string $label): string
    {
        $contact->loadMissing('phones');
        $label = strtolower($label);

        foreach ($contact->phones as $phone) {
            if (strtolower((string) $phone->phone_label) !== $label) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', (string) $phone->phone_number);

            if ($digits !== '') {
                return $digits;
            }
        }

        return '';
    }

    /**
     * @return Collection<int, User>
     */
    public function usersForExtension(Extensions $extension): Collection
    {
        $extension->loadMissing(['extension_users.user']);

        $users = collect();

        foreach ($extension->extension_users as $extensionUser) {
            if ($extensionUser->user) {
                $users->push($extensionUser->user);
            }
        }

        $directUsers = User::query()
            ->where('domain_uuid', $extension->domain_uuid)
            ->where('extension_uuid', $extension->extension_uuid)
            ->get();

        return $users
            ->merge($directUsers)
            ->unique('user_uuid')
            ->values();
    }

    private function resolvePhoneNumberForExtension(Extensions $extension, string $userResolver): string
    {
        foreach ($this->usersForExtension($extension) as $user) {
            $number = $this->{$userResolver}($user);

            if ($number !== '') {
                return $number;
            }
        }

        return '';
    }

    public function syncCloudPlayForContact(VContact $contact): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        app(CloudPlayApiService::class)->clearEnterpriseDirectoryCache();

        $sync = app(CloudPlayEnterpriseDirectorySync::class);
        $extensions = $this->extensionsForContact($contact);

        if ($extensions->isNotEmpty()) {
            $extensions->each(fn (Extensions $extension) => $this->syncCloudPlayForExtension($extension));
            $sync->removePhonebookOnlyContactEntry($contact);

            return;
        }

        if ($this->contactHasLinkedUsers($contact)) {
            $sync->syncPhonebookOnlyContact($contact);

            return;
        }

        $sync->removePhonebookOnlyContactEntry($contact);
    }

    public function contactHasLinkedUsers(VContact $contact): bool
    {
        if (User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->exists()) {
            return true;
        }

        return SpeedDialUser::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->exists();
    }

    public function syncCloudPlayForUser(User $user): void
    {
        $this->syncCloudPlayAfterUserExtensionChange($user);
    }

    public function syncCloudPlayAfterUserExtensionChange(User $user, ?string $previousExtensionUuid = null): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        app(CloudPlayApiService::class)->clearEnterpriseDirectoryCache();

        if (
            $previousExtensionUuid
            && $previousExtensionUuid !== $user->extension_uuid
        ) {
            $previousExtension = Extensions::query()
                ->where('domain_uuid', $user->domain_uuid)
                ->where('extension_uuid', $previousExtensionUuid)
                ->first();

            if ($previousExtension) {
                $this->releaseExtensionContactPhones($previousExtension);
            }
        }

        $extensionUuids = $this->extensionsForUser($user)->pluck('extension_uuid');

        if ($previousExtensionUuid) {
            $extensionUuids->push($previousExtensionUuid);
        }

        $contact = $this->resolvePhonebookContactForUser($user);

        if ($contact) {
            $extensionUuids = $extensionUuids->merge(
                $this->extensionsForContact($contact)->pluck('extension_uuid')
            );
        }

        $extensionUuids = $extensionUuids->unique()->values();

        if ($extensionUuids->isEmpty()) {
            if ($contact) {
                $this->syncCloudPlayForContact($contact);
            }

            return;
        }

        $this->syncCloudPlayForExtensionUuids(
            $extensionUuids->all(),
            $user->domain_uuid,
            $previousExtensionUuid,
        );

        if ($contact) {
            if ($user->extension_uuid) {
                app(CloudPlayEnterpriseDirectorySync::class)->removePhonebookOnlyContactEntry($contact);
            } else {
                $this->syncCloudPlayForContact($contact);
            }
        }

        app(CloudPlayEnterpriseDirectorySync::class)
            ->removeDuplicateEnterpriseEntries($user->domain_uuid);
    }

    /**
     * @param  array<int, string>  $extensionUuids
     */
    public function syncCloudPlayForExtensionUuids(
        array $extensionUuids,
        ?string $domainUuid = null,
        ?string $previousExtensionUuid = null,
    ): void {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        $extensionUuids = collect($extensionUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values();

        if ($extensionUuids->isEmpty()) {
            return;
        }

        if ($previousExtensionUuid) {
            $extensionUuids = collect([$previousExtensionUuid])
                ->merge($extensionUuids->reject(fn ($uuid) => $uuid === $previousExtensionUuid))
                ->values();
        }

        $query = Extensions::query()->whereIn('extension_uuid', $extensionUuids->all());

        if ($domainUuid) {
            $query->where('domain_uuid', $domainUuid);
        }

        $extensions = $query->get()->keyBy('extension_uuid');

        foreach ($extensionUuids as $extensionUuid) {
            $extension = $extensions->get($extensionUuid);

            if ($extension) {
                $this->syncCloudPlayForExtension($extension);
            }
        }
    }

    public function extensionHasLinkedContactPhones(Extensions $extension): bool
    {
        return $this->resolveMobileNumberForExtension($extension) !== ''
            || $this->resolveWorkNumberForExtension($extension) !== '';
    }

    public function releaseExtensionContactPhones(Extensions $extension): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        $sync = app(CloudPlayEnterpriseDirectorySync::class);
        $extension->loadMissing('mobile_app');

        if ($extension->mobile_app) {
            $sync->syncForExtension($extension);
            $sync->clearStaleExtensionPhonebookEdId($extension);

            return;
        }

        $sync->removePhonebookOnlyEnterpriseEntry($extension);
    }

    public function syncCloudPlayForExtension(Extensions $extension): void
    {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        $sync = app(CloudPlayEnterpriseDirectorySync::class);
        $extension->loadMissing('mobile_app');

        if ($extension->mobile_app) {
            $sync->syncForExtension($extension);
            $sync->clearStaleExtensionPhonebookEdId($extension);

            return;
        }

        if (! $this->extensionHasLinkedContactPhones($extension)) {
            $sync->removePhonebookOnlyEnterpriseEntry($extension);

            return;
        }

        $sync->syncPhonebookOnlyExtension($extension);
    }

    /**
     * @return Collection<int, Extensions>
     */
    public function extensionsForContact(VContact $contact): Collection
    {
        $userUuids = User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->pluck('user_uuid');

        $assignedUserUuids = SpeedDialUser::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->pluck('user_uuid');

        $userUuids = $userUuids->merge($assignedUserUuids)->unique()->values();

        if ($userUuids->isEmpty()) {
            return collect();
        }

        $extensionUuids = ExtensionUser::query()
            ->whereIn('user_uuid', $userUuids)
            ->pluck('extension_uuid');

        $directExtensionUuids = User::query()
            ->whereIn('user_uuid', $userUuids)
            ->whereNotNull('extension_uuid')
            ->pluck('extension_uuid');

        $extensionUuids = $extensionUuids->merge($directExtensionUuids)->unique()->values();

        if ($extensionUuids->isEmpty()) {
            return collect();
        }

        return Extensions::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->whereIn('extension_uuid', $extensionUuids)
            ->get();
    }

    /**
     * @return Collection<int, Extensions>
     */
    public function extensionsForUser(User $user): Collection
    {
        $extensionUuids = ExtensionUser::query()
            ->where('user_uuid', $user->user_uuid)
            ->pluck('extension_uuid');

        if ($user->extension_uuid) {
            $extensionUuids->push($user->extension_uuid);
        }

        $extensionUuids = $extensionUuids->unique()->values();

        if ($extensionUuids->isEmpty()) {
            return collect();
        }

        return Extensions::query()
            ->where('domain_uuid', $user->domain_uuid)
            ->whereIn('extension_uuid', $extensionUuids)
            ->get();
    }

    /**
     * @param  iterable<int, SpeedDialUser|array<string, mixed>>  $assignments
     * @param  array<int, array{value: string, label: string}>  $userOptions
     * @return array<int, array{contact_user_uuid: ?string, user_uuid: string, value: string, label: string}>
     */
    public function formatContactUserAssignmentsForForm(iterable $assignments, array $userOptions = []): array
    {
        $labelsByUserUuid = collect($userOptions)->pluck('label', 'value');

        return collect($assignments)
            ->map(function ($assignment) use ($labelsByUserUuid) {
                $user = is_array($assignment) ? ($assignment['user'] ?? null) : $assignment->user;
                $userUuid = (string) (is_array($assignment)
                    ? ($assignment['user_uuid'] ?? $assignment['value'] ?? '')
                    : $assignment->user_uuid);

                if (is_array($assignment) && ! empty($assignment['label'])) {
                    return [
                        'contact_user_uuid' => $assignment['contact_user_uuid'] ?? null,
                        'user_uuid' => $userUuid,
                        'value' => $userUuid,
                        'label' => (string) $assignment['label'],
                    ];
                }

                $label = (is_object($user) ? $user->name_formatted : null)
                    ?: $labelsByUserUuid->get($userUuid)
                    ?: (is_object($user) ? $user->username : null)
                    ?: $userUuid;

                return [
                    'contact_user_uuid' => is_array($assignment)
                        ? ($assignment['contact_user_uuid'] ?? null)
                        : $assignment->contact_user_uuid,
                    'user_uuid' => $userUuid,
                    'value' => $userUuid,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    public function syncPhonebookContactAssignmentForUser(User $user): void
    {
        SpeedDialUser::query()
            ->where('domain_uuid', $user->domain_uuid)
            ->where('user_uuid', $user->user_uuid)
            ->delete();

        if (empty($user->contact_uuid)) {
            return;
        }

        $assignment = new SpeedDialUser();
        $assignment->contact_user_uuid = (string) Str::uuid();
        $assignment->forceFill([
            'domain_uuid' => $user->domain_uuid,
            'contact_uuid' => $user->contact_uuid,
            'user_uuid' => $user->user_uuid,
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ])->save();
    }

    public function syncUserContactUuidAssignments(VContact $contact, array $userUuids): void
    {
        $userUuids = collect($userUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values();

        User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->whereNotIn('user_uuid', $userUuids)
            ->update(['contact_uuid' => null]);

        if ($userUuids->isEmpty()) {
            return;
        }

        User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->whereIn('user_uuid', $userUuids)
            ->update(['contact_uuid' => $contact->contact_uuid]);
    }
}
