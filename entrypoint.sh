#!/bin/sh
# Comandos de inicialización
echo "Starting application..."
composer install
php artisan serve -vvv &
chmod +x setup-githook.sh
sh setup-githook.sh
if [ ! -f .git/hooks/pre-commit ]
then
  cp .env.example .env
fi
tail -f /dev/null