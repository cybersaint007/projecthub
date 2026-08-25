#!/bin/sh
set -e

# The image bakes a config cache at build time (`php artisan optimize`), and a
# cached config makes Laravel ignore .env entirely at runtime. Since .env is
# bind-mounted by docker-compose, rebuild the cache on every start so that
# credential changes take effect with a restart instead of an image rebuild.
if [ -f /var/www/html/.env ]; then
    php artisan config:cache
else
    echo "entrypoint: /var/www/html/.env not found, keeping the baked config cache" >&2
fi

exec "$@"
