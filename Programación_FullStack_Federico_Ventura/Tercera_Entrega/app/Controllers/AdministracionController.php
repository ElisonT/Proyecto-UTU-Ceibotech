<?php
/**
 * app/Controllers/AdministracionController.php
 *
 * Lógica de la administración de cuentas que hace el Administrador general
 * (letra 5.1: crear, editar y eliminar usuarios administrativos). El controlador
 * valida y decide; el modelo Usuario solo guarda. Quién puede llamar a esto
 * (solo el administrador) y el token CSRF los comprueba api/admin.php.
 *
 * Reglas de protección:
 *  - Los administradores están protegidos entre sí: desde el panel no se puede
 *    cambiar el rol ni dar de baja a otro administrador (así nunca se queda el
 *    sistema sin administradores por un error). Quien quiera salir lo hace desde
 *    su propio perfil.
 *  - Nadie cambia su propio rol ni se da de baja desde el panel.
 *  - Un organizador con torneos sin finalizar no puede perder el rol ni la
 *    cuenta: primero hay que asignar esos torneos a otro organizador.
 *  - Una cuenta ya dada de baja no se puede modificar.
 * Además administra los módulos de competencia (habilitar o deshabilitar un formato
 * para torneos nuevos) y la configuración general (letra 5.1).
 *
 * "Eliminar" una cuenta es darla de baja (anonimizarla), igual que cuando la
 * elimina su dueño: se borran los datos personales pero se conserva el historial
 * de torneos y resultados.
 */

require_once __DIR__ . '/../Helpers/roles.php';
require_once __DIR__ . '/../Helpers/contrasena.php';
require_once __DIR__ . '/../Helpers/validaciones_usuario.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Models/Auditoria.php';
require_once __DIR__ . '/../Models/Configuracion.php';

class AdministracionController
{
    private const ROLES = [
        ROL_ADMINISTRADOR => 'Administrador general',
        ROL_ORGANIZADOR   => 'Organizador de torneo',
        ROL_PARTICIPANTE  => 'Participante',
    ];

    private Usuario $usuarios;
    private Auditoria $auditoria;
    private Configuracion $config;

    public function __construct()
    {
        $this->usuarios  = new Usuario();
        $this->auditoria = new Auditoria();
        $this->config    = new Configuracion();
    }

    /**
     * Crea una cuenta con el rol elegido.
     * @return array{errores: string[], id: int|null}
     */
    public function crearUsuario(array $datos, int $actorId): array
    {
        $usuario  = trim((string) ($datos['usuario'] ?? ''));
        $nombre   = trim((string) ($datos['nombre'] ?? ''));
        $email    = trim((string) ($datos['email'] ?? ''));
        $rolId    = (int) ($datos['rol_id'] ?? 0);
        $clave    = (string) ($datos['password'] ?? '');
        $confirma = (string) ($datos['confirm'] ?? '');

        $errores = erroresDatosCuenta($usuario, $nombre, $email);
        if (!isset(self::ROLES[$rolId])) {
            $errores[] = 'Elegí un rol válido.';
        }
        if ($errorClave = errorPoliticaContrasena($clave)) {
            $errores[] = $errorClave;
        }
        if ($clave !== $confirma) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if (empty($errores)) {
            if ($this->usuarios->nombreUsuarioExiste($usuario)) {
                $errores[] = 'Ese nombre de usuario ya está en uso.';
            }
            if ($this->usuarios->emailExiste($email)) {
                $errores[] = 'Ya existe una cuenta registrada con ese correo.';
            }
        }
        if (!empty($errores)) {
            return ['errores' => $errores, 'id' => null];
        }

        $id = $this->usuarios->registrar([
            'nombre_usuario'  => $usuario,
            'nombre_completo' => $nombre,
            'email'           => $email,
            'celular'         => '',
            'genero'          => 'otro',
            'contrasena'      => $clave,
        ], $rolId);
        if ($id === false) {
            return ['errores' => ['No se pudo crear la cuenta. Probá de nuevo.'], 'id' => null];
        }

        // Nunca se guarda la contraseña en la auditoría.
        $this->auditoria->registrar($actorId, 'ALTA_USUARIO_ADMIN', 'usuarios', $id,
            "Cuenta creada por un administrador: '{$usuario}' con rol " . self::ROLES[$rolId]);
        return ['errores' => [], 'id' => $id];
    }

    /**
     * Cambia nombre, correo y rol de una cuenta (el nombre de usuario no se cambia).
     * @return string[] Lista de errores (vacía si se guardó).
     */
    public function editarUsuario(int $id, array $datos, int $actorId): array
    {
        $objetivo = $this->usuarios->buscarPorId($id);
        if (!$objetivo) {
            return ['Ese usuario no existe.'];
        }
        if ($this->usuarios->estaDadaDeBaja($objetivo)) {
            return ['Esa cuenta fue dada de baja y no se puede modificar.'];
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $email  = trim((string) ($datos['email'] ?? ''));
        $rolId  = (int) ($datos['rol_id'] ?? 0);

        $errores = erroresDatosCuenta(null, $nombre, $email);
        if (!isset(self::ROLES[$rolId])) {
            $errores[] = 'Elegí un rol válido.';
        }
        if (empty($errores) && $this->usuarios->emailExisteParaOtro($email, $id)) {
            $errores[] = 'Ya existe otra cuenta registrada con ese correo.';
        }
        if (!empty($errores)) {
            return $errores;
        }

        $rolActual = (int) $objetivo['rol_id'];
        if ($rolId !== $rolActual) {
            if ($id === $actorId) {
                return ['No podés cambiar tu propio rol.'];
            }
            if ($rolActual === ROL_ADMINISTRADOR) {
                return ['No se puede cambiar el rol de otro administrador general.'];
            }
            if ($rolActual === ROL_ORGANIZADOR) {
                $abiertos = $this->usuarios->contarTorneosSinFinalizar($id);
                if ($abiertos > 0) {
                    return ["Tiene {$abiertos} torneo(s) asignado(s) sin finalizar: asignalos a otro organizador antes de cambiarle el rol."];
                }
            }
        }

        if (!$this->usuarios->actualizarDatosAdmin($id, $nombre, $email, $rolId)) {
            return ['No se pudieron guardar los cambios. Probá de nuevo.'];
        }

        $cambios = [];
        if ($nombre !== $objetivo['nombre_completo']) {
            $cambios[] = 'nombre';
        }
        if ($email !== $objetivo['email']) {
            $cambios[] = 'correo';
        }
        if ($rolId !== $rolActual) {
            $cambios[] = 'rol (' . self::ROLES[$rolActual] . ' a ' . self::ROLES[$rolId] . ')';
        }
        $this->auditoria->registrar($actorId, 'EDICION_USUARIO', 'usuarios', $id,
            "Cuenta '{$objetivo['nombre_usuario']}' editada: " . ($cambios ? implode(', ', $cambios) : 'sin cambios'));
        return [];
    }

    /**
     * Da de baja (anonimiza) una cuenta.
     * @return string[] Lista de errores (vacía si se dio de baja).
     */
    public function darDeBaja(int $id, int $actorId): array
    {
        $objetivo = $this->usuarios->buscarPorId($id);
        if (!$objetivo) {
            return ['Ese usuario no existe.'];
        }
        if ($this->usuarios->estaDadaDeBaja($objetivo)) {
            return ['Esa cuenta ya fue dada de baja.'];
        }
        if ($id === $actorId) {
            return ['No podés dar de baja tu propia cuenta desde el panel.'];
        }
        if ((int) $objetivo['rol_id'] === ROL_ADMINISTRADOR) {
            return ['No se puede dar de baja a otro administrador general.'];
        }
        $abiertos = $this->usuarios->contarTorneosSinFinalizar($id);
        if ($abiertos > 0) {
            return ["Tiene {$abiertos} torneo(s) asignado(s) sin finalizar: asignalos a otro organizador antes de darlo de baja."];
        }

        // Se borra la foto del servidor antes de anonimizar (después ya no tendríamos la ruta).
        if (!empty($objetivo['foto'])) {
            $ruta = __DIR__ . '/../../' . $objetivo['foto'];
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
        if (!$this->usuarios->anonimizarCuenta($id)) {
            return ['No se pudo dar de baja la cuenta. Probá de nuevo.'];
        }
        $this->auditoria->registrar($actorId, 'BAJA_USUARIO_ADMIN', 'usuarios', $id,
            "Cuenta '{$objetivo['nombre_usuario']}' dada de baja por un administrador");
        return [];
    }

    /**
     * Habilita o deshabilita un módulo de competencia. Un módulo deshabilitado deja de
     * ofrecerse al crear torneos nuevos; los torneos existentes con ese formato siguen
     * hasta terminar. Siempre tiene que quedar al menos un módulo habilitado.
     * @return string[] Lista de errores (vacía si se cambió).
     */
    public function cambiarModulo(string $formato, bool $habilitar, int $actorId): array
    {
        if (!in_array($formato, Configuracion::FORMATOS, true)) {
            return ['Ese módulo no existe.'];
        }
        $habilitados = $this->config->formatosHabilitados();
        if (in_array($formato, $habilitados, true) === $habilitar) {
            return [$habilitar ? 'Ese módulo ya estaba habilitado.' : 'Ese módulo ya estaba deshabilitado.'];
        }
        if (!$habilitar && count($habilitados) <= 1) {
            return ['Tiene que quedar al menos un módulo habilitado.'];
        }
        if (!$this->config->cambiarModulo($formato, $habilitar, $actorId)) {
            return ['No se pudo cambiar el módulo. Probá de nuevo.'];
        }
        $this->auditoria->registrar($actorId, 'CAMBIO_MODULO', 'modulos_competencia', null,
            "Módulo '{$formato}' " . ($habilitar ? 'habilitado' : 'deshabilitado'));
        return [];
    }

    /**
     * Guarda la configuración general.
     * @return array{errores: string[], mensaje: string}
     */
    public function guardarConfiguracion(array $datos, int $actorId): array
    {
        $max = filter_var($datos['max_participantes_por_torneo'] ?? '', FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2, 'max_range' => Configuracion::MAX_PARTICIPANTES_POR_DEFECTO]]);
        $registro = $datos['permitir_registro'] ?? '';
        $validar  = $datos['validar_resultados'] ?? ($this->config->todas()['validar_resultados'] ?? '0');

        $errores = [];
        if ($max === false) {
            $errores[] = 'El cupo máximo tiene que ser un número entre 2 y ' . Configuracion::MAX_PARTICIPANTES_POR_DEFECTO . '.';
        }
        if (!in_array($registro, ['0', '1'], true)) {
            $errores[] = 'El valor de "permitir registro" no es válido.';
        }
        if (!in_array($validar, ['0', '1'], true)) {
            $errores[] = 'El valor de "validar resultados" no es válido.';
        }
        if (!empty($errores)) {
            return ['errores' => $errores, 'mensaje' => ''];
        }

        $actual = $this->config->todas();
        $nuevo  = ['max_participantes_por_torneo' => (string) $max, 'permitir_registro' => $registro, 'validar_resultados' => $validar];
        $cambios = [];
        foreach ($nuevo as $clave => $valor) {
            if (($actual[$clave] ?? null) !== $valor) {
                $this->config->establecer($clave, $valor, $actorId);
                $cambios[] = "{$clave}: " . ($actual[$clave] ?? '(sin valor)') . " a {$valor}";
            }
        }
        if (empty($cambios)) {
            return ['errores' => [], 'mensaje' => 'No había cambios para guardar.'];
        }
        $this->auditoria->registrar($actorId, 'CAMBIO_CONFIGURACION', 'configuracion', null, implode('; ', $cambios));
        return ['errores' => [], 'mensaje' => 'Configuración guardada.'];
    }
}
