#!/usr/bin/env bash
#
# FS PBX Cloud Agent development environment installer.
#
# Brings up a working development instance of the FS PBX web application
# (Laravel + Inertia/Vue) backed by PostgreSQL and Redis. FreeSWITCH and the
# production nginx / php-fpm / supervisor stack are intentionally NOT installed;
# the web application runs via `php artisan serve` and degrades gracefully for
# pages that would otherwise talk to a live FreeSWITCH over ESL.
#
# The script is idempotent: it is safe to run repeatedly.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

# Fixed local development database password. This is a throwaway credential for
# an isolated dev VM only; production uses a randomly generated password.
DB_PASSWORD="fspbx_dev_pw"
PG_VERSION_DEFAULT="16"

log()  { echo -e "\e[36m[install]\e[0m $*"; }
warn() { echo -e "\e[33m[install]\e[0m $*"; }

export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
# 1. System packages: PHP 8.4 (Sury), Composer, PostgreSQL, Redis, Node
# ---------------------------------------------------------------------------
if ! command -v php8.4 >/dev/null 2>&1; then
  log "Installing base tooling, Redis and PostgreSQL..."
  sudo apt-get update -y
  sudo apt-get install -y --no-install-recommends \
    ca-certificates curl wget gnupg2 lsb-release apt-transport-https unzip git ffmpeg \
    redis-server postgresql postgresql-contrib

  log "Adding Sury PHP repository..."
  sudo mkdir -p /etc/apt/keyrings
  curl -fsSL https://packages.sury.org/php/apt.gpg | sudo gpg --dearmor --batch --yes -o /etc/apt/keyrings/sury-php.gpg
  sudo chmod 0644 /etc/apt/keyrings/sury-php.gpg
  codename="$(. /etc/os-release && echo "${VERSION_CODENAME}")"
  echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${codename} main" \
    | sudo tee /etc/apt/sources.list.d/php-sury.list >/dev/null
  sudo apt-get update -y

  log "Installing PHP 8.4 and extensions..."
  sudo apt-get install -y --no-install-recommends \
    php8.4 php8.4-cli php8.4-fpm \
    php8.4-pgsql php8.4-sqlite3 \
    php8.4-curl php8.4-xml php8.4-gd php8.4-mbstring php8.4-ldap \
    php8.4-zip php8.4-bcmath php8.4-intl php8.4-soap php8.4-gmp \
    php8.4-redis php8.4-igbinary
else
  log "PHP 8.4 already installed; skipping system package install."
fi

# Make php8.4 the default `php`.
sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.4 84 >/dev/null 2>&1 || true
sudo update-alternatives --set php /usr/bin/php8.4 >/dev/null 2>&1 || true

if ! command -v composer >/dev/null 2>&1; then
  log "Installing Composer..."
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  sudo php8.4 /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

# ---------------------------------------------------------------------------
# 2. Start PostgreSQL and Redis (no systemd on the Cloud Agent VM)
# ---------------------------------------------------------------------------
PG_VERSION="$(ls /etc/postgresql 2>/dev/null | sort -n | tail -1 || echo "$PG_VERSION_DEFAULT")"
log "Ensuring PostgreSQL ${PG_VERSION} is running..."
# Match the production installer: use md5 auth for simple local connections.
sudo sed -i 's/scram-sha-256/md5/g' "/etc/postgresql/${PG_VERSION}/main/pg_hba.conf" || true
sudo pg_ctlcluster "$PG_VERSION" main start 2>/dev/null || sudo pg_ctlcluster "$PG_VERSION" main restart 2>/dev/null || true
for i in $(seq 1 20); do
  sudo -u postgres psql -tc "SELECT 1;" >/dev/null 2>&1 && break
  sleep 1
done

log "Ensuring Redis is running..."
redis-cli ping >/dev/null 2>&1 || sudo redis-server --daemonize yes

# ---------------------------------------------------------------------------
# 3. Database, roles and extensions (idempotent)
# ---------------------------------------------------------------------------
log "Configuring database and roles..."
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
SELECT 'CREATE DATABASE fusionpbx' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname='fusionpbx')\gexec
DO \$do\$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='fusionpbx') THEN
      CREATE ROLE fusionpbx WITH SUPERUSER LOGIN PASSWORD '${DB_PASSWORD}';
   ELSE
      ALTER ROLE fusionpbx WITH SUPERUSER LOGIN PASSWORD '${DB_PASSWORD}';
   END IF;
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='freeswitch') THEN
      CREATE ROLE freeswitch WITH LOGIN PASSWORD '${DB_PASSWORD}';
   END IF;
END
\$do\$;
GRANT ALL PRIVILEGES ON DATABASE fusionpbx TO fusionpbx;
SQL
sudo -u postgres psql -d fusionpbx -c 'CREATE EXTENSION IF NOT EXISTS "uuid-ossp";' >/dev/null

# Legacy FusionPBX config consumed by the bundled upgrade_*.php scripts.
log "Writing /etc/fusionpbx/config.conf..."
sudo mkdir -p /etc/fusionpbx /var/cache/fusionpbx
sudo cp install/fusionpbx_config.conf /etc/fusionpbx/config.conf
sudo sed -i "s|{database_password}|${DB_PASSWORD}|" /etc/fusionpbx/config.conf
sudo sed -i "s|document.root = .*|document.root = ${APP_DIR}/public|" /etc/fusionpbx/config.conf

# ---------------------------------------------------------------------------
# 4. FusionPBX legacy overlay (untracked public/ directory)
# ---------------------------------------------------------------------------
if [ ! -f public/core/upgrade/upgrade_schema.php ]; then
  log "Downloading FusionPBX release into public/ ..."
  FUSIONPBX_VERSION="$(curl -s https://api.github.com/repos/nemerald-voip/fusionpbx/releases/latest | grep '"tag_name"' | cut -d '"' -f 4)"
  FUSIONPBX_VERSION="${FUSIONPBX_VERSION:-v1.2.6}"
  log "FusionPBX version: ${FUSIONPBX_VERSION}"
  mkdir -p public
  wget -q -O /tmp/fusionpbx.tar.gz "https://github.com/nemerald-voip/fusionpbx/archive/refs/tags/${FUSIONPBX_VERSION}.tar.gz"
  tar -xzf /tmp/fusionpbx.tar.gz -C public --strip-components=1
  rm -f /tmp/fusionpbx.tar.gz
  cp install/index.php public/index.php
  cp install/check_auth.php public/resources/check_auth.php
else
  log "FusionPBX overlay already present; skipping download."
fi

# ---------------------------------------------------------------------------
# 5. Environment file and PHP dependencies
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
  log "Creating .env for local development..."
  cp .env.example .env
  sed -i 's|^APP_ENV=production|APP_ENV=local|' .env
  sed -i 's|^APP_DEBUG=false|APP_DEBUG=true|' .env
  sed -i 's|^APP_URL=.*|APP_URL=http://localhost:8000|' .env
  sed -i 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=localhost|' .env
  sed -i 's|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost,127.0.0.1|' .env
  sed -i 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' .env
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
  sed -i 's|^MAIL_MAILER=.*|MAIL_MAILER=log|' .env
fi

log "Installing Composer dependencies..."
composer install --prefer-dist --no-progress --no-interaction

if ! grep -q '^APP_KEY=base64:' .env; then
  log "Generating application key..."
  php artisan key:generate --force
fi

# ---------------------------------------------------------------------------
# 6. Database bootstrap (schema, defaults, migrations, seeders, superadmin)
# ---------------------------------------------------------------------------
log "Building core FusionPBX schema..."
php public/core/upgrade/upgrade_schema.php >/dev/null 2>&1 || true

log "Ensuring admin domain exists..."
php artisan tinker .cursor/dev/create_domain.php >/dev/null

log "Seeding groups, permissions and default settings..."
php public/core/upgrade/upgrade_domains.php >/dev/null 2>&1 || true
php public/core/upgrade/upgrade.php >/dev/null 2>&1 || true

log "Ensuring app schema/metadata and superadmin user..."
php artisan tinker .cursor/dev/seed_admin.php

log "Running Laravel migrations..."
php artisan migrate --force

log "Seeding application data..."
php artisan db:seed --force
php artisan db:seed --class=RecommendedSettingsSeeder --force
php artisan db:seed --class=DeviceVendorsSeeder --force
php artisan menu:create-fspbx >/dev/null
php artisan prov:templates:seed >/dev/null
php artisan email:templates:seed >/dev/null
php artisan storage:link >/dev/null 2>&1 || true
cp -n install/assets/* storage/app/public/ 2>/dev/null || true

# ---------------------------------------------------------------------------
# 7. Frontend assets
# ---------------------------------------------------------------------------
log "Installing and building frontend assets..."
if [ -f package-lock.json ]; then npm ci; else npm install; fi
npm run build

php artisan config:clear >/dev/null 2>&1 || true

log "Install complete."
echo
echo "  URL:      http://localhost:8000"
echo "  Login:    fspbx@fspbx.com"
echo "  Password: Fspbx-Dev-1234!"
