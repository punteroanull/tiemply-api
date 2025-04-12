#!/bin/sh¡
# Esperar a que MariaDB esté listo
# echo "Waiting for database connection..."
# while ! mysqladmin ping -h"$DB_HOST" --silent; do
#   sleep 2
# done
echo "Database is ready. Executing 'composer install'..."
composer install
echo "Starting Laravel application..."
php artisan serve --host=0.0.0.0 --port=8000 
echo "Setting up Git hooks..."
chmod +x setup-githook.sh
sh setup-githook.sh
if [ ! -f .git/hooks/pre-commit ]
then
  cp .env.example .env
fi
sleep infinity