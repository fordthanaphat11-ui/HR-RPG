FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers expires \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/hr-rpg.conf \
    && a2enconf hr-rpg

COPY docker/php-production.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/start-apache.sh /usr/local/bin/start-apache
RUN chmod +x /usr/local/bin/start-apache

WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

CMD ["start-apache"]
