<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCallPermissionRequest;
use App\Http\Requests\UpdateCallPermissionRequest;
use App\Models\CallPermission;
use App\Services\CallPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CallPermissionController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('call_permission_view')) {
            return redirect('/');
        }

        return Inertia::render('CallPermissions', [
            'routes' => [
                'current_page' => route('call-permissions.index'),
                'data_route' => route('call-permissions.data'),
                'select_all' => route('call-permissions.select.all'),
                'bulk_copy' => route('call-permissions.bulk.copy'),
                'bulk_delete' => route('call-permissions.bulk.delete'),
                'bulk_toggle' => route('call-permissions.bulk.toggle'),
                'store' => route('call-permissions.store'),
                'item_options' => route('call-permissions.item.options'),
                'export' => route('call-permissions.export'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function export()
    {
        if (! userCheckPermission('call_permission_view')) {
            abort(403);
        }

        $columns = [
            'call_permission_uuid',
            'domain_uuid',
            'name',
            'description',
            'toll_allow',
            'default_action',
            'enabled',
        ];

        $rows = CallPermission::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('name')
            ->get($columns);

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $row->only($columns));
            }

            fclose($handle);
        }, 'call_permissions_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function store(StoreCallPermissionRequest $request, CallPermissionService $service): JsonResponse
    {
        try {
            $profile = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Call Permission created successfully.']],
                'call_permission_uuid' => $profile->call_permission_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('CallPermissionController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create Call Permission.']],
            ], 500);
        }
    }

    public function update(UpdateCallPermissionRequest $request, CallPermission $call_permission, CallPermissionService $service): JsonResponse
    {
        if ($call_permission->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $call_permission);

            return response()->json([
                'messages' => ['success' => ['Call Permission updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('CallPermissionController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update Call Permission.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('call_permission_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('call_permission_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = CallPermission::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with('destinations')
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new CallPermission();
            $item->enabled = 'true';
            $item->default_action = 'allow';
            $item->setRelation('destinations', collect());
        }

        return response()->json([
            'item' => $item,
            'routes' => [
                'store_route' => route('call-permissions.store'),
                'update_route' => $itemUuid ? route('call-permissions.update', ['call_permission' => $item->call_permission_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('call_permission_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedProfiles($request)
            ->withCount('extensions')
            ->select([
                'domain_uuid',
                'call_permission_uuid',
                'name',
                'description',
                'toll_allow',
                'default_action',
                'enabled',
            ])
            ->allowedSorts([
                'name',
                'toll_allow',
                'default_action',
                'enabled',
            ])
            ->defaultSort('name')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('call_permission_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedProfiles($request)
            ->select(['call_permission_uuid'])
            ->defaultSort('name')
            ->pluck('call_permission_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching profiles selected.']],
        ]);
    }

    public function bulkCopy(Request $request, CallPermissionService $service): JsonResponse
    {
        if (! userCheckPermission('call_permission_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No profiles selected.']],
            ], 422);
        }

        $copied = $service->copy($items);

        return response()->json([
            'messages' => ['success' => ["Copied {$copied} profile(s)."]],
        ]);
    }

    public function bulkDelete(Request $request, CallPermissionService $service): JsonResponse
    {
        if (! userCheckPermission('call_permission_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No profiles selected.']],
            ], 422);
        }

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} profile(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, CallPermissionService $service): JsonResponse
    {
        if (! userCheckPermission('call_permission_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No profiles selected.']],
            ], 422);
        }

        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => ['Profile status toggled.']],
        ]);
    }

    private function scopedProfiles(Request $request): QueryBuilder
    {
        return QueryBuilder::for(CallPermission::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%")
                            ->orWhere('toll_allow', 'ilike', "%{$needle}%")
                            ->orWhere('enabled', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::exact('enabled'),
            ]);
    }

    private function itemsFromRequest(Request $request): Collection
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();

        if (empty($uuids)) {
            return collect();
        }

        return CallPermission::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('call_permission_uuid', $uuids)
            ->get();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('call_permission_add'),
            'update' => userCheckPermission('call_permission_edit'),
            'destroy' => userCheckPermission('call_permission_delete'),
            'copy' => userCheckPermission('call_permission_add'),
            'export' => userCheckPermission('call_permission_view'),
        ];
    }
}
