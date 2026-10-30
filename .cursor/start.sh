#!/usr/bin/env bash
#
# Per-boot startup for the FS PBX Cloud Agent development environment.
# Starts the PostgreSQL and Redis services the web application depends on.
# The application server itself runs from the `terminals` entries in
# environment.json (php artisan serve / queue worker).
set -euo pipefail

log() { echo -e "\e[36m[start]\e[0m $*"; }

PG_VERSION="$(ls /etc/postgresql 2>/dev/null | sort -n | tail -1 || echo 16)"
log "Starting PostgreSQL ${PG_VERSION}..."
sudo pg_ctlcluster "$PG_VERSION" main start 2>/dev/null || true
for i in $(seq 1 20); do
  sudo -u postgres psql -tc "SELECT 1;" >/dev/null 2>&1 && break
  sleep 1
done

log "Starting Redis..."
redis-cli ping >/dev/null 2>&1 || sudo redis-server --daemonize yes

log "Services ready."
