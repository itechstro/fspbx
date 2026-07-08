<?php

namespace App\Services;

use App\Models\CallPermission;
use App\Models\CallPermissionDestination;
use App\Models\Dialplans;
use App\Models\Extensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CallPermissionService
{
    private const DIALPLAN_APP_UUID = 'c4a8f1e2-9b3d-4c7a-8f6e-1d2c3b4a5e6f';

    private const DIALPLAN_XML = <<<'XML'
<extension name="call_permissions" number="" context="${domain_name}" continue="true" app_uuid="c4a8f1e2-9b3d-4c7a-8f6e-1d2c3b4a5e6f" enabled="true" order="35">
	<condition field="${call_direction}" expression="^outbound$">
		<action application="lua" data="lua/call_permissions.lua"/>
	</condition>
</extension>
XML;

    public function save(array $validated, ?CallPermission $profile = null): CallPermission
    {
        return DB::transaction(function () use ($validated, $profile) {
            $profile ??= new CallPermission();
            $isNew = ! $profile->exists;

            $profile->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'call_permission_uuid' => $profile->call_permission_uuid ?: (string) Str::uuid(),
                'name' => trim((string) $validated['name']),
                'description' => $this->blankToNull($validated['description'] ?? null),
                'toll_allow' => $this->blankToNull($validated['toll_allow'] ?? null),
                'default_action' => $validated['default_action'] ?? 'allow',
                'enabled' => $validated['enabled'],
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            $this->syncDestinations($profile, $validated['destinations'] ?? []);
            $this->syncExtensionTollAllow($profile);
            $this->enableDialplanIfNeeded();
            $this->bumpRuleCacheVersion(session('domain_uuid'));

            return $profile->fresh(['destinations']);
        });
    }

    public function toggle(Collection $profiles): void
    {
        DB::transaction(function () use ($profiles) {
            foreach ($profiles as $profile) {
                $profile->forceFill([
                    'enabled' => $profile->enabled === 'true' ? 'false' : 'true',
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ])->save();
            }

            $this->bumpRuleCacheVersion(session('domain_uuid'));
        });
    }

    public function delete(Collection $profiles): int
    {
        return DB::transaction(function () use ($profiles) {
            $uuids = $profiles->pluck('call_permission_uuid');

            Extensions::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_permission_uuid', $uuids)
                ->update([
                    'call_permission_uuid' => null,
                    'update_date' => now(),
                ]);

            CallPermissionDestination::query()
                ->whereIn('call_permission_uuid', $uuids)
                ->delete();

            $deleted = CallPermission::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_permission_uuid', $uuids)
                ->delete();

            $this->bumpRuleCacheVersion(session('domain_uuid'));

            return $deleted;
        });
    }

    public function copy(Collection $profiles): int
    {
        return DB::transaction(function () use ($profiles) {
            $count = 0;

            foreach ($profiles as $profile) {
                $profile->loadMissing('destinations');

                $copy = $profile->replicate();
                $copy->call_permission_uuid = (string) Str::uuid();
                $copy->name = trim((string) $profile->name . ' (copy)');
                $copy->insert_date = now();
                $copy->insert_user = session('user_uuid');
                $copy->update_date = null;
                $copy->update_user = null;
                $copy->save();

                foreach ($profile->destinations as $destination) {
                    $destinationCopy = $destination->replicate();
                    $destinationCopy->call_permission_destination_uuid = (string) Str::uuid();
                    $destinationCopy->call_permission_uuid = $copy->call_permission_uuid;
                    $destinationCopy->insert_date = now();
                    $destinationCopy->insert_user = session('user_uuid');
                    $destinationCopy->update_date = null;
                    $destinationCopy->update_user = null;
                    $destinationCopy->save();
                }

                $count++;
            }

            $this->bumpRuleCacheVersion(session('domain_uuid'));

            return $count;
        });
    }

    public function applyProfileToExtension(?string $callPermissionUuid): ?string
    {
        if (! $callPermissionUuid) {
            return null;
        }

        $profile = CallPermission::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('call_permission_uuid', $callPermissionUuid)
            ->where('enabled', 'true')
            ->first();

        return $profile?->toll_allow;
    }

    public function optionsForDomain(?string $domainUuid = null): array
    {
        $domainUuid ??= session('domain_uuid');

        return CallPermission::query()
            ->where('domain_uuid', $domainUuid)
            ->where('enabled', 'true')
            ->orderBy('name')
            ->get(['call_permission_uuid', 'name', 'toll_allow'])
            ->map(fn (CallPermission $profile) => [
                'value' => $profile->call_permission_uuid,
                'label' => $profile->name,
                'toll_allow' => $profile->toll_allow,
            ])
            ->values()
            ->all();
    }

    public function bumpRuleCacheVersion(?string $domainUuid): void
    {
        if (! $domainUuid) {
            return;
        }

        try {
            $key = "call_permissions:version:{$domainUuid}";
            $redis = Redis::connection('freeswitch');
            $version = $redis->incr($key);

            if ((int) $version === 1) {
                $redis->incr($key);
            }
        } catch (\Throwable $exception) {
            logger('CallPermissionService@bumpRuleCacheVersion error: ' . $exception->getMessage());
        }
    }

    private function syncDestinations(CallPermission $profile, array $destinations): void
    {
        $kept = [];
        $order = 100;

        foreach ($destinations as $destination) {
            $prefix = trim((string) ($destination['destination_prefix'] ?? ''));
            $action = trim((string) ($destination['destination_action'] ?? ''));

            if ($prefix === '' || ! in_array($action, ['allow', 'deny'], true)) {
                continue;
            }

            $uuid = $destination['call_permission_destination_uuid'] ?? null;
            $model = null;

            if ($uuid) {
                $model = CallPermissionDestination::query()
                    ->where('call_permission_uuid', $profile->call_permission_uuid)
                    ->whereKey($uuid)
                    ->first();
            }

            $model ??= new CallPermissionDestination();
            $isNew = ! $model->exists;

            $model->forceFill([
                'call_permission_uuid' => $profile->call_permission_uuid,
                'call_permission_destination_uuid' => $model->call_permission_destination_uuid ?: (string) Str::uuid(),
                'destination_prefix' => $prefix,
                'destination_action' => $action,
                'destination_order' => (int) ($destination['destination_order'] ?? $order),
                'enabled' => $destination['enabled'] ?? 'true',
                'destination_description' => $this->blankToNull($destination['destination_description'] ?? null),
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            $kept[] = $model->call_permission_destination_uuid;
            $order += 10;
        }

        CallPermissionDestination::query()
            ->where('call_permission_uuid', $profile->call_permission_uuid)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('call_permission_destination_uuid', $kept))
            ->when($kept === [], fn ($query) => $query)
            ->delete();
    }

    private function syncExtensionTollAllow(CallPermission $profile): void
    {
        Extensions::query()
            ->where('domain_uuid', $profile->domain_uuid)
            ->where('call_permission_uuid', $profile->call_permission_uuid)
            ->update([
                'toll_allow' => $profile->toll_allow,
            ]);
    }

    private function enableDialplanIfNeeded(): void
    {
        $domainUuid = session('domain_uuid');

        $exists = Dialplans::query()
            ->where('domain_uuid', $domainUuid)
            ->where('app_uuid', self::DIALPLAN_APP_UUID)
            ->exists();

        if ($exists) {
            return;
        }

        $domain = DB::table('v_domains')
            ->where('domain_uuid', $domainUuid)
            ->value('domain_name');

        if (! $domain) {
            return;
        }

        $xml = str_replace('${domain_name}', $domain, self::DIALPLAN_XML);

        Dialplans::query()->create([
            'dialplan_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'app_uuid' => self::DIALPLAN_APP_UUID,
            'dialplan_name' => 'call_permissions',
            'dialplan_number' => '',
            'dialplan_context' => $domain,
            'dialplan_continue' => 'true',
            'dialplan_order' => '35',
            'dialplan_enabled' => 'true',
            'dialplan_description' => 'Call Permissions outbound restrictions',
            'dialplan_xml' => $xml,
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ]);

        app(DialplanService::class)->clearDialplanCache($domain);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
