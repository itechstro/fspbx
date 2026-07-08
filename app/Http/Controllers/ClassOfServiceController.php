<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClassOfServiceRequest;
use App\Http\Requests\UpdateClassOfServiceRequest;
use App\Models\ClassOfService;
use App\Services\ClassOfServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClassOfServiceController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('class_of_service_view')) {
            return redirect('/');
        }

        return Inertia::render('ClassOfService', [
            'routes' => [
                'current_page' => route('class-of-service.index'),
                'data_route' => route('class-of-service.data'),
                'select_all' => route('class-of-service.select.all'),
                'bulk_copy' => route('class-of-service.bulk.copy'),
                'bulk_delete' => route('class-of-service.bulk.delete'),
                'bulk_toggle' => route('class-of-service.bulk.toggle'),
                'store' => route('class-of-service.store'),
                'item_options' => route('class-of-service.item.options'),
                'export' => route('class-of-service.export'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function export()
    {
        if (! userCheckPermission('class_of_service_view')) {
            abort(403);
        }

        $columns = [
            'class_of_service_uuid',
            'domain_uuid',
            'cos_name',
            'cos_description',
            'toll_allow',
            'default_action',
            'enabled',
        ];

        $rows = ClassOfService::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('cos_name')
            ->get($columns);

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $row->only($columns));
            }

            fclose($handle);
        }, 'class_of_service_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function store(StoreClassOfServiceRequest $request, ClassOfServiceService $service): JsonResponse
    {
        try {
            $profile = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Class of Service profile created successfully.']],
                'class_of_service_uuid' => $profile->class_of_service_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ClassOfServiceController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create Class of Service profile.']],
            ], 500);
        }
    }

    public function update(UpdateClassOfServiceRequest $request, ClassOfService $class_of_service, ClassOfServiceService $service): JsonResponse
    {
        if ($class_of_service->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $class_of_service);

            return response()->json([
                'messages' => ['success' => ['Class of Service profile updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ClassOfServiceController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update Class of Service profile.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('class_of_service_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('class_of_service_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = ClassOfService::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with('destinations')
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new ClassOfService();
            $item->enabled = 'true';
            $item->default_action = 'allow';
            $item->setRelation('destinations', collect());
        }

        return response()->json([
            'item' => $item,
            'routes' => [
                'store_route' => route('class-of-service.store'),
                'update_route' => $itemUuid ? route('class-of-service.update', ['class_of_service' => $item->class_of_service_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('class_of_service_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedProfiles($request)
            ->withCount('extensions')
            ->select([
                'domain_uuid',
                'class_of_service_uuid',
                'cos_name',
                'cos_description',
                'toll_allow',
                'default_action',
                'enabled',
            ])
            ->allowedSorts([
                'cos_name',
                'toll_allow',
                'default_action',
                'enabled',
            ])
            ->defaultSort('cos_name')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('class_of_service_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedProfiles($request)
            ->select(['class_of_service_uuid'])
            ->defaultSort('cos_name')
            ->pluck('class_of_service_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching profiles selected.']],
        ]);
    }

    public function bulkCopy(Request $request, ClassOfServiceService $service): JsonResponse
    {
        if (! userCheckPermission('class_of_service_add')) {
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

    public function bulkDelete(Request $request, ClassOfServiceService $service): JsonResponse
    {
        if (! userCheckPermission('class_of_service_delete')) {
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

    public function bulkToggle(Request $request, ClassOfServiceService $service): JsonResponse
    {
        if (! userCheckPermission('class_of_service_edit')) {
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
        return QueryBuilder::for(ClassOfService::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('cos_name', 'ilike', "%{$needle}%")
                            ->orWhere('cos_description', 'ilike', "%{$needle}%")
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

        return ClassOfService::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('class_of_service_uuid', $uuids)
            ->get();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('class_of_service_add'),
            'update' => userCheckPermission('class_of_service_edit'),
            'destroy' => userCheckPermission('class_of_service_delete'),
            'copy' => userCheckPermission('class_of_service_add'),
            'export' => userCheckPermission('class_of_service_view'),
        ];
    }
}
