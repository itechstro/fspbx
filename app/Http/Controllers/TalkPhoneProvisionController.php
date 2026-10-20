<?php

namespace App\Http\Controllers;

use App\Mail\TalkPhoneProvisionMail;
use App\Models\Devices;
use App\Services\Provisioning\TalkPhoneProvisionException;
use App\Services\Provisioning\TalkPhoneProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class TalkPhoneProvisionController extends Controller
{
    public function __construct(private TalkPhoneProvisionService $provision)
    {
    }

    public function show(string $deviceUuid)
    {
        try {
            $payload = $this->provision->payloadForUuid($deviceUuid);
        } catch (TalkPhoneProvisionException) {
            abort(404);
        }

        return response()->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function preview(Devices $device): JsonResponse
    {
        $this->authorizeDevice($device);

        try {
            $payload = $this->provision->payload($device);
        } catch (TalkPhoneProvisionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $summary = $payload;
        unset($summary['password']);

        return response()->json([
            'url' => $this->provision->provisionUrl($device),
            'summary' => $summary,
        ]);
    }

    public function qr(Devices $device)
    {
        $this->authorizeDevice($device);

        try {
            $this->provision->payload($device);
            $png = $this->provision->qrPng($this->provision->provisionUrl($device));
        } catch (TalkPhoneProvisionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response($png, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, private');
    }

    public function email(Devices $device): JsonResponse
    {
        $this->authorizeDevice($device);

        try {
            $payload = $this->provision->payload($device);
        } catch (TalkPhoneProvisionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['server' => [$e->getMessage()]],
            ], 422);
        }

        $emails = $this->provision->recipientEmails($device);
        if ($emails === []) {
            $message = __('This extension has no voicemail email address.');

            return response()->json([
                'message' => $message,
                'errors' => ['server' => [$message]],
            ], 422);
        }

        try {
            $url = $this->provision->provisionUrl($device);
            Mail::to($emails)->send(new TalkPhoneProvisionMail([
                'domain_uuid' => $device->domain_uuid,
                'display_name' => $payload['displayName'],
                'extension' => $payload['sipUri'],
                'server' => $payload['server'],
                'provision_url' => $url,
                'qr_png' => $this->provision->qrPng($url),
                'email_subject' => config('app.name', 'FS PBX').' TalkPhone setup',
            ]));
        } catch (\Throwable $e) {
            logger('TalkPhoneProvisionController@email error: '.$e->getMessage());

            return response()->json([
                'message' => __('Failed to send the provisioning email.'),
                'errors' => ['server' => [__('Failed to send the provisioning email.')]],
            ], 500);
        }

        return response()->json([
            'messages' => ['success' => [__('Provisioning email sent.')]],
        ]);
    }

    private function authorizeDevice(Devices $device): void
    {
        if (! userCheckPermission('device_edit')) {
            abort(403);
        }

        if (userCheckPermission('device_all')) {
            return;
        }

        $sessionDomain = session('domain_uuid');
        if ($sessionDomain && $device->domain_uuid !== $sessionDomain) {
            abort(403);
        }
    }
}
