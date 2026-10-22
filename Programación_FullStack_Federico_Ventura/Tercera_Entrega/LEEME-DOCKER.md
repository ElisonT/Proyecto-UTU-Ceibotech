# PrimeCup (SGDM) con Docker

Levanta la aplicación (PHP 8.3 + Apache) y la base de datos (MariaDB 11.4) con un solo comando.
La primera vez, MariaDB crea la base, corre el esquema y todas las migraciones **en orden**, deja al
usuario de la aplicación solo con permisos de lectura y escritura de datos (igual que `bd/sgdm_dcl.sql`)
y carga los datos de prueba.

## Levantarlo

```bash
docker compose up -d --build
```

Abrir <http://localhost:8080>. La primera vez tarda un poco más porque carga la base
(`docker compose logs -f db` muestra el avance; al final dice `[sgdm] Base de datos lista`).

## Cuentas de prueba

La contraseña de todas es `Prueba123!`

| Usuario | Rol |
|---|---|
| `test_admin` | Administrador general |
| `test_organizador1` … `test_organizador5` | Organizadores |
| `test_valentina01`, `test_santiago02`… hasta el 100 | Participantes |

## Comandos útiles

| Qué | Comando |
|---|---|
| Ver los logs del sitio | `docker compose logs -f web` |
| Apagar todo (se conservan los datos) | `docker compose down` |
| Borrar TODO y empezar de cero (base y fotos) | `docker compose down -v` |
| Reconstruir después de cambiar el código | `docker compose up -d --build` |
| Entrar a la base | `docker compose exec db mariadb -usgdm_app -p sgdm` |

## Cambiar claves, puerto o los datos de prueba

Copiar `.env.example` como `.env` y editarlo:

* `WEB_PORT`: puerto del sitio (en un servidor, `80`).
* `SGDM_DB_PASS` y `SGDM_DB_ROOT_PASS`: claves de la base. **Solo se aplican la primera vez** que se crea
  la base; si ya existe, hay que hacer `docker compose down -v` o cambiarlas desde MariaDB.
* `CARGAR_DATOS_PRUEBA=0`: arranca con la base vacía (solo los 3 roles).

Sin `.env` se usan claves de desarrollo (`sgdm_dev_pass`): **no usarlas en un servidor real**.

## En el servidor (AlmaLinux)

```bash
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
```

Después, `docker compose up -d --build` dentro de la carpeta del proyecto. Si el Apache del servidor ya
usa el puerto 80, dejar `WEB_PORT=8080` o apagar `httpd` antes. Los volúmenes llevan `:z` para que funcione
con SELinux activado.

## Cómo está armado

* `Dockerfile`: imagen del sitio. Apache no sirve `app/`, `config/` ni `bd/`, y en `uploads/` nunca se ejecuta PHP.
* `docker-compose.yml`: los dos servicios, sus variables y los volúmenes (`db_data` para la base y `uploads`
  para las fotos de perfil).
* `docker/db/init.sh`: el orden de los scripts de `bd/`. Si se agrega una migración nueva, hay que sumarla ahí.
* `config/database.php`: lee `SGDM_DB_HOST`, `SGDM_DB_NAME`, `SGDM_DB_USER` y `SGDM_DB_PASS`. Si no están
  definidas usa los valores de siempre, así que en la VM actual no cambia nada.
* Regenerar los datos de prueba (opcional): `python3 bd/generar_datos_prueba.py`.
* Fechas de los datos de prueba: el seed se generó "como si hoy fuera el 7/oct/2026". Después de cargarlo hay que correr
  `bd/sgdm_datos_prueba_actualizar_fechas.sql` (el `init.sh` ya lo hace): corre todas las fechas hasta el día de hoy para
  que las inscripciones abiertas sigan abiertas. Se puede volver a correr cuando quieras (si ya está al día no mueve nada).
