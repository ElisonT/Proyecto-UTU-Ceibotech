# Políticas de Seguridad del Servidor
## Sistema de Gestión de Torneos — SGDM / PrimeCup

---

## 1. Objetivo

Definir las medidas de seguridad aplicadas al servidor AlmaLinux que aloja
la base de datos y la aplicación web del sistema SGDM/PrimeCup, de forma
de proteger la confidencialidad, integridad y disponibilidad de la
información frente a accesos no autorizados, software malicioso y
pérdida de datos.

---

## 2. Control de acceso

- El acceso remoto al servidor se realiza exclusivamente por **SSH**
  (puerto 22), con límite de 4 intentos de conexión por minuto por IP
  para mitigar ataques de fuerza bruta.
- No se permite el acceso directo a la base de datos MySQL desde el
  exterior: el puerto 3306 permanece **cerrado** en el firewall. Toda
  administración remota de la base de datos debe hacerse mediante un
  túnel SSH.
- Cada usuario del sistema (administrador, organizador, participante,
  público) tiene permisos diferenciados sobre la base de datos, definidos
  en el script de DCL (`DCL_PrimeCup.sql`), siguiendo el principio de
  **mínimo privilegio**: cada rol solo puede hacer lo estrictamente
  necesario para su función.
- Las contraseñas de servicios (MySQL root, usuarios de aplicación) no se
  versionan en texto plano en el repositorio: se gestionan mediante un
  archivo `.env` local, excluido del control de versiones (`.gitignore`).

## 3. Firewall

- Se utiliza **firewalld** como interfaz de gestión del firewall del
  sistema (`configure_firewall.sh`).
- Política por defecto: **denegar todo**, permitir solo lo explícitamente
  necesario.
- Servicios habilitados: SSH (22, limitado), HTTP (80), HTTPS (443).
- Todo el resto del tráfico entrante, incluyendo el puerto de la base de
  datos, queda bloqueado.
- Se bloquean los pings ICMP (`echo-request`) para dificultar el
  reconocimiento externo del servidor.

## 4. Antivirus

- Se utiliza **ClamAV**, motor antivirus estándar en entornos Linux/servidor.
- Las firmas de virus se actualizan automáticamente mediante `freshclam`.
- Se ejecuta un escaneo automático diario (3:00 AM) sobre los directorios
  críticos del servidor (`/var/www`, `/home`, `/tmp`), con eliminación
  automática de archivos infectados y registro en log
  (`setup_antivirus.sh` / `scan_antivirus.sh`).

## 5. Respaldo y recuperación

- Se realiza un **respaldo diario automático** de todas las bases de
  datos MySQL mediante `mysqldump`, programado por cron a las 2:00 AM
  (`backup_mysql.sh` + `setup_cron.sh`).
- Los respaldos se almacenan con marca de fecha y hora
  (`/var/backups/mysql/backup_<fecha>.sql`), permitiendo restaurar el
  sistema ante pérdida de datos, fallos de hardware o incidentes de
  seguridad.

## 6. Monitoreo

- Se ejecuta un script de monitoreo (`db_monitor.sh`) que verifica
  periódicamente: estado del servicio MySQL (y lo reinicia si se
  detiene), conexiones activas, tamaño de las bases de datos, consultas
  lentas y espacio en disco disponible, generando alertas cuando el uso
  de disco supera el 85%.
- Todas las acciones administrativas sobre el servidor quedan
  centralizadas y auditables a través del script general de gestión
  (`server_manage.sh`).
- A nivel de base de datos, las tablas críticas (`torneo`, `resultado`)
  cuentan con **triggers de auditoría** que registran automáticamente
  quién hizo qué cambio y cuándo (trazabilidad), implementados en el DDL.

## 7. Actualizaciones del sistema

- El sistema operativo y sus paquetes (incluyendo MySQL, Apache, PHP,
  firewalld y ClamAV) deben mantenerse actualizados regularmente mediante
  `dnf update`, para incorporar parches de seguridad.
- Se recomienda revisar las actualizaciones disponibles al menos una vez
  por semana.

## 8. Contenerización de la aplicación

- La aplicación web puede desplegarse de forma aislada mediante
  **Docker** (`Dockerfile` + `docker-compose.yml`), separando el
  contenedor de la aplicación (Apache + PHP) del contenedor de la base
  de datos, comunicados por una red interna privada.
- El contenedor de la base de datos **no expone ningún puerto hacia el
  exterior**: solo es accesible desde el contenedor de la aplicación web,
  reduciendo la superficie de ataque.
- Las credenciales de la base de datos se inyectan por variables de
  entorno (`.env`), nunca hardcodeadas en las imágenes.

## 9. Responsabilidades

| Rol | Responsabilidad de seguridad |
|---|---|
| Administrador del sistema | Mantener firewall, antivirus, respaldos y actualizaciones al día |
| Administrador de la BD | Gestionar permisos de usuarios (DCL) y revisar logs de auditoría |
| Desarrolladores | No commitear credenciales ni datos sensibles al repositorio |
| Organizadores/Usuarios | Usar contraseñas propias, no compartir sus credenciales de acceso |

---

*Documento elaborado para la Tercera Entrega — Administración de Sistemas
Operativos. Complementa los scripts: `configure_firewall.sh`,
`setup_antivirus.sh`, `db_monitor.sh`, `server_manage.sh`,
`backup_mysql.sh`, `setup_cron.sh`, `Dockerfile`, `docker-compose.yml`.*
