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
        if (! empty($user->phonebook_contact_uuid)) {
            $contact = VContact::query()
                ->where('domain_uuid', $user->domain_uuid)
                ->whereKey($user->phonebook_contact_uuid)
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

    public function resolveEmailForContact(VContact $contact): string
    {
        $contact->loadMissing('emails');

        foreach ($contact->emails as $email) {
            if (! $this->isPrimaryFlag($email->email_primary)) {
                continue;
            }

            $address = trim((string) $email->email_address);

            if ($address !== '') {
                return $address;
            }
        }

        foreach ($contact->emails as $email) {
            if (strtolower((string) $email->email_label) !== 'work') {
                continue;
            }

            $address = trim((string) $email->email_address);

            if ($address !== '') {
                return $address;
            }
        }

        foreach ($contact->emails as $email) {
            $address = trim((string) $email->email_address);

            if ($address !== '') {
                return $address;
            }
        }

        return '';
    }

    public function resolveEmailForUser(User $user): string
    {
        $contact = $this->resolvePhonebookContactForUser($user);

        return $contact ? $this->resolveEmailForContact($contact) : '';
    }

    public function resolveEmailForExtension(Extensions $extension): string
    {
        $contact = $this->resolvePhonebookContactForExtension($extension);

        if ($contact) {
            $email = $this->resolveEmailForContact($contact);

            if ($email !== '') {
                return $email;
            }
        }

        return trim((string) ($extension->email ?? ''));
    }

    public function resolveEmailForExtensionDirect(Extensions $extension): string
    {
        $contact = $this->resolvePhonebookContactForExtensionDirect($extension);

        return $contact ? $this->resolveEmailForContact($contact) : '';
    }

    public function resolvePhoneNumberForContactByLabel(VContact $contact, string $label): string
    {
        $contact->loadMissing('phones');
        $label = strtolower($label);

        foreach ($contact->phones as $phone) {
            if (strtolower((string) $phone->phone_label) !== $label) {
                continue;
            }

            $normalized = formatContactPhoneE164((string) $phone->phone_number, $contact->domain_uuid);

            if ($normalized !== '') {
                return $normalized;
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

    public function resolvePhonebookContactForExtension(Extensions $extension): ?VContact
    {
        $direct = $this->resolvePhonebookContactForExtensionDirect($extension);

        if ($direct) {
            return $direct;
        }

        foreach ($this->usersForExtension($extension) as $user) {
            $contact = $this->resolvePhonebookContactForUser($user);

            if ($contact) {
                return $contact;
            }
        }

        return null;
    }

    public function resolvePhonebookContactForExtensionDirect(Extensions $extension): ?VContact
    {
        $extension->loadMissing('phonebookContact');

        if ($extension->phonebook_contact_uuid && $extension->phonebookContact) {
            $extension->phonebookContact->loadMissing('phones');

            return $extension->phonebookContact;
        }

        return null;
    }

    public function resolveMobileNumberForExtensionDirect(Extensions $extension): string
    {
        $contact = $this->resolvePhonebookContactForExtensionDirect($extension);

        return $contact ? $this->resolveMobileNumberForContact($contact) : '';
    }

    public function resolveWorkNumberForExtensionDirect(Extensions $extension): string
    {
        $contact = $this->resolvePhonebookContactForExtensionDirect($extension);

        return $contact ? $this->resolveWorkNumberForContact($contact) : '';
    }

    public function extensionHasDirectLinkedContactPhones(Extensions $extension): bool
    {
        return $this->resolveMobileNumberForExtensionDirect($extension) !== ''
            || $this->resolveWorkNumberForExtensionDirect($extension) !== '';
    }

    private function resolvePhoneNumberForExtension(Extensions $extension, string $userResolver): string
    {
        $contact = $this->resolvePhonebookContactForExtension($extension);

        if ($contact) {
            return $userResolver === 'resolveMobileNumberForUser'
                ? $this->resolveMobileNumberForContact($contact)
                : $this->resolveWorkNumberForContact($contact);
        }

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
        $extensions = $this->directExtensionsForContact($contact);

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
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->exists()) {
            return true;
        }

        if (Extensions::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
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

        if (! $this->extensionHasDirectLinkedContactPhones($extension)) {
            $sync->removePhonebookOnlyEnterpriseEntry($extension);

            return;
        }

        $sync->syncPhonebookOnlyExtension($extension);
    }

    /**
     * @return Collection<int, Extensions>
     */
    public function directExtensionsForContact(VContact $contact): Collection
    {
        return Extensions::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->get();
    }

    /**
     * @return Collection<int, Extensions>
     */
    public function extensionsForContact(VContact $contact): Collection
    {
        $directExtensions = $this->directExtensionsForContact($contact);

        $userUuids = User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->pluck('user_uuid');

        $assignedUserUuids = SpeedDialUser::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->pluck('user_uuid');

        $userUuids = $userUuids->merge($assignedUserUuids)->unique()->values();

        if ($userUuids->isEmpty()) {
            return $directExtensions->values();
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
            return $directExtensions->values();
        }

        return $directExtensions
            ->merge(
                Extensions::query()
                    ->where('domain_uuid', $contact->domain_uuid)
                    ->whereIn('extension_uuid', $extensionUuids)
                    ->get()
            )
            ->unique('extension_uuid')
            ->values();
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

        if (empty($user->phonebook_contact_uuid)) {
            return;
        }

        $assignment = new SpeedDialUser();
        $assignment->contact_user_uuid = (string) Str::uuid();
        $assignment->forceFill([
            'domain_uuid' => $user->domain_uuid,
            'contact_uuid' => $user->phonebook_contact_uuid,
            'user_uuid' => $user->user_uuid,
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ])->save();
    }

    /**
     * @return array{
     *     contact_uuid: string,
     *     name: string,
     *     mobile: string,
     *     work: string,
     *     user_labels: array<int, string>
     * }|null
     */
    public function formatLinkedContactForExtension(Extensions $extension): ?array
    {
        $extension->loadMissing('phonebookContact');
        $contact = null;
        $userLabels = [];
        $linkType = 'user';

        if ($extension->phonebook_contact_uuid && $extension->phonebookContact) {
            $contact = $extension->phonebookContact->loadMissing('phones');
            $linkType = 'extension';
        } else {
            foreach ($this->usersForExtension($extension) as $user) {
                $resolved = $this->resolvePhonebookContactForUser($user);

                if (! $resolved) {
                    continue;
                }

                $contact ??= $resolved;
                $userLabels[] = $user->name_formatted ?: $user->username;
            }
        }

        if (! $contact) {
            return null;
        }

        return [
            'contact_uuid' => (string) $contact->contact_uuid,
            'name' => (string) $contact->display_name,
            'mobile' => $this->resolveMobileNumberForExtension($extension),
            'work' => $this->resolveWorkNumberForExtension($extension),
            'email' => $this->resolveEmailForExtension($extension),
            'user_labels' => array_values(array_unique($userLabels)),
            'link_type' => $linkType,
        ];
    }

    /**
     * @return array<int, array{extension_uuid: string, extension: string, label: string, user_labels: array<int, string>}>
     */
    public function formatLinkedExtensionsForContact(VContact $contact): array
    {
        $contact->loadMissing('contactUsers.user');

        $usersByUuid = User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->get(['user_uuid', 'username'])
            ->keyBy('user_uuid');

        foreach ($contact->contactUsers as $assignment) {
            if ($assignment->user) {
                $usersByUuid->put(
                    $assignment->user_uuid,
                    $assignment->user,
                );
            }
        }

        $extensions = $this->extensionsForContact($contact);
        $rows = [];

        foreach ($extensions as $extension) {
            $userLabels = [];
            $isDirectLink = (string) $extension->phonebook_contact_uuid === (string) $contact->contact_uuid;

            if ($isDirectLink) {
                $userLabels[] = 'direct extension link';
            }

            foreach ($usersByUuid as $user) {
                $matches = $this->extensionsForUser($user)
                    ->contains(fn (Extensions $candidate) => $candidate->extension_uuid === $extension->extension_uuid);

                if ($matches) {
                    $userLabels[] = $user->name_formatted ?: $user->username;
                }
            }

            $label = trim((string) $extension->effective_caller_id_name);

            if ($label === '') {
                $label = trim(trim((string) $extension->directory_first_name) . ' ' . trim((string) $extension->directory_last_name));
            }

            if ($label === '') {
                $label = (string) $extension->extension;
            }

            $rows[] = [
                'extension_uuid' => (string) $extension->extension_uuid,
                'extension' => (string) $extension->extension,
                'label' => $label,
                'user_labels' => array_values(array_unique($userLabels)),
                'direct_link' => $isDirectLink,
            ];
        }

        usort($rows, fn (array $left, array $right) => strcmp($left['extension'], $right['extension']));

        return $rows;
    }

    public function cleanupBeforeContactDelete(VContact $contact): void
    {
        $extensions = $this->extensionsForContact($contact);

        // Break all linkage before CloudPLAY sync so contact phones stop resolving.
        User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->update(['phonebook_contact_uuid' => null]);

        Extensions::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->update(['phonebook_contact_uuid' => null]);

        SpeedDialUser::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('contact_uuid', $contact->contact_uuid)
            ->delete();

        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        app(CloudPlayApiService::class)->clearEnterpriseDirectoryCache();

        $sync = app(CloudPlayEnterpriseDirectorySync::class);
        $sync->removePhonebookOnlyContactEntry($contact);

        foreach ($extensions as $extension) {
            $this->releaseExtensionContactPhones($extension);
        }

        $sync->removeDuplicateEnterpriseEntries($contact->domain_uuid);
    }

    public function syncUserContactUuidAssignments(VContact $contact, array $userUuids): void
    {
        $userUuids = collect($userUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values();

        User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->whereNotIn('user_uuid', $userUuids)
            ->update(['phonebook_contact_uuid' => null]);

        if ($userUuids->isEmpty()) {
            return;
        }

        User::query()
            ->where('domain_uuid', $contact->domain_uuid)
            ->whereIn('user_uuid', $userUuids)
            ->update(['phonebook_contact_uuid' => $contact->contact_uuid]);
    }

    public function syncExtensionPhonebookContactAssignment(VContact $contact, ?string $extensionUuid): void
    {
        $domainUuid = $contact->domain_uuid;

        $previouslyLinkedExtensionUuids = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->pluck('extension_uuid');

        $previousContactForTargetExtension = null;

        if ($extensionUuid) {
            $previousContactForTargetExtension = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->whereKey($extensionUuid)
                ->value('phonebook_contact_uuid');
        }

        Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->where('phonebook_contact_uuid', $contact->contact_uuid)
            ->when($extensionUuid, fn ($query) => $query->where('extension_uuid', '!=', $extensionUuid))
            ->update(['phonebook_contact_uuid' => null]);

        if ($extensionUuid) {
            Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->whereKey($extensionUuid)
                ->update(['phonebook_contact_uuid' => $contact->contact_uuid]);
        }

        $this->syncCloudPlayAfterPhonebookLinkChange(
            $domainUuid,
            collect([$contact->contact_uuid, $previousContactForTargetExtension])->filter()->unique()->values(),
            $previouslyLinkedExtensionUuids
                ->merge($extensionUuid ? [$extensionUuid] : [])
                ->unique()
                ->values(),
        );
    }

    public function syncPhonebookContactAssignmentForExtension(
        Extensions $extension,
        ?string $previousContactUuid,
        ?string $newContactUuid,
    ): void {
        $domainUuid = $extension->domain_uuid;

        $displacedExtensionUuids = collect();

        if ($newContactUuid) {
            $displacedExtensionUuids = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->where('phonebook_contact_uuid', $newContactUuid)
                ->where('extension_uuid', '!=', $extension->extension_uuid)
                ->pluck('extension_uuid');

            Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->where('phonebook_contact_uuid', $newContactUuid)
                ->where('extension_uuid', '!=', $extension->extension_uuid)
                ->update(['phonebook_contact_uuid' => null]);
        }

        $this->syncCloudPlayAfterPhonebookLinkChange(
            $domainUuid,
            collect([$previousContactUuid, $newContactUuid])->filter()->unique()->values(),
            collect([$extension->extension_uuid])->merge($displacedExtensionUuids)->unique()->values(),
        );
    }

    /**
     * @param  Collection<int, string>|array<int, string>  $contactUuids
     * @param  Collection<int, string>|array<int, string>  $extensionUuids
     */
    public function syncCloudPlayAfterPhonebookLinkChange(
        string $domainUuid,
        Collection|array $contactUuids,
        Collection|array $extensionUuids,
    ): void {
        if (get_mobile_app_provider() !== 'cloudplay') {
            return;
        }

        app(CloudPlayApiService::class)->clearEnterpriseDirectoryCache();

        foreach (collect($extensionUuids)->unique()->filter() as $extensionUuid) {
            $extension = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->whereKey($extensionUuid)
                ->first();

            if ($extension) {
                $this->syncCloudPlayForExtension($extension);
            }
        }

        foreach (collect($contactUuids)->unique()->filter() as $contactUuid) {
            $contact = VContact::query()
                ->where('domain_uuid', $domainUuid)
                ->whereKey($contactUuid)
                ->first();

            if ($contact) {
                $this->syncCloudPlayForContact($contact);
            }
        }

        app(CloudPlayEnterpriseDirectorySync::class)->removeDuplicateEnterpriseEntries($domainUuid);
    }

    private function isPrimaryFlag(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }
}
