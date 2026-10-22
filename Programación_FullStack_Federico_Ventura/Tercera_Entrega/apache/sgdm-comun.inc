# ------------------------------------------------------------
# apache/sgdm-comun.inc: reglas del sitio que comparten los VirtualHost.
# Se copia a /etc/httpd/conf.d/ y lo incluye sgdm.conf. Lleva extensión .inc
# (y no .conf) para que Apache no lo cargue por su cuenta, solo mediante Include.
# ------------------------------------------------------------
DocumentRoot /var/www/sgdm

<Directory /var/www/sgdm>
    # Sin Indexes: si falta un index, Apache no lista la carpeta.
    Options -Indexes +FollowSymLinks
    # El proyecto no usa .htaccess, así que no se permite que ninguno cambie la configuración.
    AllowOverride None
    Require all granted
    DirectoryIndex index.php index.html
</Directory>

# El código, la configuración y los scripts de la base no se sirven por web.
<DirectoryMatch "^/var/www/sgdm/(app|config|bd|docker|apache)(/|$)">
    Require all denied
</DirectoryMatch>

# Las fotos que suben los usuarios nunca se ejecutan como PHP.
<Directory /var/www/sgdm/uploads>
    <FilesMatch "\.(php|phtml|phar)$">
        Require all denied
    </FilesMatch>
</Directory>

# Archivos ocultos (.git, .env, .htaccess...).
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
