FROM php:8.3-apache

RUN set -eux; \
    docker-php-ext-install mysqli pdo_mysql; \
    a2dismod -f mpm_event mpm_worker || true; \
    a2enmod mpm_prefork rewrite headers expires; \
    printf '%s\n' \
        'ServerName localhost' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/hr-rpg.conf; \
    a2enconf hr-rpg; \
    apache2ctl configtest

COPY docker/php-production.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/start-apache.sh /usr/local/bin/start-apache
RUN chmod +x /usr/local/bin/start-apache

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

CMD ["start-apache"]
