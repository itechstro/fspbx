<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainUsageLimitExceededException;
use App\Jobs\SendRecorderAnalyticsReport;
use App\Models\RecorderAnalyticsReportSchedule;
use App\Services\CdrDataService;
use App\Services\RecorderAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class RecorderAnalyticsController extends Controller
{
    public function __construct(
        protected CdrDataService $cdrDataService,
        protected RecorderAnalyticsService $analyticsService,
    ) {
    }

    public function index(Request $request)
    {
        if (! userCheckPermission('recorder_analytics_view') || ! userCheckPermission('recorder_view')) {
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

        return Inertia::render('RecorderAnalytics', [
            'startPeriod' => fn () => $startPeriod,
            'endPeriod' => fn () => $endPeriod,
            'timezone' => fn () => get_local_time_zone($domainUuid),
            'routes' => [
                'current_page' => route('recorder.analytics.index'),
                'report_route' => route('recorder.analytics.report'),
                'export_route' => route('recorder.analytics.export'),
                'executive_summary_route' => route('recorder.analytics.executive-summary'),
                'schedule_route' => route('recorder.analytics.schedule'),
                'send_route' => route('recorder.analytics.send'),
                'recorder_page' => route('recorder.index'),
            ],
            'permissions' => fn () => [
                'schedule' => userCheckPermission('recorder_analytics_schedule'),
            ],
            'executiveSummaryAvailable' => $this->analyticsService->isExecutiveSummaryAvailable(),
        ]);
    }

    public function report(Request $request)
    {
        $this->authorizeAnalytics();

        $domainUuid = session('domain_uuid');
        [$startPeriod, $endPeriod, $search] = $this->resolveReportFilters($request, $domainUuid);

        return response()->json(
            $this->analyticsService->buildReport($domainUuid, $startPeriod, $endPeriod, $search)
        );
    }

    public function export(Request $request)
    {
        $this->authorizeAnalytics();

        $domainUuid = session('domain_uuid');
        [$startPeriod, $endPeriod, $search] = $this->resolveReportFilters($request, $domainUuid);
        $report = $this->analyticsService->buildReport($domainUuid, $startPeriod, $endPeriod, $search);
        $filename = $this->analyticsService->csvFilename($report);

        return response($this->analyticsService->buildCsvContent($report), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function executiveSummary(Request $request)
    {
        $this->authorizeAnalytics();

        if (! $this->analyticsService->isExecutiveSummaryAvailable()) {
            return response()->json([
                'errors' => ['executive_summary' => ['OpenAI API key is not configured.']],
            ], 422);
        }

        $domainUuid = session('domain_uuid');
        [$startPeriod, $endPeriod, $search] = $this->resolveReportFilters($request, $domainUuid);
        $report = $this->analyticsService->buildReport($domainUuid, $startPeriod, $endPeriod, $search);

        try {
            return response()->json([
                'executive_summary' => $this->analyticsService->generateExecutiveSummary($report),
            ]);
        } catch (DomainUsageLimitExceededException $exception) {
            return response()->json([
                'errors' => $exception->toErrorBag(),
            ], 403);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'errors' => ['executive_summary' => [$exception->getMessage()]],
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'errors' => ['executive_summary' => ['Unable to generate executive summary. Please try again.']],
            ], 502);
        }
    }

    public function schedule(Request $request)
    {
        $this->authorizeAnalytics();

        if (! userCheckPermission('recorder_analytics_schedule')) {
            abort(403);
        }

        $domainUuid = session('domain_uuid');

        if ($request->isMethod('get')) {
            $schedule = RecorderAnalyticsReportSchedule::query()
                ->firstOrCreate(
                    ['domain_uuid' => $domainUuid],
                    [
                        'enabled' => false,
                        'include_executive_summary' => false,
                        'emails' => [],
                        'frequency' => 'weekly',
                        'send_time' => '08:00',
                        'day_of_week' => 1,
                        'day_of_month' => 1,
                    ]
                );

            return response()->json(['schedule' => $schedule]);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'include_executive_summary' => ['sometimes', 'boolean'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'send_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $emails = $this->analyticsService->normalizeEmails($data['emails'] ?? []);

        if ($data['enabled'] && $emails === []) {
            return response()->json([
                'errors' => ['emails' => ['Add at least one valid email address to enable scheduled reports.']],
            ], 422);
        }

        $search = trim((string) ($data['search'] ?? ''));
        $search = $search !== '' ? $search : null;

        $schedule = RecorderAnalyticsReportSchedule::query()->updateOrCreate(
            ['domain_uuid' => $domainUuid],
            [
                'enabled' => (bool) $data['enabled'],
                'include_executive_summary' => (bool) ($data['include_executive_summary'] ?? false),
                'search' => $search,
                'emails' => $emails,
                'frequency' => $data['frequency'],
                'send_time' => $data['send_time'],
                'day_of_week' => $data['frequency'] === 'weekly'
                    ? ($data['day_of_week'] ?? 1)
                    : null,
                'day_of_month' => $data['frequency'] === 'monthly'
                    ? ($data['day_of_month'] ?? 1)
                    : null,
            ]
        );

        return response()->json([
            'messages' => ['success' => ['Scheduled report settings saved.']],
            'schedule' => $schedule->fresh(),
        ]);
    }

    public function send(Request $request)
    {
        $this->authorizeAnalytics();

        if (! userCheckPermission('recorder_analytics_schedule')) {
            abort(403);
        }

        $domainUuid = session('domain_uuid');
        $data = $request->validate([
            'emails' => ['required', 'array', 'min:1'],
            'emails.*' => ['email'],
            'filter.dateRange' => ['required', 'array', 'size:2'],
            'filter.dateRange.0' => ['required', 'date'],
            'filter.dateRange.1' => ['required', 'date'],
            'filter.search' => ['nullable', 'string', 'max:200'],
            'include_executive_summary' => ['sometimes', 'boolean'],
        ]);

        $emails = $this->analyticsService->normalizeEmails($data['emails']);
        if ($emails === []) {
            return response()->json([
                'errors' => ['emails' => ['Add at least one valid email address.']],
            ], 422);
        }

        [$startPeriod, $endPeriod, $search] = $this->resolveReportFilters($request, $domainUuid);

        SendRecorderAnalyticsReport::dispatch(
            $domainUuid,
            $startPeriod->toIso8601String(),
            $endPeriod->toIso8601String(),
            $emails,
            (bool) ($data['include_executive_summary'] ?? false),
            $search,
        )->onQueue('emails');

        return response()->json([
            'messages' => ['success' => ['Report email queued.']],
        ], 202);
    }

    protected function authorizeAnalytics(): void
    {
        if (! userCheckPermission('recorder_analytics_view') || ! userCheckPermission('recorder_view')) {
            abort(403);
        }

        if (! $this->cdrDataService->isRecorderEnabled(session('domain_uuid'))) {
            abort(404);
        }
    }

    protected function resolveDateRange(Request $request, string $domainUuid): array
    {
        $timezone = get_local_time_zone($domainUuid);
        $startPeriod = Carbon::now($timezone)->startOfDay()->utc();
        $endPeriod = Carbon::now($timezone)->endOfDay()->utc();

        $dateRange = $request->input('filter.dateRange');
        if (is_array($dateRange) && count($dateRange) === 2) {
            $startPeriod = Carbon::parse($dateRange[0])->setTimezone('UTC');
            $endPeriod = Carbon::parse($dateRange[1])->setTimezone('UTC');
        }

        return [$startPeriod, $endPeriod];
    }

    protected function resolveReportFilters(Request $request, string $domainUuid): array
    {
        [$startPeriod, $endPeriod] = $this->resolveDateRange($request, $domainUuid);
        $search = trim((string) $request->input('filter.search', ''));

        return [$startPeriod, $endPeriod, $search !== '' ? $search : null];
    }
}
