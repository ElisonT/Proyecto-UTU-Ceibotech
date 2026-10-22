# =========================================================================
# Dockerfile - Aplicacion web SGDM / PrimeCup (PHP + Apache)
# La app (Segunda_Entrega) usa PDO para conectarse a MySQL/MariaDB.
# =========================================================================
FROM php:8.2-apache

# PDO + driver MySQL (la app usa PDO) y mysqli por si hiciera falta
RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

# Codigo de la app (carpeta ./app junto a este Dockerfile)
COPY ./app/ /var/www/html/

# La app guarda fotos de perfil en uploads/avatars: debe poder escribirlo Apache
RUN mkdir -p /var/www/html/uploads/avatars \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 775 /var/www/html/uploads

EXPOSE 80
CMD ["apache2-foreground"]
