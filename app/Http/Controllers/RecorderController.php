<?php

namespace App\Http\Controllers;

use App\Services\CdrDataService;
use App\Services\RecorderPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class RecorderController extends Controller
{
    public function __construct(
        protected CdrDataService $cdrDataService
    ) {
    }

    public function index(Request $request)
    {
        if (! RecorderPermissionService::canViewRecorder()) {
            return redirect('/');
        }

        $domainUuid = session('domain_uuid');

        if (! $this->cdrDataService->isRecorderEnabled($domainUuid)) {
            return redirect('/call-detail-records');
        }

        $startPeriod = Carbon::now(get_local_time_zone($domainUuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domainUuid))->endOfDay()->setTimeZone('UTC');

        if (! empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        return Inertia::render('Recorder', [
            'showGlobal' => false,
            'startPeriod' => fn () => $startPeriod,
            'endPeriod' => fn () => $endPeriod,
            'timezone' => fn () => get_local_time_zone($domainUuid),
            'routes' => [
                'current_page' => route('recorder.index'),
                'data_route' => route('recorder.data'),
                'item_options' => route('cdrs.item.options'),
                'call_recording_route' => route('cdrs.recording.options'),
                'analytics_page' => route('recorder.analytics.index'),
            ],
            'permissions' => fn () => RecorderPermissionService::pagePermissions(),
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
        ]);
    }

    public function getData()
    {
        if (! RecorderPermissionService::canViewRecorder()) {
            abort(403);
        }

        $domainUuid = session('domain_uuid');

        if (! $this->cdrDataService->isRecorderEnabled($domainUuid)) {
            abort(404);
        }

        $params = request()->all();

        foreach (['filter.search', 'filter.searchTerm', 'filterData.search'] as $searchKey) {
            $raw = data_get($params, $searchKey);
            if ($raw === null || $raw === '') {
                continue;
            }

            data_set($params, $searchKey, $this->cdrDataService->normalizeSearchTerm($raw));
            break;
        }

        if (! empty(data_get($params, 'filter.sentiment'))) {
            if (! RecorderPermissionService::canSearchSentiment()) {
                abort(403);
            }
        }

        if (filter_var(data_get($params, 'filter.showGlobal'), FILTER_VALIDATE_BOOLEAN)
            && ! userCheckPermission('recorder_view_global')) {
            data_set($params, 'filter.showGlobal', false);
        }

        $params['paginate'] = fspbx_pagination_per_page();
        $params['domain_uuid'] = $domainUuid;

        $startPeriod = Carbon::now(get_local_time_zone($domainUuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domainUuid))->endOfDay()->setTimeZone('UTC');

        if (! empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
        $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

        unset($params['filter']['dateRange']);

        return $this->cdrDataService->getRecorderData($params);
    }

}
