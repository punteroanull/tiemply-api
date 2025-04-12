# filepath: d:\tiemply-api\Dockerfile
FROM php:8.3-cli

RUN apt-get -y update && apt-get -y upgrade && \
    apt-get -y install git zip unzip libzip-dev curl default-mysql-client && \
    curl -fsSL https://deb.nodesource.com/setup_23.x -o nodesource_setup.sh && \
    bash nodesource_setup.sh && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/* nodesource_setup.sh

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql mbstring exif pcntl bcmath gd zip
RUN git config --global --add safe.directory /app

RUN groupadd -g 1000 app && \
    useradd -m -u 1000 -g app -s /bin/bash app

COPY entrypoint.sh /usr/local/bin/
COPY setup-githook.sh /app/
RUN chmod +x /usr/local/bin/entrypoint.sh
RUN chmod +x /app/setup-githook.sh
# RUN chmod +x entrypoint.sh

RUN mkdir -p /app && chown -R app:app /app

USER app

ENTRYPOINT [ "sh", "/usr/local/bin/entrypoint.sh" ]