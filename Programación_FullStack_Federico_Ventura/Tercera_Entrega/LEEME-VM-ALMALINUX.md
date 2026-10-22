# PrimeCup / SGDM en una VM con AlmaLinux 10 (de cero)

Guía para dejar el sitio andando en una VM nueva, y entender qué hace cada paso.
Está probada con la versión 10.1 de AlmaLinux (trae PHP 8.3 y MariaDB en sus repositorios).
Todo lo de `bd/` ya fue probado con el usuario restringido `sgdm_app`, no solo con root.

Lo que vas a armar:

```
navegador ──► Apache (httpd) ──► PHP-FPM ──► MariaDB
   (HTTPS)       :443 (TLS)       el código      base "sgdm"
                                  de /var/www/sgdm
```

---

## 1. Crear la VM

| Recurso | Mínimo | Recomendado |
|---|---|---|
| Procesadores | 1 | 2 |
| RAM | 1,5 GB | 2 GB |
| Disco | 15 GB | 20 GB |
| Red | NAT con reenvío de puertos | **Adaptador puente (Bridge)** |

* Con **Bridge** la VM recibe una IP de tu red y entrás desde el navegador de tu compu con `http://IP-DE-LA-VM/`.
* Con **NAT** hay que reenviar puertos: el 8443 de tu compu al 443 de la VM (el sitio por HTTPS), el 8080 al 80 y el 2222 al 22 (SSH).
* Descargá la ISO **Boot** (si hay internet) o **DVD** de AlmaLinux 10.1, arquitectura **x86_64**.

## 2. Instalar AlmaLinux

En el instalador:

1. Idioma: el que prefieras. Teclado: *Spanish* (o *Latin American*).
2. **Time & Date**: Montevideo (América/Montevideo) y hora automática activada.
3. **Software Selection**: **Server** (sin entorno gráfico). Si usaste la ISO Minimal, solo aparece *Minimal Install* y está bien.
4. **Installation Destination**: el disco completo con partición automática.
5. **Network & Host Name**: activá la interfaz (el interruptor en *ON*) y poné de nombre de equipo `sgdm`.
6. **Root Password**: poné una contraseña (anotala).
7. **User Creation**: creá tu usuario y tildá **"Make this user administrator"** (queda en el grupo `wheel`, o sea puede usar `sudo`).
8. *Begin Installation*. Al terminar, reiniciá y sacá la ISO de la unidad virtual.

**Por qué:** "Server" sin gráficos gasta menos memoria y tiene menos superficie de ataque. Trabajar con un usuario común + `sudo` es más seguro que usar root todo el tiempo.

## 3. Primer arranque

Entrá con tu usuario (no root). Después:

```bash
ip a                      # anotá la IP (algo como 192.168.x.x)
sudo dnf update -y        # actualiza el sistema
sudo dnf install -y git nano tar policycoreutils-python-utils
sudo reboot               # solo hace falta si se actualizó el kernel
```

Desde acá podés trabajar por SSH desde tu compu (más cómodo para copiar y pegar):
`ssh TU_USUARIO@IP-DE-LA-VM`

> **Tip:** sacá un *snapshot* de la VM cuando termines esta sección y otro cuando todo ande. Si algo sale mal, volvés atrás en un minuto.

## 4. Instalar Apache, PHP y MariaDB

```bash
sudo dnf install -y httpd mod_ssl openssl mariadb-server php php-fpm php-mysqlnd php-mbstring
sudo systemctl enable --now httpd php-fpm mariadb
```

Comprobá que PHP trae lo que el proyecto necesita (tienen que aparecer las tres):

```bash
php -v                                         # PHP 8.3.x
php -m | grep -E "pdo_mysql|mbstring|fileinfo"
```

Configuración de PHP del proyecto (zona horaria, subida de fotos, cookies de sesión seguras):

```bash
sudo tee /etc/php.d/99-sgdm.ini > /dev/null <<'EOF'
date.timezone = America/Montevideo
expose_php = Off
display_errors = Off
log_errors = On
upload_max_filesize = 5M
post_max_size = 8M
session.cookie_httponly = 1
session.use_strict_mode = 1
session.cookie_samesite = Lax
session.cookie_secure = 1
EOF
```

`session.cookie_secure` hace que el navegador solo envíe la cookie de sesión por HTTPS. Por eso el sitio solo funciona entrando por `https://` (punto 9).

Asegurar MariaDB:

```bash
sudo mariadb-secure-installation
```

Respuestas recomendadas:

| Pregunta | Respuesta |
|---|---|
| Enter current password for root | Enter (no tiene) |
| Switch to unix_socket authentication | `n` (ya viene así) |
| Change the root password | `n` (root entra con `sudo mariadb`, sin contraseña de red) |
| Remove anonymous users | `Y` |
| Disallow root login remotely | `Y` |
| Remove test database | `Y` |
| Reload privilege tables | `Y` |

**Por qué:** los usuarios anónimos y la base `test` son puertas innecesarias. Con `unix_socket`, solo el root del sistema entra como root de la base.

## 5. Firewall y SELinux

```bash
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload

getenforce                                   # tiene que decir: Enforcing
sudo setsebool -P httpd_can_network_connect_db 1
```

El puerto 80 queda abierto solo para redirigir a HTTPS.

**No desactives SELinux.** Es una capa de seguridad: si algo falla, se arregla con el permiso correcto (ver el punto 10), no apagándolo.

## 6. Traer el proyecto

```bash
sudo mkdir -p /var/www/sgdm
sudo chown $USER:$USER /var/www/sgdm
git clone URL_DE_TU_REPOSITORIO /var/www/sgdm
cd /var/www/sgdm
```

La carpeta de fotos de perfil tiene que existir y poder escribirla Apache/PHP:

```bash
mkdir -p uploads/avatars
sudo chown -R apache:apache uploads
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/sgdm/uploads(/.*)?"
sudo restorecon -Rv /var/www/sgdm
```

**Por qué:** PHP corre como el usuario `apache`. El resto del código queda de solo lectura para él y solo `uploads/` es escribible. El `semanage` le dice a SELinux que esa carpeta (y solo esa) puede recibir archivos de Apache.

## 7. Crear la base de datos

Elegí las contraseñas **sin** los caracteres `/ & ' " \` (rompen el `sed`). No se guardan en ningún archivo del repositorio.

```bash
cd /var/www/sgdm/bd

# 1) Esquema y migraciones, EN ESTE ORDEN
for f in sgdm_schema sgdm_migracion_perfil_torneos sgdm_migracion_organizador_asignado \
         sgdm_migracion_auditoria sgdm_migracion_competencia sgdm_migracion_bye \
         sgdm_migracion_ganador sgdm_migracion_inscripcion sgdm_migracion_equipos \
         sgdm_migracion_fechas sgdm_migracion_correccion_vistas \
         sgdm_migracion_modulos_config sgdm_migracion_correccion_autorizada sgdm_migracion_intentos_login sgdm_migracion_validacion_resultados sgdm_migracion_editar_cancelar sgdm_migracion_premio_reglas_visibilidad; do
  echo ">> $f"
  sudo mariadb --default-character-set=utf8mb4 < $f.sql || break
done

# 2) Usuarios de la base (DCL): uno para la aplicación y otro para respaldos
read -rsp "Contraseña para sgdm_app: " PASS_APP; echo
read -rsp "Contraseña para sgdm_backup: " PASS_BAK; echo
sed -e "s/CAMBIAR_ESTA_CONTRASEÑA/$PASS_APP/" \
    -e "s/CAMBIAR_ESTA_OTRA_CONTRASEÑA/$PASS_BAK/" sgdm_dcl.sql \
  | sudo mariadb --default-character-set=utf8mb4

# 3) Datos de prueba y fechas al día de hoy (para la demo)
sudo mariadb --default-character-set=utf8mb4 < sgdm_datos_prueba.sql
sudo mariadb --default-character-set=utf8mb4 < sgdm_datos_prueba_actualizar_fechas.sql
```

Comprobá:

```bash
sudo mariadb sgdm -e "SELECT COUNT(*) AS usuarios FROM usuarios; SELECT * FROM v_resumen_admin\G"
sudo mariadb -e "SHOW GRANTS FOR 'sgdm_app'@'localhost'"
```

**Por qué dos usuarios:** `sgdm_app` solo puede leer y escribir datos (SELECT, INSERT, UPDATE, DELETE): si alguien lograra una inyección SQL, no podría borrar tablas. `sgdm_backup` solo puede leer, para los respaldos. Se llama *principio de menor privilegio*. Las vistas (`v_resumen_admin`, etc.) se crearon con root y la aplicación las consulta con un simple SELECT.

## 8. Conectar la aplicación a la base (sin tocar archivos del repositorio)

`config/database.php` lee las credenciales de variables de entorno. Se las damos a PHP-FPM, así la contraseña real **no queda en Git** y un `git pull` nunca da conflicto:

```bash
sudo tee -a /etc/php-fpm.d/www.conf > /dev/null <<EOF

; --- PrimeCup / SGDM: credenciales de la base ---
env[SGDM_DB_HOST] = localhost
env[SGDM_DB_NAME] = sgdm
env[SGDM_DB_USER] = sgdm_app
env[SGDM_DB_PASS] = "$PASS_APP"
EOF
sudo chmod 640 /etc/php-fpm.d/www.conf
sudo systemctl restart php-fpm
```

(Si cerraste la terminal y se perdió `$PASS_APP`, escribí la contraseña a mano en lugar de la variable.)

## 9. Apache y HTTPS

El sitio se sirve **solo por HTTPS**: el tráfico viaja cifrado con TLS y la cookie de sesión nunca viaja sin cifrar. El puerto 80 no muestra nada: redirige a la versión segura. Como la VM no tiene un nombre público en internet, el certificado es **autofirmado**: cifra igual que uno comprado, pero ninguna autoridad lo garantiza, y por eso el navegador avisa (ver más abajo).

Generá el certificado, válido por un año. En `subjectAltName` van los nombres con los que vas a entrar al sitio; si entrás por la IP de la VM, agregala (por ejemplo `,IP:192.168.1.50`):

```bash
sudo openssl req -x509 -nodes -newkey rsa:2048 -sha256 -days 365 \
  -keyout /etc/pki/tls/private/sgdm.key -out /etc/pki/tls/certs/sgdm.crt \
  -subj "/C=UY/O=PrimeCup/CN=sgdm.local" \
  -addext "subjectAltName=DNS:sgdm.local,DNS:localhost,IP:127.0.0.1"
sudo chmod 600 /etc/pki/tls/private/sgdm.key
sudo restorecon -v /etc/pki/tls/private/sgdm.key /etc/pki/tls/certs/sgdm.crt
```

Instalá la configuración de Apache (el archivo se copia con el prefijo `00-` para que cargue antes que el `ssl.conf` que trae `mod_ssl` y su sitio sea el predeterminado del puerto 443):

```bash
sudo cp /var/www/sgdm/apache/sgdm.conf /etc/httpd/conf.d/00-sgdm.conf
sudo cp /var/www/sgdm/apache/sgdm-comun.inc /etc/httpd/conf.d/sgdm-comun.inc
sudo httpd -t                      # tiene que decir: Syntax OK
sudo systemctl restart httpd php-fpm
```

Probá desde la propia VM (`-k` le dice a curl que acepte el certificado autofirmado):

```bash
curl -I http://localhost/          # 301, con Location: https://...
curl -kI https://localhost/        # 200 OK, con la cabecera Strict-Transport-Security
```

Para entrar desde el navegador de tu compu:

* **Con Bridge:** `https://IP-DE-LA-VM/`. Opcional, para usar `https://sgdm.local/`: agregá `IP-DE-LA-VM  sgdm.local` al archivo `hosts` de tu compu (Windows: `C:\Windows\System32\drivers\etc\hosts`, abierto como administrador).
* **Con NAT:** entrá directo a `https://localhost:8443/` (la redirección desde el puerto 80 no conoce el puerto reenviado).

**El aviso del navegador** ("La conexión no es privada", `NET::ERR_CERT_AUTHORITY_INVALID`) es esperable con un certificado autofirmado: tocá *Avanzado* y *Continuar*. Si querés que desaparezca en tu compu, copiá el certificado con `scp` e importalo como autoridad de confianza (en Firefox: Configuración → Privacidad → Certificados → Ver certificados → Autoridades → Importar). En un servidor real con nombre público se usaría un certificado de una autoridad como Let's Encrypt.

La configuración también agrega cabeceras de seguridad (HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy` y una política de contenido `Content-Security-Policy` que solo deja ejecutar scripts del propio sitio), oculta la versión de Apache (`ServerTokens Prod`, `ServerSignature Off`), desactiva `TRACE` y solo admite TLS 1.2 y 1.3. La política de contenido no se aplica a `reglas.php` (el PDF de reglas de un torneo). Para verificarlo: `curl -kI https://localhost/ | grep -i -E "content-security|permissions|cross-origin|server:"`. El HSTS se dejó con una vigencia de 5 minutos a propósito: con un certificado autofirmado, un valor largo podría dejarte sin acceso al sitio si lo cambiás.

El VirtualHost hace además cuatro cosas de seguridad: sin listado de carpetas (`-Indexes`), bloquea el acceso web a `app/`, `config/`, `bd/` y archivos ocultos (`.git`), y nunca ejecuta PHP dentro de `uploads/`.

> Docker sirve el sitio por HTTP a propósito: corre solo en tu equipo, sin tráfico de red real.

## 10. Probar que anda todo

Entrá con `test_admin` / `Prueba123!` (siempre por `https://`) y revisá:

- [ ] La portada muestra torneos reales.
- [ ] Panel de administración: números, usuarios, torneos, auditoría.
- [ ] Un organizador (`test_organizador1`) carga y corrige un resultado.
- [ ] Un participante (`test_valentina01`) se inscribe a un torneo abierto.
- [ ] Subir una foto de perfil (prueba los permisos de `uploads/`).
- [ ] Cerrar sesión y volver a entrar.

**Si algo falla**, mirá los registros en este orden:

```bash
sudo tail -n 30 /var/log/httpd/sgdm_error.log
sudo tail -n 30 /var/log/php-fpm/www-error.log
sudo ausearch -m avc -ts recent          # denegaciones de SELinux
```

| Síntoma | Causa probable | Solución |
|---|---|---|
| El navegador descarga el `.php` o muestra código | PHP-FPM no está andando | `sudo systemctl enable --now php-fpm && sudo systemctl restart httpd` |
| "No se pudo conectar con la base de datos" | Contraseña mal puesta o MariaDB apagada | Revisá `env[SGDM_DB_PASS]`, `sudo systemctl status mariadb`, reiniciá php-fpm |
| 403 en todo el sitio | Permisos o etiqueta de SELinux | `sudo restorecon -Rv /var/www/sgdm` |
| La foto de perfil no se guarda | `uploads/` sin permisos | Repetí los comandos del punto 6 |
| No abre desde mi compu pero sí con `curl` en la VM | Firewall o red | Punto 5 (puertos 80 y 443); revisá la IP con `ip a` y que el adaptador sea Bridge, o el reenvío de puertos si es NAT |
| El navegador dice "La conexión no es privada" | Certificado autofirmado (es lo esperado) | *Avanzado → Continuar*, o importar el certificado (punto 9) |
| Inicio sesión y vuelve a la pantalla de login | Entraste por `http://`: la cookie de sesión es solo HTTPS | Entrá por `https://` |
| `httpd -t` falla con `SSLCertificateFile ... does not exist` | No se generó el certificado | Repetí el comando `openssl` del punto 9 |
| Cambié CSS/JS y no se nota | Caché del navegador | Subí el número `?v=` de `styles.css` y `script.js`, o Ctrl+F5 |

## 11. Después de cada `git pull`

```bash
cd /var/www/sgdm
git pull
sudo restorecon -R /var/www/sgdm      # si se agregaron archivos nuevos
sudo systemctl reload php-fpm          # limpia la caché de PHP
```

Si el `pull` trae una migración nueva en `bd/`, se corre **solo esa**, una vez:
`sudo mariadb --default-character-set=utf8mb4 < bd/NOMBRE.sql`

## 12. Respaldos

```bash
# Respaldar (con el usuario de solo lectura)
mariadb-dump -u sgdm_backup -p --single-transaction sgdm > ~/sgdm_$(date +%F).sql

# Restaurar en una base nueva para comprobar que el respaldo sirve
sudo mariadb -e "CREATE DATABASE sgdm_prueba CHARACTER SET utf8mb4"
sudo mariadb sgdm_prueba < ~/sgdm_AAAA-MM-DD.sql
```

Un respaldo que nunca se probó restaurando no es un respaldo.

---

## Para explicar en la defensa

* **Apache + PHP-FPM:** Apache sirve los archivos y le pasa los `.php` a PHP-FPM, un servicio aparte. Es la forma estándar en AlmaLinux.
* **Dos usuarios de base y sin permisos de estructura:** menor privilegio (punto 7).
* **HTTPS con TLS 1.2 y 1.3:** el tráfico viaja cifrado; el certificado es autofirmado por ser una VM sin nombre público, y se explica la diferencia con uno de una autoridad. Además, HSTS, la cookie de sesión marcada como Secure y las cabeceras de seguridad.
* **SELinux en *Enforcing* y firewall solo con HTTP y HTTPS:** solo queda abierto lo necesario.
* **Credenciales fuera del repositorio:** viven en la configuración de PHP-FPM, no en Git.
* **Consultas SQL preparadas (PDO):** los datos del usuario nunca se pegan dentro de la consulta, y por eso no hay inyección SQL.
* **Token CSRF en cada formulario:** impide que otro sitio mande formularios en nombre de un usuario con sesión iniciada.
* **Transacciones:** cargar o corregir un resultado actualiza el partido, la tabla y el campeón en un solo bloque que se guarda completo o no se guarda.
* **Vistas SQL:** el panel de administración consulta resúmenes ya armados en la base.
* **Auditoría:** cada acción importante (carga y corrección de resultados, suspensiones, fixtures) queda registrada con quién la hizo.
* **Docker:** la misma aplicación corre con un solo comando (`docker compose up`); ver `LEEME-DOCKER.md`.
