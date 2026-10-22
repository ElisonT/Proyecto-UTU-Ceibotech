# =========================================================================
# Dockerfile - Imagen para la aplicacion web SGDM / PrimeCup
# Sirve el frontend (HTML/CSS/JS del equipo) con Apache + PHP, igual que
# en la VM de AlmaLinux pero empaquetado en un contenedor.
# =========================================================================

FROM php:8.2-apache

# Habilitar la extension mysqli para que PHP se conecte a MySQL
RUN docker-php-ext-install mysqli

# Habilitar mod_rewrite por si la app lo necesita
RUN a2enmod rewrite

# Copiar el codigo de la aplicacion web al directorio publico de Apache
COPY ./app /var/www/html/

# Permisos correctos para Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
