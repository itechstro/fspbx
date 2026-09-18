<?php
// Idempotently ensure the admin domain exists. Run via:
//   php artisan tinker .cursor/dev/create_domain.php
// Must run before the legacy upgrade_domains.php so per-domain groups,
// permissions and default data are seeded for it.

$domain = \App\Models\Domain::firstOrCreate(
    ['domain_name' => 'admin.localhost'],
    ['domain_description' => 'Admin Domain', 'domain_enabled' => true]
);

echo 'admin domain: ' . $domain->domain_uuid . PHP_EOL;
