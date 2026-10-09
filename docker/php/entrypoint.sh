#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set; generating a temporary one. Set APP_KEY for any real deployment." >&2
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction
fi

# Compile configuration and routes once at boot instead of on every request.
php artisan config:cache
php artisan route:cache

exec "$@"
