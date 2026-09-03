<?php
/**
 * app/Models/Usuario.php
 *
 * Modelo (capa de datos) de la entidad Usuario.
 * Todas las consultas usan parámetros preparados (:nombre_param)
 * en vez de concatenar variables directamente en el SQL, para
 * evitar inyección SQL.
 */

require_once __DIR__ . '/../../config/database.php';

class Usuario
{
    private PDO $conexion;

    // Rol por defecto para quien se autoregistra desde register.php
    // (ver bd/sgdm_schema.sql: 3 = Participante).
    public const ROL_PARTICIPANTE = 3;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    public function nombreUsuarioExiste(string $nombreUsuario): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT id FROM usuarios WHERE nombre_usuario = :nombre_usuario'
        );
        $consulta->execute(['nombre_usuario' => $nombreUsuario]);
        return (bool) $consulta->fetch();
    }

    public function emailExiste(string $email): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT id FROM usuarios WHERE email = :email'
        );
        $consulta->execute(['email' => $email]);
        return (bool) $consulta->fetch();
    }

    public function registrar(array $datos): bool
    {
        $hash = password_hash($datos['contrasena'], PASSWORD_DEFAULT);

        $consulta = $this->conexion->prepare(
            'INSERT INTO usuarios
                (nombre_usuario, nombre_completo, email, celular, contrasena_hash, genero, rol_id)
             VALUES
                (:nombre_usuario, :nombre_completo, :email, :celular, :hash, :genero, :rol_id)'
        );

        return $consulta->execute([
            'nombre_usuario'  => $datos['nombre_usuario'],
            'nombre_completo' => $datos['nombre_completo'],
            'email'           => $datos['email'],
            'celular'         => $datos['celular'] ?: null,
            'hash'            => $hash,
            'genero'          => $datos['genero'],
            'rol_id'          => self::ROL_PARTICIPANTE,
        ]);
    }

    /**
     * El login acepta nombre de usuario O email en el mismo campo
     * (ver login.php: placeholder "Tu usuario o correo").
     */
    public function buscarPorLogin(string $login): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM usuarios WHERE nombre_usuario = :login OR email = :login'
        );
        $consulta->execute(['login' => $login]);
        return $consulta->fetch();
    }

    public function buscarPorId(int $id): array|false
    {
        $consulta = $this->conexion->prepare('SELECT * FROM usuarios WHERE id = :id');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch();
    }

    /** Para el desplegable de "asignar organizador" en el panel admin. */
    public function listarPorRol(int $rolId): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT id, nombre_completo, nombre_usuario FROM usuarios WHERE rol_id = :rol AND activo = 1 ORDER BY nombre_completo'
        );
        $consulta->execute(['rol' => $rolId]);
        return $consulta->fetchAll();
    }

    /** Para editar perfil: hay que ignorar el propio registro al chequear duplicados. */
    public function nombreUsuarioExisteParaOtro(string $nombreUsuario, int $idPropio): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT id FROM usuarios WHERE nombre_usuario = :nombre_usuario AND id != :id'
        );
        $consulta->execute(['nombre_usuario' => $nombreUsuario, 'id' => $idPropio]);
        return (bool) $consulta->fetch();
    }

    public function actualizarPerfil(int $id, string $nombreCompleto, string $nombreUsuario, string $genero): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuarios
                SET nombre_completo = :nombre_completo, nombre_usuario = :nombre_usuario, genero = :genero
              WHERE id = :id'
        );
        return $consulta->execute([
            'nombre_completo' => $nombreCompleto,
            'nombre_usuario'  => $nombreUsuario,
            'genero'          => $genero,
            'id'              => $id,
        ]);
    }

    public function actualizarFoto(int $id, string $rutaFoto): bool
    {
        $consulta = $this->conexion->prepare('UPDATE usuarios SET foto = :foto WHERE id = :id');
        return $consulta->execute(['foto' => $rutaFoto, 'id' => $id]);
    }

    /** Para editar contacto: el correo es único, hay que ignorar el propio registro. */
    public function emailExisteParaOtro(string $email, int $idPropio): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT id FROM usuarios WHERE email = :email AND id != :id'
        );
        $consulta->execute(['email' => $email, 'id' => $idPropio]);
        return (bool) $consulta->fetch();
    }

    public function actualizarContacto(int $id, string $email, string $celular): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE usuarios SET email = :email, celular = :celular WHERE id = :id'
        );
        return $consulta->execute([
            'email'   => $email,
            'celular' => $celular ?: null,
            'id'      => $id,
        ]);
    }

    public function actualizarContrasena(int $id, string $contrasenaPlano): bool
    {
        $hash = password_hash($contrasenaPlano, PASSWORD_DEFAULT);
        $consulta = $this->conexion->prepare('UPDATE usuarios SET contrasena_hash = :hash WHERE id = :id');
        return $consulta->execute(['hash' => $hash, 'id' => $id]);
    }

    /**
     * "Elimina" la cuenta sin borrar la fila: reemplaza los datos personales
     * por valores genéricos y la desactiva. Así, si el usuario creó o ganó
     * algún torneo, ese historial sigue siendo coherente para otras personas
     * ("Organizador: Usuario eliminado") en vez de romperse por las claves
     * foráneas. La foto real se borra aparte, desde el controlador (acá solo
     * se limpia la columna).
     */
    public function anonimizarCuenta(int $id): bool
    {
        // Contraseña imposible de adivinar: si alguna vez alguien reactivara
        // la fila a mano, no podría loguearse con nada conocido.
        $hashInvalido = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $consulta = $this->conexion->prepare(
            "UPDATE usuarios
                SET nombre_completo = 'Usuario eliminado',
                    nombre_usuario  = CONCAT('usuario_eliminado_', :id),
                    email           = CONCAT('eliminado_', :id, '@baja.local'),
                    celular         = NULL,
                    foto            = NULL,
                    contrasena_hash = :hash,
                    activo          = 0
              WHERE id = :id"
        );
        return $consulta->execute(['id' => $id, 'hash' => $hashInvalido]);
    }

    public function verificarContrasena(string $contrasenaPlano, string $hashGuardado): bool
    {
        return password_verify($contrasenaPlano, $hashGuardado);
    }
}
