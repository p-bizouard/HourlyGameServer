#!/bin/sh

cd /app

if [[ $@ == yarn* ]]; then
  exec "$@"
else
  # If dependencies are missing, install them
  
  if [[ -f /app/vendor/autoload.php ]]; then
    composer install --no-interaction --optimize-autoloader
  fi

  php bin/console doctrine:migrations:migrate -n

  echo Exec $@
  exec "$@"
fi
