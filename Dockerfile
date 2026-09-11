FROM php:8.2-apache

COPY docker/apache.conf /etc/apache2/conf-available/ganaderia.conf

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && a2enconf ganaderia

COPY . /var/www/html/

RUN cp /var/www/html/config.sample.php /var/www/html/config.php \
    && chown -R www-data:www-data /var/www/html

ENV GANADERIA_ENV=production

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/login.php")===false?1:0);'
