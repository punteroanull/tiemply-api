composer install

php artisan serve -vvv &

# sh setup-githook.sh

if [ ! -f .git/hooks/pre-commit ]
then
  cp .env.example .env
fi


# tail -f /dev/null