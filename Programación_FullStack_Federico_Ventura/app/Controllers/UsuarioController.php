<?php
/**
 * app/Controllers/UsuarioController.php
 *
 * Controlador: recibe los datos del formulario (ya en $_POST),
 * los valida, y coordina con el Modelo. No genera HTML: eso
 * queda en la Vista (register.php / login.php).
 */

require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Auditoria.php';

class UsuarioController
{
    private Usuario $modeloUsuario;
    private Auditoria $auditoria;

    public function __construct()
    {
        $this->modeloUsuario = new Usuario();
        $this->auditoria     = new Auditoria();
    }

    /**
     * Procesa el formulario de registro.
     * @return string[] Lista de errores. Vacía si el registro salió bien.
     */
    public function procesarRegistro(array $datos): array
    {
        $errores = [];

        $nombreUsuario  = trim($datos['usuario'] ?? '');
        $nombreCompleto = trim($datos['nombre'] ?? '');
        $email          = trim($datos['email'] ?? '');
        $celular        = trim($datos['celular'] ?? '');
        $genero         = $datos['genero'] ?? 'otro';
        $contrasena     = $datos['password'] ?? '';
        $confirmacion   = $datos['confirm'] ?? '';

        // ---- Validaciones del lado del servidor ----
        // (el HTML ya valida en el navegador, pero eso se puede saltear,
        //  así que estas son las que realmente importan).
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $nombreUsuario)) {
            $errores[] = 'El nombre de usuario solo puede tener letras, números y guion bajo.';
        }
        if ($nombreCompleto === '' || !str_contains(trim($nombreCompleto), ' ')) {
            $errores[] = 'Ingresá tu nombre y apellido.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if (strlen($contrasena) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($contrasena !== $confirmacion) {
            $errores[] = 'Las contraseñas no coinciden.';
        }

        // Solo se consulta la base si las validaciones básicas ya pasaron
        // (para no gastar una consulta con datos que ya sabemos inválidos).
        if (empty($errores)) {
            if ($this->modeloUsuario->nombreUsuarioExiste($nombreUsuario)) {
                $errores[] = 'Ese nombre de usuario ya está en uso.';
            }
            if ($this->modeloUsuario->emailExiste($email)) {
                $errores[] = 'Ya existe una cuenta registrada con ese correo.';
            }
        }

        if (empty($errores)) {
            $idNuevo = $this->modeloUsuario->registrar([
                'nombre_usuario'  => $nombreUsuario,
                'nombre_completo' => $nombreCompleto,
                'email'           => $email,
                'celular'         => $celular,
                'genero'          => $genero,
                'contrasena'      => $contrasena,
            ]);

            if ($idNuevo === false) {
                $errores[] = 'No se pudo completar el registro. Probá de nuevo.';
            } else {
                // El propio usuario recién creado es "quien hizo" la acción
                // (todavía no hay sesión iniciada en el momento del registro).
                $this->auditoria->registrar($idNuevo, 'ALTA_USUARIO', 'usuarios', $idNuevo, "Autoregistro de '{$nombreUsuario}'");
            }
        }

        return $errores;
    }

    /**
     * Procesa la subida de una nueva foto de perfil.
     * @return string[] Lista de errores. Vacía si no hay nada que reportar
     *                   (incluye el caso de "no se eligió ningún archivo").
     */
    public function procesarFoto(int $idUsuario, array $archivo): array
    {
        $errores = [];

        // No es un error: el usuario puede guardar el resto del perfil sin cambiar la foto.
        if (!isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return $errores;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'Hubo un problema al subir la imagen. Probá de nuevo.';
            return $errores;
        }

        $tamanioMaximo = 2 * 1024 * 1024; // 2 MB
        if ($archivo['size'] > $tamanioMaximo) {
            $errores[] = 'La foto no puede pesar más de 2 MB.';
            return $errores;
        }

        // Se valida el contenido real del archivo, no solo la extensión del
        // nombre (un .jpg falso podría en realidad ser otra cosa).
        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $tipoReal = mime_content_type($archivo['tmp_name']);

        if (!isset($tiposPermitidos[$tipoReal])) {
            $errores[] = 'La foto tiene que ser JPG, PNG o WEBP.';
            return $errores;
        }

        $extension       = $tiposPermitidos[$tipoReal];
        $nombreArchivo   = 'usuario_' . $idUsuario . '_' . time() . '.' . $extension;
        $carpetaDestino  = __DIR__ . '/../../uploads/avatars/';
        $rutaCompleta    = $carpetaDestino . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            $errores[] = 'No se pudo guardar la imagen en el servidor.';
            return $errores;
        }

        // Se guarda solo la ruta relativa: la misma que después usa <img src="...">
        $exito = $this->modeloUsuario->actualizarFoto($idUsuario, 'uploads/avatars/' . $nombreArchivo);
        if (!$exito) {
            $errores[] = 'La imagen se subió pero no se pudo asociar a tu perfil.';
        } else {
            $this->auditoria->registrar($idUsuario, 'CAMBIO_FOTO', 'usuarios', $idUsuario);
        }

        return $errores;
    }

    /**
     * Procesa el formulario de "Editar perfil".
     * @return string[] Lista de errores. Vacía si se guardó bien.
     */
    public function procesarEdicionPerfil(int $idUsuario, array $datos): array
    {
        $errores = [];

        $nombreUsuario  = trim($datos['usuario'] ?? '');
        $nombreCompleto = trim($datos['nombre'] ?? '');
        $genero         = $datos['genero'] ?? 'otro';

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $nombreUsuario)) {
            $errores[] = 'El nombre de usuario solo puede tener letras, números y guion bajo.';
        }
        if ($nombreCompleto === '' || !str_contains(trim($nombreCompleto), ' ')) {
            $errores[] = 'Ingresá tu nombre y apellido.';
        }

        if (empty($errores) && $this->modeloUsuario->nombreUsuarioExisteParaOtro($nombreUsuario, $idUsuario)) {
            $errores[] = 'Ese nombre de usuario ya está en uso por otra cuenta.';
        }

        if (empty($errores)) {
            $exito = $this->modeloUsuario->actualizarPerfil($idUsuario, $nombreCompleto, $nombreUsuario, $genero);
            if (!$exito) {
                $errores[] = 'No se pudo actualizar el perfil. Probá de nuevo.';
            } else {
                $this->auditoria->registrar($idUsuario, 'EDICION_PERFIL', 'usuarios', $idUsuario);
            }
        }

        return $errores;
    }

    /**
     * Procesa el formulario de "Datos de contacto" (correo y celular).
     * @return string[] Lista de errores. Vacía si se guardó bien.
     */
    public function procesarContacto(int $idUsuario, array $datos): array
    {
        $errores = [];

        $email   = trim($datos['correo'] ?? '');
        $celular = trim($datos['celular'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if ($celular !== '' && !preg_match('/^[0-9+\s]+$/', $celular)) {
            $errores[] = 'El celular solo puede tener números, espacios y "+".';
        }

        if (empty($errores) && $this->modeloUsuario->emailExisteParaOtro($email, $idUsuario)) {
            $errores[] = 'Ya existe otra cuenta con ese correo.';
        }

        if (empty($errores)) {
            $exito = $this->modeloUsuario->actualizarContacto($idUsuario, $email, $celular);
            if (!$exito) {
                $errores[] = 'No se pudieron guardar los datos de contacto. Probá de nuevo.';
            } else {
                $this->auditoria->registrar($idUsuario, 'EDICION_CONTACTO', 'usuarios', $idUsuario);
            }
        }

        return $errores;
    }

    /**
     * Procesa el formulario de "Cambiar contraseña".
     * @return string[] Lista de errores. Vacía si se guardó bien.
     */
    public function procesarCambioContrasena(int $idUsuario, array $datos): array
    {
        $errores = [];

        $actual      = $datos['pass_actual']    ?? '';
        $nueva       = $datos['pass_nueva']     ?? '';
        $confirmar   = $datos['pass_confirmar'] ?? '';

        $usuario = $this->modeloUsuario->buscarPorId($idUsuario);
        if (!$usuario || !$this->modeloUsuario->verificarContrasena($actual, $usuario['contrasena_hash'])) {
            $errores[] = 'La contraseña actual no es correcta.';
        }

        if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9]).{8,}$/', $nueva)) {
            $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres, una mayúscula y un número.';
        }
        if ($nueva !== $confirmar) {
            $errores[] = 'Las contraseñas nuevas no coinciden.';
        }

        if (empty($errores)) {
            $exito = $this->modeloUsuario->actualizarContrasena($idUsuario, $nueva);
            if (!$exito) {
                $errores[] = 'No se pudo cambiar la contraseña. Probá de nuevo.';
            } else {
                // Ojo: nunca se guarda la contraseña (ni la vieja ni la nueva) en la auditoría.
                $this->auditoria->registrar($idUsuario, 'CAMBIO_CONTRASENA', 'usuarios', $idUsuario);
            }
        }

        return $errores;
    }

    /**
     * Procesa la eliminación de cuenta. Pide la contraseña actual como
     * confirmación (para que no baste con haber dejado la sesión abierta).
     * No borra la fila: la anonimiza (ver Usuario::anonimizarCuenta), para
     * no romper el historial de torneos que otras personas puedan compartir
     * con este usuario (como organizador o como ganador).
     * @return string[] Lista de errores. Vacía si se eliminó la cuenta.
     */
    public function procesarEliminacionCuenta(int $idUsuario, string $contrasenaActual): array
    {
        $errores = [];

        $usuario = $this->modeloUsuario->buscarPorId($idUsuario);
        if (!$usuario || !$this->modeloUsuario->verificarContrasena($contrasenaActual, $usuario['contrasena_hash'])) {
            $errores[] = 'La contraseña ingresada no es correcta.';
            return $errores;
        }

        // Se borra el archivo de la foto real del servidor antes de limpiar
        // la columna (una vez anonimizada la fila, ya no tendríamos la ruta).
        if (!empty($usuario['foto'])) {
            $rutaFoto = __DIR__ . '/../../' . $usuario['foto'];
            if (is_file($rutaFoto)) {
                @unlink($rutaFoto);
            }
        }

        if (!$this->modeloUsuario->anonimizarCuenta($idUsuario)) {
            $errores[] = 'No se pudo eliminar la cuenta. Probá de nuevo.';
        } else {
            $this->auditoria->registrar($idUsuario, 'ELIMINACION_CUENTA', 'usuarios', $idUsuario);
        }

        return $errores;
    }

    /**
     * Procesa el formulario de login.
     * @return string|null Mensaje de error, o null si el login fue exitoso.
     */
    public function procesarLogin(string $login, string $contrasena): ?string
    {
        $login = trim($login);

        if ($login === '' || $contrasena === '') {
            return 'Completá usuario/correo y contraseña.';
        }

        $usuario = $this->modeloUsuario->buscarPorLogin($login);

        if (!$usuario || !$this->modeloUsuario->verificarContrasena($contrasena, $usuario['contrasena_hash'])) {
            // Mensaje genérico a propósito: no decimos si falló el usuario
            // o la contraseña, para no facilitarle el trabajo a un atacante.
            return 'Usuario o contraseña incorrectos.';
        }

        if (!$usuario['activo']) {
            return 'Esta cuenta está suspendida. Contactate con un administrador.';
        }

        // Solo se guarda en sesión lo necesario; nunca la contraseña ni su hash.
        $_SESSION['usuario_id']     = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre_completo'];
        $_SESSION['usuario_rol']    = (int) $usuario['rol_id'];
        $_SESSION['usuario_foto']   = $usuario['foto'];

        $this->auditoria->registrar((int) $usuario['id'], 'LOGIN', 'usuarios', (int) $usuario['id']);

        return null;
    }
}
