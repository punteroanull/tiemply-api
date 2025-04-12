#!/bin/sh¡
# Esperar a que MariaDB esté listo
# echo "Waiting for database connection..."
# while ! mysqladmin ping -h"$DB_HOST" --silent; do
#   sleep 2
# done
echo "Database is ready. Starting application..."
composer install
php artisan serve -vvv &
chmod +x setup-githook.sh
sh setup-githook.sh
if [ ! -f .git/hooks/pre-commit ]
then
  cp .env.example .env
fi
tail -f /dev/null