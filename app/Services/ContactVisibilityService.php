<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\VContact;
use App\Models\VContactPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactVisibilityService
{
    public function contactPermissionsEnabled(?string $domainUuid = null): bool
    {
        return $this->settingIsTrue('contact', 'permissions', $domainUuid);
    }

    public function provisionContactUsersEnabled(?string $domainUuid = null): bool
    {
        return $this->settingIsTrue('provision', 'contact_users', $domainUuid);
    }

    public function provisionContactGroupsEnabled(?string $domainUuid = null): bool
    {
        return $this->settingIsTrue('provision', 'contact_groups', $domainUuid);
    }

    public function provisionContactExtensionsEnabled(?string $domainUuid = null): bool
    {
        return $this->settingIsTrue('provision', 'contact_extensions', $domainUuid);
    }

    public function applyPortalListScope(Builder $query, ?string $domainUuid = null): Builder
    {
        if (userCheckPermission('contact_domain_view')) {
            return $query;
        }

        $domainUuid ??= session('domain_uuid');
        $userUuid = session('user_uuid');
        $groupUuids = $this->viewerGroupUuids($userUuid);

        return $query->where(function (Builder $query) use ($domainUuid, $userUuid, $groupUuids) {
            $query->whereHas('contactGroups', function (Builder $query) use ($domainUuid, $groupUuids) {
                $query->where('domain_uuid', $domainUuid)
                    ->whereIn('group_uuid', $groupUuids);
            })->orWhereHas('contactUsers', function (Builder $query) use ($domainUuid, $userUuid) {
                $query->where('domain_uuid', $domainUuid)
                    ->where('user_uuid', $userUuid);
            });
        });
    }

    /**
     * Group UUIDs used for portal list ACL, including the viewer's user UUID
     * for legacy private-contact rows stored in v_contact_groups.
     *
     * @return array<int, string>
     */
    public function viewerGroupUuids(?string $userUuid = null): array
    {
        $userUuid ??= session('user_uuid');
        $groupUuids = collect(session('user.groups', []))
            ->pluck('group_uuid')
            ->filter(fn ($uuid) => is_string($uuid) && Str::isUuid($uuid));

        if ($userUuid && Str::isUuid($userUuid)) {
            $groupUuids->push($userUuid);
        }

        return $groupUuids->unique()->values()->all();
    }

    /**
     * Extensions shown in provisioned phonebook/directory XML.
     */
    public function directoryExtensions(?string $domainUuid = null): Collection
    {
        if (! $this->provisionContactExtensionsEnabled($domainUuid)) {
            return collect();
        }

        $domainUuid ??= session('domain_uuid');

        return Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->where('enabled', 'true')
            ->where('directory_visible', 'true')
            ->orderBy('number_alias')
            ->orderBy('extension')
            ->get()
            ->map(function (Extensions $extension) {
                $given = trim((string) $extension->directory_first_name);
                $family = trim((string) $extension->directory_last_name);

                if ($given === '' && $family === '') {
                    $parts = preg_split('/\s+/', trim((string) $extension->effective_caller_id_name), 2) ?: [];
                    $given = $parts[0] ?? '';
                    $family = $parts[1] ?? '';
                }

                $number = is_numeric((string) $extension->extension)
                    ? (string) $extension->extension
                    : (string) $extension->number_alias;

                return [
                    'contact_uuid' => $extension->extension_uuid,
                    'category' => 'extensions',
                    'contact_category' => 'extensions',
                    'contact_name_given' => $given,
                    'contact_name_family' => $family,
                    'phone_extension' => $number,
                    'call_group' => $extension->call_group,
                ];
            });
    }

    /**
     * Voice contacts visible to a provisioned device's portal user.
     */
    public function contactsForDeviceUser(string $deviceUserUuid, ?string $domainUuid = null): Collection
    {
        $domainUuid ??= session('domain_uuid');

        if (! $this->contactPermissionsEnabled($domainUuid)) {
            return $this->voiceContactsQuery($domainUuid)->get();
        }

        $contacts = collect();

        if ($this->provisionContactGroupsEnabled($domainUuid)) {
            $groupUuids = collect(DB::table('v_user_groups')
                ->where('user_uuid', $deviceUserUuid)
                ->where('domain_uuid', $domainUuid)
                ->pluck('group_uuid')
                ->filter(fn ($uuid) => is_string($uuid) && Str::isUuid($uuid)));

            if ($groupUuids->isNotEmpty()) {
                $contacts = $contacts->merge(
                    $this->voiceContactsQuery($domainUuid)
                        ->whereIn('contact_uuid', function ($query) use ($domainUuid, $groupUuids) {
                            $query->select('contact_uuid')
                                ->from('v_contact_groups')
                                ->where('domain_uuid', $domainUuid)
                                ->whereIn('group_uuid', $groupUuids);
                        })
                        ->get()
                );
            }
        }

        if ($this->provisionContactUsersEnabled($domainUuid)) {
            $contacts = $contacts->merge(
                $this->voiceContactsQuery($domainUuid)
                    ->whereIn('contact_uuid', function ($query) use ($domainUuid, $deviceUserUuid) {
                        $query->select('contact_uuid')
                            ->from('v_contact_users')
                            ->where('domain_uuid', $domainUuid)
                            ->where('user_uuid', $deviceUserUuid);
                    })
                    ->get()
            );
        }

        return $contacts->unique('contact_uuid')->values();
    }

    private function voiceContactsQuery(string $domainUuid): Builder
    {
        return VContact::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('contact_uuid', function ($query) use ($domainUuid) {
                $query->select('contact_uuid')
                    ->from((new VContactPhone())->getTable())
                    ->where('domain_uuid', $domainUuid)
                    ->where('phone_type_voice', '1');
            })
            ->with(['phones' => function ($query) use ($domainUuid) {
                $query->where('domain_uuid', $domainUuid)
                    ->where('phone_type_voice', '1');
            }]);
    }

    private function settingIsTrue(string $category, string $subcategory, ?string $domainUuid = null): bool
    {
        $value = $this->readSetting($category, $subcategory, $domainUuid);

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function readSetting(string $category, string $subcategory, ?string $domainUuid = null): ?string
    {
        $domainUuid ??= session('domain_uuid');

        if ($domainUuid) {
            $domainValue = DomainSettings::query()
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', $category)
                ->where('domain_setting_subcategory', $subcategory)
                ->where('domain_setting_enabled', 'true')
                ->value('domain_setting_value');

            if ($domainValue !== null) {
                return $domainValue;
            }
        }

        return DefaultSettings::query()
            ->where('default_setting_category', $category)
            ->where('default_setting_subcategory', $subcategory)
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');
    }
}
