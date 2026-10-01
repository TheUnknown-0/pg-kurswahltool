FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl pcntl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot auf public/ setzen (Front-Controller-Pattern)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html/
COPY config/php.ini /usr/local/etc/php/conf.d/kurswahl.ini

# Sitzungen in einem eigenen Verzeichnis statt im flüchtigen /tmp
RUN mkdir -p /var/lib/php/sessions /var/www/html/uploads/queue /var/www/html/import \
    && chown www-data:www-data /var/lib/php/sessions /var/www/html/uploads /var/www/html/uploads/queue /var/www/html/import \
    && chmod 700 /var/lib/php/sessions

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
