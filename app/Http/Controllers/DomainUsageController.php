<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DomainUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainUsageController extends Controller
{
    public function __construct(
        protected DomainUsageService $domainUsageService,
    ) {
    }

    public function show(Request $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_license_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            abort(403);
        }

        $period = $request->string('period')->toString() ?: null;

        return response()->json(
            $this->domainUsageService->buildSummary($domain->domain_uuid, $period)
        );
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
