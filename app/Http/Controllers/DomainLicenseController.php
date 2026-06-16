<?php

namespace App\Http\Controllers;

use App\Models\CallTranscription;
use App\Models\Domain;
use App\Services\DomainLicenseService;
use App\Services\DomainUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DomainLicenseController extends Controller
{
    public function __construct(
        protected DomainLicenseService $domainLicenseService,
        protected DomainUsageService $domainUsageService,
    ) {
    }

    public function index(Domain $domain): Response|\Illuminate\Http\RedirectResponse
    {
        if (! userCheckPermission('domain_license_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return redirect('/');
        }

        return Inertia::render('DomainLicense', [
            'domain' => [
                'domain_uuid' => $domain->domain_uuid,
                'domain_name' => $domain->domain_name,
                'domain_description' => $domain->domain_description,
            ],
            'routes' => [
                'domains' => route('domains.index'),
                'domain_settings' => route('domains.settings.index', ['domain' => $domain]),
                'usage' => route('domains.license.usage', ['domain' => $domain]),
                'update' => route('domains.license.update', ['domain' => $domain]),
                'usage_details' => userCheckPermission('domain_license_usage_details_view')
                    ? route('domains.license.usage-details.index', ['domain' => $domain])
                    : null,
                'ai_usage_rates' => userCheckPermission('ai_usage_rates_view')
                    ? route('system-settings.index', ['tab' => 'ai_usage_rates'])
                    : null,
            ],
            'permissions' => [
                'edit' => userCheckPermission('domain_license_edit'),
                'usage_details' => userCheckPermission('domain_license_usage_details_view'),
                'ai_usage_rates' => userCheckPermission('ai_usage_rates_view'),
            ],
        ]);
    }

    public function usage(Request $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_license_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            abort(403);
        }

        $period = $request->string('period')->toString() ?: null;

        return response()->json(
            $this->domainLicenseService->buildPageData($domain->domain_uuid, $period)
        );
    }

    public function update(Request $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_license_edit') || ! $this->canAccessDomain($domain->domain_uuid)) {
            abort(403);
        }

        $data = $request->validate([
            'limits' => ['required', 'array'],
            'limits.*.key' => ['required', 'string', 'max:64'],
            'limits.*.enabled' => ['required', 'boolean'],
            'limits.*.value' => ['nullable', 'numeric', 'min:0'],
            'limits.*.revert' => ['sometimes', 'boolean'],
        ]);

        foreach ($data['limits'] as $row) {
            $key = (string) $row['key'];

            if (! empty($row['revert'])) {
                $this->domainLicenseService->revertLimit($domain->domain_uuid, $key);
                continue;
            }

            $this->domainLicenseService->updateLimit(
                $domain->domain_uuid,
                $key,
                (bool) $row['enabled'],
                isset($row['value']) ? (string) $row['value'] : null,
            );
        }

        return response()->json([
            'messages' => ['success' => ['License limits saved.']],
            'data' => $this->domainLicenseService->buildPageData($domain->domain_uuid),
        ]);
    }

    public function usageDetailsIndex(Domain $domain): Response|\Illuminate\Http\RedirectResponse
    {
        if (! userCheckPermission('domain_license_usage_details_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return redirect('/');
        }

        return Inertia::render('DomainLicenseUsageDetails', [
            'domain' => [
                'domain_uuid' => $domain->domain_uuid,
                'domain_name' => $domain->domain_name,
                'domain_description' => $domain->domain_description,
            ],
            'routes' => [
                'domains' => route('domains.index'),
                'license' => route('domains.license.index', ['domain' => $domain]),
                'data' => route('domains.license.usage-details.data', ['domain' => $domain]),
                'export_csv' => route('domains.license.usage-details.export', ['domain' => $domain]),
            ],
        ]);
    }

    public function usageDetailsData(Request $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_license_usage_details_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            abort(403);
        }

        $period = $request->string('period')->toString()
            ?: $this->domainUsageService->currentPeriod($domain->domain_uuid);
        $perPage = min(100, max(10, (int) $request->integer('per_page', 25)));

        $paginator = $this->domainLicenseService
            ->usageDetailsQuery($domain->domain_uuid, $period)
            ->paginate($perPage);

        $paginator->getCollection()->transform(
            fn (CallTranscription $row) => $this->domainLicenseService->mapUsageDetailRow($row, $domain->domain_uuid)
        );

        return response()->json([
            'period' => $period,
            'summary' => $this->domainLicenseService->buildUsageDetailsSummary($domain->domain_uuid, $period),
            'rows' => $paginator,
            'executive_summaries' => $this->domainLicenseService->executiveSummaryRunsForPeriod(
                $domain->domain_uuid,
                $period
            ),
        ]);
    }

    public function usageDetailsExport(Request $request, Domain $domain)
    {
        if (! userCheckPermission('domain_license_usage_details_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            abort(403);
        }

        $period = $request->string('period')->toString()
            ?: $this->domainUsageService->currentPeriod($domain->domain_uuid);

        $csv = $this->domainLicenseService->buildUsageDetailsCsvContent($domain->domain_uuid, $period);
        $filename = $this->domainLicenseService->usageDetailsCsvFilename($domain->domain_uuid, $period);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function canAccessDomain(string $domainUuid): bool
    {
        $domains = session('domains', []);

        if (! is_array($domains) || $domains === []) {
            return session('domain_uuid') === $domainUuid;
        }

        return collect($domains)->contains(fn ($domain) => ($domain['domain_uuid'] ?? null) === $domainUuid);
    }
}
