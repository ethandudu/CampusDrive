FROM php:8.5-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ajout des librairies pour l'extension GD (nécessaire pour les Captchas)
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd

RUN a2enmod rewrite

RUN echo "file_uploads = On\n" \
         "memory_limit = 256M\n" \
         "upload_max_filesize = 10M\n" \
         "post_max_size = 10M\n" \
         "max_execution_time = 300\n" \
         > /usr/local/etc/php/conf.d/uploads.ini

RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]