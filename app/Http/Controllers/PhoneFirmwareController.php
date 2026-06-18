<?php

namespace App\Http\Controllers;

use App\Services\PhoneFirmwareProvisionSettingsService;
use App\Services\PhoneFirmwareService;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

class PhoneFirmwareController extends Controller
{
    public function index(Request $request)
    {
        if (! userCheckPermission('phone_firmware_view')) {
            return redirect('/');
        }

        return Inertia::render('PhoneFirmware', [
            'routes' => [
                'current_page' => route('phone-firmware.index'),
                'data_route' => route('phone-firmware.data'),
                'upload' => route('phone-firmware.upload'),
                'mkdir' => route('phone-firmware.mkdir'),
                'delete' => route('phone-firmware.delete'),
                'download' => route('phone-firmware.download'),
                'apply_provision' => route('phone-firmware.apply-provision'),
                'default_settings' => route('default-settings.index'),
                'domain_settings' => session('domain_uuid')
                    ? route('domains.settings.index', ['domain' => session('domain_uuid')])
                    : null,
            ],
            'permissions' => [
                'upload' => userCheckPermission('phone_firmware_upload'),
                'delete' => userCheckPermission('phone_firmware_delete'),
                'default_settings' => userCheckPermission('default_setting_edit'),
                'domain_settings' => userCheckPermission('domain_setting_edit') && (bool) session('domain_uuid'),
            ],
            'public_base_url' => $request->getSchemeAndHttpHost(),
            'domain_name' => session('domain_name'),
            'allowed_extensions' => PhoneFirmwareService::ALLOWED_EXTENSIONS,
            'max_upload_mb' => (int) (PhoneFirmwareService::MAX_UPLOAD_BYTES / 1048576),
        ]);
    }

    public function getData(
        Request $request,
        PhoneFirmwareService $service,
        PhoneFirmwareProvisionSettingsService $provisionSettings,
    ): JsonResponse {
        if (! userCheckPermission('phone_firmware_view')) {
            return $this->denied();
        }

        try {
            $path = (string) $request->query('path', '');
            $publicBaseUrl = $request->getSchemeAndHttpHost();
            $payload = $service->listDirectory($path, $publicBaseUrl);
            $payload['provision'] = $provisionSettings->preview($path, $publicBaseUrl);

            return response()->json($payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        }
    }

    public function applyProvision(
        Request $request,
        PhoneFirmwareProvisionSettingsService $provisionSettings,
    ): JsonResponse {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'in:default,domain'],
        ]);

        $scope = $validated['scope'];

        if ($scope === 'default' && ! userCheckPermission('default_setting_edit')) {
            return $this->denied();
        }

        if ($scope === 'domain' && ! userCheckPermission('domain_setting_edit')) {
            return $this->denied();
        }

        $domain = null;
        if ($scope === 'domain') {
            $domainUuid = session('domain_uuid');
            if (! $domainUuid) {
                return response()->json([
                    'messages' => ['error' => ['Select a domain before applying domain settings.']],
                ], 422);
            }

            $domain = Domain::query()->where('domain_uuid', $domainUuid)->first();
            if (! $domain) {
                return response()->json([
                    'messages' => ['error' => ['Current domain was not found.']],
                ], 422);
            }
        }

        try {
            $result = $provisionSettings->apply(
                $scope,
                $validated['path'],
                $request->getSchemeAndHttpHost(),
                $domain,
            );

            $scopeLabel = $scope === 'domain'
                ? 'domain settings for ' . ($domain->domain_description ?: $domain->domain_name)
                : 'default settings';

            return response()->json([
                'data' => $result,
                'messages' => ['success' => [
                    sprintf(
                        'Applied %s firmware upgrade settings to %s.',
                        $result['label'],
                        $scopeLabel,
                    ),
                ]],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        }
    }

    public function upload(Request $request, PhoneFirmwareService $service): JsonResponse
    {
        if (! userCheckPermission('phone_firmware_upload')) {
            return $this->denied();
        }

        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:' . (PhoneFirmwareService::MAX_UPLOAD_BYTES / 1024)],
        ]);

        try {
            $result = $service->uploadFile(
                (string) ($validated['path'] ?? ''),
                $request->file('file'),
            );

            return response()->json([
                'data' => $result,
                'messages' => ['success' => ['Firmware file uploaded successfully.']],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'messages' => ['error' => ['Could not upload firmware file.']],
            ], 500);
        }
    }

    public function mkdir(Request $request, PhoneFirmwareService $service): JsonResponse
    {
        if (! userCheckPermission('phone_firmware_upload')) {
            return $this->denied();
        }

        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $result = $service->createDirectory(
                (string) ($validated['path'] ?? ''),
                $validated['name'],
            );

            return response()->json([
                'data' => $result,
                'messages' => ['success' => ['Folder created successfully.']],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        }
    }

    public function destroy(Request $request, PhoneFirmwareService $service): JsonResponse
    {
        if (! userCheckPermission('phone_firmware_delete')) {
            return $this->denied();
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        try {
            $service->deletePath($validated['path']);

            return response()->json([
                'messages' => ['success' => ['Deleted successfully.']],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'messages' => ['error' => [$exception->getMessage()]],
            ], 422);
        }
    }

    public function download(Request $request, PhoneFirmwareService $service)
    {
        if (! userCheckPermission('phone_firmware_view')) {
            abort(403);
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        try {
            $absolutePath = $service->downloadAbsolutePath($validated['path']);

            return Response::download($absolutePath, basename($absolutePath));
        } catch (InvalidArgumentException $exception) {
            abort(404, $exception->getMessage());
        }
    }

    private function denied(): JsonResponse
    {
        return response()->json([
            'messages' => ['error' => ['Access denied.']],
        ], 403);
    }
}
