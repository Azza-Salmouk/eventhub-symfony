#!/bin/sh
set -e

APP_DIR=/var/www/html
CACHE_DIR="${APP_DIR}/var/cache"

echo "[entrypoint] Starting permission and cache setup..."

# ── 1. Nuclear cache wipe (runs as root — nothing can block us) ───────────────
if [ -d "${CACHE_DIR}" ]; then
    mv "${CACHE_DIR}" "${CACHE_DIR}_old_$$" 2>/dev/null || true
    rm -rf "${CACHE_DIR}_old_$$" 2>/dev/null || true
    rm -rf "${CACHE_DIR}" 2>/dev/null || true
fi

# ── 2. Recreate required directories ─────────────────────────────────────────
mkdir -p \
    "${CACHE_DIR}/dev" \
    "${CACHE_DIR}/prod" \
    "${APP_DIR}/var/log" \
    "${APP_DIR}/var/sessions" \
    "${APP_DIR}/public/uploads/events"

# ── 3. Fix ownership ──────────────────────────────────────────────────────────
chown -R www-data:www-data \
    "${APP_DIR}/var" \
    "${APP_DIR}/public/uploads"

chmod -R 775 "${APP_DIR}/var"
chmod -R 775 "${APP_DIR}/public/uploads"

# ── 4. Fix JWT key permissions ────────────────────────────────────────────────
if [ -f "${APP_DIR}/config/jwt/private.pem" ]; then
    chown www-data:www-data \
        "${APP_DIR}/config/jwt/private.pem" \
        "${APP_DIR}/config/jwt/public.pem" 2>/dev/null || true
    chmod 640 "${APP_DIR}/config/jwt/private.pem"
    chmod 644 "${APP_DIR}/config/jwt/public.pem"
fi

# ── 5. Warm up Symfony cache as www-data ─────────────────────────────────────
echo "[entrypoint] Warming up Symfony cache..."
su-exec www-data php "${APP_DIR}/bin/console" cache:warmup \
    --env="${APP_ENV:-dev}" --no-debug 2>&1 || {
    echo "[entrypoint] WARNING: cache:warmup failed — will warm on first request"
}

echo "[entrypoint] Done. Starting PHP-FPM (master as root, workers as www-data)..."

# ── 6. Start PHP-FPM as root ──────────────────────────────────────────────────
# PHP-FPM master process runs as root (needed to bind port 9000 and manage workers).
# Worker processes drop to www-data automatically via pool config (user = www-data).
# This is the standard PHP-FPM deployment model.
exec "$@"
