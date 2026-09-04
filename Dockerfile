FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev pkg-config \
    && docker-php-ext-install curl \
    && apt-get purge -y --auto-remove pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true; \
    a2enmod mpm_prefork

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && apache2ctl -t

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
