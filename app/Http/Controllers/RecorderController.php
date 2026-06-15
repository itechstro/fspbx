<?php

namespace App\Http\Controllers;

use App\Services\CdrDataService;
use App\Services\CallTranscription\CallTranscriptionService;
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
        if (! userCheckPermission('xml_cdr_view')) {
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
            ],
            'permissions' => fn () => $this->recorderPermissions(),
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
        ]);
    }

    public function getData()
    {
        if (! userCheckPermission('xml_cdr_view')) {
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

    private function recorderPermissions(): array
    {
        $transcriptionService = app(CallTranscriptionService::class);
        $config = $transcriptionService->getCachedConfig(session('domain_uuid') ?? null);
        $isCallTranscriptionServiceEnabled = (bool) ($config['enabled'] ?? false);

        return [
            'all_cdr_view' => userCheckPermission('xml_cdr_domain'),
            'call_recording_play' => userCheckPermission('call_recording_play'),
            'transcription_summary' => userCheckPermission('transcription_summary'),
            'search_sentiment' => userCheckPermission('xml_cdr_search_sentiment')
                && $isCallTranscriptionServiceEnabled
                && (bool) ($config['auto_summarize_recorder'] ?? false),
        ];
    }
}
