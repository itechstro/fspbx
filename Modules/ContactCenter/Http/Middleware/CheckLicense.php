<?php

namespace Modules\ContactCenter\Http\Middleware;

use Closure;
use Inertia\Inertia;
use App\Models\ProFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\KeygenAPIService;  // Use your service for license validation

class CheckLicense
{
    protected $keygenApiService;

    public function __construct(KeygenAPIService $keygenApiService)
    {
        $this->keygenApiService = $keygenApiService;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!config('contactcenter.require_license', false)) {
            return $next($request);
        }

        $proFeature = ProFeatures::where('slug', 'fspbx')->first();

        if (!$proFeature || !$proFeature->license) {
            return Inertia::render('ErrorPage', [
                'status' => 404,
                'error' => 'License not found',
                'message' => 'The required license is missing.'
            ]);
        }

        $license = $proFeature->license;
        $isLicenseValid = Cache::get("license_validation_{$license}");

        if ($isLicenseValid === null) {
            $isLicenseValid = $this->validateAndCacheLicense($license);
        } elseif ($isLicenseValid === false) {
            $isLicenseValid = $this->validateAndCacheLicense($license, true);
        }

        if (!$isLicenseValid) {
            return Inertia::render('ErrorPage', [
                'status' => 404,
                'error' => 'License is invalid',
                'message' => 'License is invalid or expired.'
            ]);
        }

        return $next($request);
    }

    protected function validateAndCacheLicense($license, $forceUpdate = false)
    {
        if ($forceUpdate) {
            Cache::forget("license_validation_{$license}");
        }

        $licenseResponse = $this->keygenApiService->validateLicenseKey($license);
        $isLicenseValid = (bool) ($licenseResponse['meta']['valid'] ?? false);

        Cache::put("license_validation_{$license}", $isLicenseValid, now()->addHours(4));

        return $isLicenseValid;
    }
}
