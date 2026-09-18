#!/bin/sh
set -e

if [ -f /var/www/html/composer.json ]; then
    composer install --no-interaction --no-progress --optimize-autoloader
fi

exec "$@"
