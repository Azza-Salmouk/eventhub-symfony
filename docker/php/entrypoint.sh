#!/bin/sh
set -e

# ── Fix var/ permissions ──────────────────────────────────────────────────────
# The host bind-mount (.:/var/www/html) can overwrite permissions set at build
# time. Re-apply on every container start so cache:clear never fails.
mkdir -p /var/www/html/var/cache \
         /var/www/html/var/log \
         /var/www/html/var/sessions \
         /var/www/html/public/uploads/events

chown -R www-data:www-data \
    /var/www/html/var \
    /var/www/html/public/uploads

chmod -R 775 /var/www/html/var

# ── Fix JWT key permissions ───────────────────────────────────────────────────
# Ensure PHP-FPM (www-data) can read the private key for JWT signing.
if [ -f /var/www/html/config/jwt/private.pem ]; then
    chown www-data:www-data /var/www/html/config/jwt/private.pem \
                            /var/www/html/config/jwt/public.pem 2>/dev/null || true
    chmod 640 /var/www/html/config/jwt/private.pem
    chmod 644 /var/www/html/config/jwt/public.pem
fi

exec "$@"
