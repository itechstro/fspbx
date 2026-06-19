<?php

namespace App\Models;

use App\Models\Extensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class MobileAppUsers extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "mobile_app_users";

    public $timestamps = true;

    protected $primaryKey = 'mobile_app_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get extesnion that this mobile app belongs to 
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'extension_uuid', 'extension_uuid');
    }

    public function scopeCountsTowardLimit($query)
    {
        return $query->whereIn('status', [1, '1']);
    }

    public static function countActiveLicensesForDomain(string $domainUuid): int
    {
        return (int) static::query()
            ->where('domain_uuid', $domainUuid)
            ->countsTowardLimit()
            ->count();
    }

    public function storeAppPassword(?string $plainPassword): void
    {
        if ($plainPassword === null || $plainPassword === '') {
            $this->app_password = null;

            return;
        }

        $this->app_password = Crypt::encryptString($plainPassword);
    }

    public function readAppPassword(): ?string
    {
        if (empty($this->app_password)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->app_password);
        } catch (\Throwable) {
            return null;
        }
    }

}
