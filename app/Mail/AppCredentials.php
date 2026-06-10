<?php

namespace App\Mail;

use App\Services\CloudPlayApiService;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class AppCredentials extends BaseMailable
{
    public function envelope(): Envelope
    {
        $fromEmail = $this->attributes['from_email'] ?? config('mail.from.address');
        $fromName = $this->attributes['from_name'] ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: config('app.name', 'Laravel') . ' App Credentials',
        );
    }

    public function content(): Content
    {
        $this->prepareQrCodeForEmail();

        return new Content(
            view: 'emails.app.credentials',
            text: 'emails.app.credentials-text',
        );
    }

    protected function prepareQrCodeForEmail(): void
    {
        if (!empty($this->attributes['qrCodeUrl'])) {
            return;
        }

        if ((int) ($this->attributes['status'] ?? 1) !== 1) {
            return;
        }

        $domainUuid = $this->attributes['domain_uuid'] ?? null;
        if (get_domain_setting('dont_send_user_credentials', $domainUuid) === 'true') {
            return;
        }

        try {
            $payload = $this->resolveQrPayload();

            if ($payload === '') {
                return;
            }

            $this->attributes['qrCodeUrl'] = URL::temporarySignedRoute(
                'appsMobileAppQr',
                now()->addDays(30),
                ['payload' => Crypt::encryptString($payload)]
            );
        } catch (\Throwable $e) {
            Log::warning('App credentials QR generation failed: ' . $e->getMessage());
        }
    }

    protected function resolveQrPayload(): string
    {
        if (get_mobile_app_provider() === 'cloudplay') {
            $domainUuid = $this->attributes['domain_uuid'] ?? null;
            $userId = (int) ($this->attributes['id'] ?? 0);

            if ($domainUuid && $userId > 0) {
                try {
                    $qrCode = app(CloudPlayApiService::class)->getQrCode($domainUuid, $userId);

                    if (!empty($qrCode)) {
                        return $qrCode;
                    }
                } catch (\Throwable $e) {
                    Log::warning('CloudPLAY getQrCode failed for app credentials email: ' . $e->getMessage());
                }
            }
        }

        return json_encode([
            'domain' => $this->attributes['domain'] ?? '',
            'username' => $this->attributes['username'] ?? '',
            'password' => $this->attributes['password'] ?? '',
        ]);
    }
}
