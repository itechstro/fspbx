<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ContactSyncConnection extends Model
{
    use \App\Models\Traits\TraitUuid;

    public const PROVIDER_GOOGLE = 'google';

    public const PROVIDER_MICROSOFT = 'microsoft';

    protected $table = 'contact_sync_connections';

    public $timestamps = false;

    protected $primaryKey = 'contact_sync_connection_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'provider',
        'account_email',
        'refresh_token',
        'access_token',
        'token_expires_at',
        'scopes',
        'sync_enabled',
        'connected_by_user_uuid',
        'last_sync_at',
        'last_sync_status',
        'last_sync_message',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function isSyncEnabled(): bool
    {
        return $this->sync_enabled === 'true' || $this->sync_enabled === true;
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
