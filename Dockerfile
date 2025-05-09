# filepath: d:\tiemply-api\Dockerfile
FROM php:8.3-cli

RUN apt-get -y update && apt-get -y upgrade && \
    apt-get -y install git zip unzip libzip-dev curl default-mysql-client && \
    curl -fsSL https://deb.nodesource.com/setup_23.x -o nodesource_setup.sh && \
    bash nodesource_setup.sh && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/* nodesource_setup.sh

WORKDIR /App

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql mbstring exif pcntl bcmath gd zip sodium intl
RUN git config --global --add safe.directory /App

RUN groupadd -g 1000 app && \
    useradd -m -u 1000 -g app -s /bin/bash app

COPY entrypoint.sh /usr/local/bin/
COPY setup-githook.sh /app/
RUN chmod +x /usr/local/bin/entrypoint.sh
# RUN chmod +x /App/setup-githook.sh
# RUN chmod +x entrypoint.sh

RUN mkdir -p /App && chown -R app:app /App

USER app

# Expose port 8000 and start php-fpm server
EXPOSE 8000
CMD ["php-fpm"]

ENTRYPOINT [ "sh", "/usr/local/bin/entrypoint.sh" ]