# Contact Center Module

Self-hosted Contact Center for FS PBX. This itechstro fork ships the module in `Modules/ContactCenter` instead of downloading it from Keygen.

## Requirements

- FS PBX with `mod_callcenter` available on FreeSWITCH
- Queue/agent tables already used by Basic Queues
- Built frontend assets (`npm run build`)

## First-time install

After pulling the itechstro repo on a server:

```bash
cd /var/www/fspbx
git pull origin main
composer install --no-dev
npm ci && npm run build
php artisan app:update
```

`app:update` runs the Contact Center update step when needed, rebuilds assets, and seeds module permissions.

### Manual install (if you are not using `app:update`)

```bash
php artisan module:enable ContactCenter
php artisan module:seed ContactCenter --force
php artisan optimize:clear
npm run build
```

## Configuration

License checks are off by default for self-hosted installs. Optional `.env` setting:

```env
CONTACT_CENTER_REQUIRE_LICENSE=false
```

Set `CONTACT_CENTER_REQUIRE_LICENSE=true` only if you want the module to require a valid FS PBX Pro license.

## Permissions

The module seeder creates Contact Center groups and permissions, including:

- `contact_center_dashboard_view`
- `contact_center_settings_edit`
- `contact_center_agent_create`
- `contact_center_agent_assign`
- `contact_center_agent_unassign`
- `contact_center_agent_delete`

`superadmin` and `Contact Center Admin` receive the main admin permissions from the seeder. Assign permissions to other groups as needed.

## Usage

- Dashboard: `/contact-center`
- Settings: `/contact-center/settings`

Queues use the shared Basic Queue form with an extra **Tier Rules & More** tab for Contact Center options.

## Upgrade on another server

```bash
git pull origin main
php artisan app:update
```

The update step enables the module when `Modules/ContactCenter` is present, seeds permissions, and the normal update flow rebuilds frontend assets.

## Troubleshooting

- **Contact Center missing from the app menu:** confirm `modules_statuses.json` contains `"ContactCenter": true`, then run `php artisan module:enable ContactCenter` and `php artisan optimize:clear`.
- **Settings page errors after deploy:** run `npm run build` and ensure `storage/framework/views` is writable by the web user.
- **Queue save fails with permission denied on views:** fix ownership on `storage/` and `bootstrap/cache/` for `www-data`.
