<?php

namespace App\Services\Contacts;

use App\Models\Extensions;
use App\Models\User;
use App\Models\VContact;
use App\Services\ContactService;
use RuntimeException;

class ContactFromAccountService
{
    public function __construct(
        private ContactService $contactService,
        private ContactUserLinkService $contactUserLinkService,
    ) {}

    public function createFromExtension(Extensions $extension): VContact
    {
        if ($this->contactUserLinkService->resolvePhonebookContactForExtension($extension)) {
            throw new RuntimeException('This extension already has a linked phonebook contact.');
        }

        [$givenName, $familyName] = $this->namePartsFromExtension($extension);

        return $this->contactService->save($this->buildPayload(
            givenName: $givenName,
            familyName: $familyName,
            email: trim((string) $extension->email),
            extension: $extension,
            users: $this->contactUserLinkService->usersForExtension($extension),
        ));
    }

    public function createFromUser(User $user): VContact
    {
        if ($this->contactUserLinkService->resolvePhonebookContactForUser($user)) {
            throw new RuntimeException('This user already has a linked phonebook contact.');
        }

        $user->loadMissing('extension');

        $givenName = trim((string) $user->first_name);
        $familyName = trim((string) $user->last_name);

        if ($givenName === '' && $familyName === '' && $user->name_formatted) {
            [$givenName, $familyName] = $this->splitDisplayName((string) $user->name_formatted);
        }

        $extension = $user->extension_uuid
            ? $user->extension
            : null;

        return $this->contactService->save($this->buildPayload(
            givenName: $givenName,
            familyName: $familyName,
            email: trim((string) $user->user_email),
            extension: $extension instanceof Extensions ? $extension : null,
            users: collect([$user]),
        ));
    }

    /**
     * @param  array<int, string>  $extensionUuids
     * @return array{created: int, skipped: int, failed: array<int, array{uuid: string, label: string, message: string}>}
     */
    public function bulkCreateFromExtensions(array $extensionUuids): array
    {
        $domainUuid = session('domain_uuid');
        $uuids = collect($extensionUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values()
            ->all();

        $extensions = Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('extension_uuid', $uuids)
            ->get()
            ->keyBy('extension_uuid');

        $created = 0;
        $skipped = max(count($uuids) - $extensions->count(), 0);
        $failed = [];

        foreach ($uuids as $uuid) {
            $extension = $extensions->get($uuid);

            if (! $extension) {
                continue;
            }

            try {
                $this->createFromExtension($extension);
                $created++;
            } catch (RuntimeException $exception) {
                $skipped++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'uuid' => $uuid,
                    'label' => $this->extensionLabel($extension),
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return compact('created', 'skipped', 'failed');
    }

    /**
     * @param  array<int, string>  $userUuids
     * @return array{created: int, skipped: int, failed: array<int, array{uuid: string, label: string, message: string}>}
     */
    public function bulkCreateFromUsers(array $userUuids): array
    {
        $domainUuid = session('domain_uuid');
        $uuids = collect($userUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values()
            ->all();

        $users = User::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('user_uuid', $uuids)
            ->get()
            ->keyBy('user_uuid');

        $created = 0;
        $skipped = max(count($uuids) - $users->count(), 0);
        $failed = [];

        foreach ($uuids as $uuid) {
            $user = $users->get($uuid);

            if (! $user) {
                continue;
            }

            try {
                $this->createFromUser($user);
                $created++;
            } catch (RuntimeException $exception) {
                $skipped++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'uuid' => $uuid,
                    'label' => $this->userLabel($user),
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return compact('created', 'skipped', 'failed');
    }

    private function extensionLabel(Extensions $extension): string
    {
        $name = trim((string) ($extension->effective_caller_id_name ?: $extension->name_formatted));
        $number = trim((string) $extension->extension);

        return $name !== '' ? trim("{$number} {$name}") : ($number !== '' ? $number : $extension->extension_uuid);
    }

    private function userLabel(User $user): string
    {
        $name = trim((string) ($user->name_formatted ?: $user->username));

        return $name !== '' ? $name : $user->user_uuid;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $givenName,
        string $familyName,
        string $email,
        ?Extensions $extension,
        $users,
    ): array {
        $payload = [
            'contact_type' => 'user',
            'contact_name_given' => $givenName !== '' ? $givenName : null,
            'contact_name_family' => $familyName !== '' ? $familyName : null,
            'phones' => $this->phonesForExtension($extension),
            'emails' => $this->emailsForAddress($email),
            'contact_users' => $users
                ->map(fn (User $user) => ['user_uuid' => $user->user_uuid])
                ->values()
                ->all(),
        ];

        if ($extension) {
            $payload['phonebook_extension_uuid'] = $extension->extension_uuid;
        }

        return $payload;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function namePartsFromExtension(Extensions $extension): array
    {
        $givenName = trim((string) $extension->directory_first_name);
        $familyName = trim((string) $extension->directory_last_name);

        if ($givenName !== '' || $familyName !== '') {
            return [$givenName, $familyName];
        }

        return $this->splitDisplayName(trim((string) $extension->effective_caller_id_name));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitDisplayName(string $displayName): array
    {
        $displayName = trim($displayName);

        if ($displayName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $displayName, 2) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function phonesForExtension(?Extensions $extension): array
    {
        if (! $extension) {
            return [];
        }

        $extensionNumber = trim((string) $extension->extension);
        $workNumber = formatContactPhoneE164(
            trim((string) $extension->outbound_caller_id_number),
            $extension->domain_uuid,
        );

        if ($extensionNumber === '' && $workNumber === '') {
            return [];
        }

        return [[
            'phone_label' => 'work',
            'phone_number' => $workNumber !== '' ? $workNumber : null,
            'phone_extension' => $extensionNumber !== '' ? $extensionNumber : null,
            'phone_primary' => '1',
            'phone_type_voice' => '1',
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emailsForAddress(string $email): array
    {
        if ($email === '') {
            return [];
        }

        return [[
            'email_label' => 'work',
            'email_address' => $email,
            'email_primary' => '1',
        ]];
    }
}
