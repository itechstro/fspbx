<?php

namespace App\Mail;

use App\Services\CloudPlayApiService;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class AppCredentials extends BaseMailable
{
    public function __construct(array $attributes)
    {
        parent::__construct($attributes);

        $this->prepareQrCodeForEmail();

        $this->attributes['email_subject'] = config('app.name', 'FS PBX').' App Credentials';

        $this->useEmailTemplate('app', 'credentials');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.app.credentials',
            text: 'emails.app.credentials-text',
        ));
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
        $domainUuid = $this->attributes['domain_uuid'] ?? null;

        if ($domainUuid && app(CloudPlayApiService::class)->isConfiguredForDomain($domainUuid)) {
            $payload = trim((string) ($this->attributes['qr_code'] ?? ''));
            if ($payload !== '') {
                return $payload;
            }

            return app(CloudPlayApiService::class)->buildMobileAppQrPayload(
                $this->attributes,
                $domainUuid,
            );
        }

        return json_encode([
            'domain' => $this->attributes['domain'] ?? '',
            'username' => $this->attributes['username'] ?? '',
            'password' => $this->attributes['password'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
