<?php
// Idempotently ensure the admin domain exists.
// Run via: php .cursor/dev/create_domain.php
// Must run before the legacy upgrade_domains.php so per-domain groups,
// permissions and default data are seeded for it.

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$domain = \App\Models\Domain::firstOrCreate(
    ['domain_name' => 'admin.localhost'],
    ['domain_description' => 'Admin Domain', 'domain_enabled' => true]
);

echo 'admin domain: ' . $domain->domain_uuid . PHP_EOL;
