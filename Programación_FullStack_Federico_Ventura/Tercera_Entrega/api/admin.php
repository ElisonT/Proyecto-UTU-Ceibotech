<?php
/**
 * api/admin.php
 *
 * Endpoint JSON del panel de administración (lo llama script.js con fetch,
 * sin recargar la página). Solo el Administrador general.
 *
 *   GET  ?accion=usuarios&q=&estado=&pagina=     listado de usuarios
 *   GET  ?accion=torneos&q=&estado=&pagina=      listado de torneos
 *   GET  ?accion=auditoria&filtro=&q=&pagina=    historial de auditoría
 *   GET  ?accion=resumen                         números del dashboard
 *   POST accion=estado_usuario (usuario_id, activo, csrf)   suspender / reactivar
 */
session_start();
require_once __DIR__ . '/../app/Helpers/manejador_errores.php';
require_once __DIR__ . '/../app/Helpers/roles.php';
require_once __DIR__ . '/../app/Helpers/csrf.php';
require_once __DIR__ . '/../app/Helpers/api.php';
require_once __DIR__ . '/../app/Models/Panel.php';
require_once __DIR__ . '/../app/Models/Auditoria.php';

if (empty($_SESSION['usuario_id'])) {
    responderError('Tu sesión expiró. Volvé a iniciar sesión.', 401);
}
if ((int) ($_SESSION['usuario_rol'] ?? 0) !== ROL_ADMINISTRADOR) {
    responderError('No tenés permiso para hacer esto.', 403);
}

$panel  = new Panel();
$accion = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['accion'] ?? '') : ($_GET['accion'] ?? '');

/** Arma la respuesta paginada común a los listados. */
function listado(array $datos, int $pagina): never
{
    $paginas = max(1, (int) ceil($datos['total'] / Panel::POR_PAGINA));
    responderJson([
        'ok'      => true,
        'filas'   => $datos['filas'],
        'total'   => $datos['total'],
        'pagina'  => min(max(1, $pagina), $paginas),
        'paginas' => $paginas,
    ]);
}

$pagina = (int) ($_GET['pagina'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($accion) {
        case 'resumen':
            responderJson(['ok' => true, 'resumen' => $panel->resumen()]);
        case 'usuarios':
            listado($panel->listarUsuarios(trim((string) ($_GET['q'] ?? '')), (string) ($_GET['estado'] ?? ''), $pagina), $pagina);
        case 'torneos':
            listado($panel->listarTorneos(trim((string) ($_GET['q'] ?? '')), (string) ($_GET['estado'] ?? ''), $pagina), $pagina);
        case 'auditoria':
            listado($panel->listarAuditoria((string) ($_GET['filtro'] ?? ''), trim((string) ($_GET['q'] ?? '')), $pagina), $pagina);
        default:
            responderError('Acción desconocida.', 404);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}
if (!csrfValido()) {
    responderError('La página quedó desactualizada. Recargala e intentá de nuevo.', 403);
}

if ($accion === 'estado_usuario') {
    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
    $activar   = ($_POST['activo'] ?? '') === '1';
    $actorId   = (int) $_SESSION['usuario_id'];

    $objetivo = $usuarioId > 0 ? $panel->buscarUsuario($usuarioId) : false;
    if (!$objetivo) {
        responderError('Ese usuario no existe.', 404);
    }
    if ($usuarioId === $actorId) {
        responderError('No podés suspender tu propia cuenta.');
    }
    if ((int) $objetivo['rol_id'] === ROL_ADMINISTRADOR) {
        responderError('No se puede suspender a otro Administrador general.');
    }
    if (!$panel->cambiarEstadoUsuario($usuarioId, $activar)) {
        responderError('Esa cuenta fue dada de baja y no se puede modificar, o ya tenía ese estado.');
    }

    (new Auditoria())->registrar(
        $actorId, $activar ? 'REACTIVACION_USUARIO' : 'SUSPENSION_USUARIO', 'usuarios', $usuarioId,
        ($activar ? 'Cuenta reactivada: ' : 'Cuenta suspendida: ') . $objetivo['nombre_usuario']
    );

    responderJson([
        'ok'       => true,
        'mensajes' => [$activar ? 'Usuario reactivado.' : 'Usuario suspendido.'],
        'activo'   => $activar,
        'resumen'  => $panel->resumen(),
    ]);
}

responderError('Acción desconocida.', 404);
