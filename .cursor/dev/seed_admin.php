<?php
// Idempotently ensure the FS PBX app schema/metadata and a superadmin user.
// Run via: php .cursor/dev/seed_admin.php
// Must run AFTER the legacy upgrade_domains.php so the superadmin group exists.
//
// This mirrors the relevant parts of the `fspbx:initial-seed` command without
// its production host-service side effects (FreeSWITCH / supervisor restarts,
// www-data ownership changes, etc.) which do not apply to a dev VM.

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Install\InstallSchema;
use App\Models\Domain;
use App\Models\User;
use App\Models\Groups;
use App\Models\UserGroup;
use App\Models\UserSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

$schema = app(InstallSchema::class);
$schema->ensureSchemas();
$schema->ensureMetadata();

$domain = Domain::where('domain_name', 'admin.localhost')->firstOrFail();

$username = 'fspbx@fspbx.com';
$password = 'Fspbx-Dev-1234!'; // Fixed dev credential for the isolated VM.

$user = User::where('user_email', $username)->first();
if ($user) {
    $user->update(['password' => Hash::make($password), 'user_enabled' => 'true']);
} else {
    $user = User::create([
        'domain_uuid' => $domain->domain_uuid,
        'username' => 'admin',
        'user_email' => $username,
        'password' => Hash::make($password),
        'user_enabled' => 'true',
    ]);
}

$group = Groups::where('group_name', 'superadmin')->firstOrFail();
if (! UserGroup::where('user_uuid', $user->user_uuid)->where('group_name', 'superadmin')->exists()) {
    UserGroup::create([
        'user_group_uuid' => Str::uuid(),
        'domain_uuid' => $domain->domain_uuid,
        'group_name' => 'superadmin',
        'group_uuid' => $group->group_uuid,
        'user_uuid' => $user->user_uuid,
    ]);
}

foreach ([['language', 'code', 'en-us'], ['time_zone', 'name', 'America/Los_Angeles']] as $s) {
    UserSetting::firstOrCreate(
        [
            'user_uuid' => $user->user_uuid,
            'user_setting_category' => 'domain',
            'user_setting_subcategory' => $s[0],
        ],
        [
            'user_setting_uuid' => Str::uuid(),
            'domain_uuid' => $domain->domain_uuid,
            'user_setting_category' => 'domain',
            'user_setting_subcategory' => $s[0],
            'user_setting_name' => $s[1],
            'user_setting_value' => $s[2],
            'user_setting_enabled' => true,
        ]
    );
}

echo 'superadmin ready: ' . $user->user_uuid . PHP_EOL;
