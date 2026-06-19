<?php

namespace App\Http\Controllers;

use App\Models\Extensions;
use App\Models\MobileAppPasswordResetLinks;
use App\Models\MobileAppUsers;
use App\Services\CloudPlayApiService;
use App\Services\MobileApp\MobileAppProviderResolver;
use App\Services\RingotelApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AppsCredentialsController extends Controller
{
    public function showQrCode(Request $request)
    {
        if (!$request->filled('payload')) {
            abort(404);
        }

        try {
            $payload = Crypt::decryptString($request->query('payload'));
        } catch (\Throwable $e) {
            abort(404);
        }

        $qrCode = QrCode::format('png')->generate($payload);

        return response($qrCode, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'private, max-age=2592000');
    }

    /**
     * Show the credentials view.
     */
    public function getPasswordByToken(Request $request): \Inertia\Response
    {
        $appCredentials = MobileAppPasswordResetLinks::where('token', $request->token)->first();

        if (!$appCredentials) {
            abort(403, 'The link does not exist or expired. Contact your administrator');
        }

        $extension = $appCredentials->extension()->first();
        $mobileApp = MobileAppUsers::where('extension_uuid', $appCredentials->extension_uuid)->first();
        $username = $extension->extension;

        if (get_mobile_app_provider() === 'cloudplay' && $extension) {
            $username = app(CloudPlayApiService::class)->buildMobileAppUsername(
                $extension->domain_uuid,
                (string) $extension->extension,
            );
        }

        return Inertia::render(
            'Auth/MobileAppGetPassword',
            [
                'display_name' => $extension->effective_caller_id_name,
                'domain' => $appCredentials->domain,
                'username' => $username,
                'extension' => $extension->extension,
                'routes' => [
                    'retrieve_password' => route('appsRetrievePasswordByToken', $request->token),
                ],
            ]
        );
    }

    /**
     * Attempt to retrieve the mobile app password.
     */
    public function retrievePasswordByToken(
        Request $request,
        MobileAppProviderResolver $providerResolver,
    ): \Illuminate\Http\JsonResponse {
        try {
            $appCredentials = MobileAppPasswordResetLinks::where('token', $request->token)->first();

            if (!$appCredentials) {
                abort(403, 'The link does not exist or expired. Contact your administrator');
            }

            $appUser = MobileAppUsers::where('extension_uuid', $appCredentials->extension_uuid)->first();
            $extension = Extensions::whereKey($appCredentials->extension_uuid)->firstOrFail();
            $provider = $providerResolver->resolve();

            $params = [
                'org_id' => $appUser->org_id,
                'user_id' => $appUser->user_id,
                'domain_uuid' => $extension->domain_uuid,
                'noemail' => true,
                'username' => $extension->extension,
                'ext' => $extension->extension,
                'authname' => $extension->extension,
                'password' => $extension->password,
                'name' => $extension->effective_caller_id_name,
                'email' => $extension->email ?? '',
            ];

            $user = $provider->resetPassword($params);

            $payload = $provider->getProviderKey() === 'cloudplay'
                ? app(CloudPlayApiService::class)->buildMobileAppQrPayload($user, $extension->domain_uuid)
                : json_encode([
                    'domain' => $user['domain'] ?? '',
                    'username' => $user['username'] ?? '',
                    'password' => $user['password'] ?? '',
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $qrcode = QrCode::format('png')->generate($payload);

            MobileAppPasswordResetLinks::where('token', $request->token)->delete();

            return response()->json([
                'qrcode' => $qrcode !== '' ? base64_encode($qrcode) : null,
                'password' => $user['password'],
            ]);
        } catch (\Exception $e) {
            logger('AppsCredentialsController@retrievePasswordByToken error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to retrieve credentials']],
            ], 500);
        }
    }
}
