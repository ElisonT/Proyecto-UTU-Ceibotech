# PrimeCup / SGDM - imagen de la aplicación (PHP + Apache).
# La base de datos va en otro contenedor: ver docker-compose.yml.
FROM php:8.3-apache

# Extensión para hablar con MariaDB/MySQL. mbstring y fileinfo ya vienen incluidas en la imagen.
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && a2dissite 000-default

COPY docker/apache.conf /etc/apache2/sites-available/sgdm.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/sgdm.ini
RUN a2ensite sgdm

WORKDIR /var/www/html
COPY . /var/www/html

# Lo que no tiene que quedar dentro de la imagen del sitio, y la carpeta de fotos de perfil
# con permisos para que Apache (www-data) pueda guardar archivos.
RUN rm -rf docker apache Dockerfile docker-compose.yml .dockerignore .gitattributes LEEME-DOCKER.md .env.example \
 && mkdir -p uploads/avatars \
 && chown -R www-data:www-data uploads

EXPOSE 80
