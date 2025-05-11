#!/bin/sh¡
# Esperar a que MariaDB esté listo
# echo "Waiting for database connection..."
# while ! mysqladmin ping -h"$DB_HOST" --silent; do
#   sleep 2
# done
git config --global --add safe.directory /App

echo "Database is ready. Executing 'composer install'..."

composer install

#echo "Setting up Git hooks..."
#chmod +x setup-githook.sh
#sh setup-githook.sh

if [ ! -f .git/hooks/pre-commit ]
then
  cp .env.example .env
fi

echo "Generating migrations..."

php artisan migrate:refresh --seed

echo "Starting Laravel application..."

php artisan serve --host=0.0.0.0 --port=8000 

sleep infinity
