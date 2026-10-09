<?php
/**
 * gestionar-equipo.php
 *
 * Recibe los formularios de mis-equipos.php: crear equipo, invitar, aceptar
 * o rechazar una invitación, cancelar una invitación, sacar a un integrante,
 * salir de un equipo y disolverlo. Solo cuentas de Participante. Siempre
 * vuelve a mis-equipos.php con un aviso.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Controllers/EquipoController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mis-equipos.php');
    exit;
}
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if ((int) ($_SESSION['usuario_rol'] ?? 0) !== ROL_PARTICIPANTE) {
    header('Location: index.php');
    exit;
}

$usuarioId   = (int) $_SESSION['usuario_id'];
$controlador = new EquipoController();
$accion      = $_POST['accion'] ?? '';
$equipoId    = (int) ($_POST['equipo_id'] ?? 0);
$errores     = [];
$exito       = '';

switch ($accion) {
    case 'crear_equipo':
        $errores = $controlador->crear((string) ($_POST['nombre_equipo'] ?? ''), $usuarioId, (int) ($_POST['deporte_id'] ?? 0));
        $exito   = 'Equipo creado. Ahora podés invitar a tus compañeros.';
        break;

    case 'invitar':
        $errores = $controlador->invitar($equipoId, $usuarioId, (string) ($_POST['nombre_usuario'] ?? ''));
        $exito   = 'Invitación enviada. La persona tiene que aceptarla para entrar al equipo.';
        break;

    case 'responder_invitacion':
        $acepta  = ($_POST['respuesta'] ?? '') === 'aceptar';
        $errores = $controlador->responderInvitacion((int) ($_POST['invitacion_id'] ?? 0), $usuarioId, $acepta);
        $exito   = $acepta ? '¡Listo, ya sos parte del equipo!' : 'Invitación rechazada.';
        break;

    case 'cancelar_invitacion':
        $errores = $controlador->cancelarInvitacion((int) ($_POST['invitacion_id'] ?? 0), $usuarioId);
        $exito   = 'Invitación cancelada.';
        break;

    case 'quitar_miembro':
        $errores = $controlador->quitarMiembro($equipoId, $usuarioId, (int) ($_POST['usuario_id'] ?? 0));
        $exito   = 'Integrante sacado del equipo.';
        break;

    case 'salir_equipo':
        $errores = $controlador->salir($equipoId, $usuarioId);
        $exito   = 'Abandonaste el equipo.';
        break;

    case 'disolver_equipo':
        $errores = $controlador->disolver($equipoId, $usuarioId);
        $exito   = 'Equipo disuelto.';
        break;

    default:
        $errores = ['Acción desconocida.'];
}

$_SESSION['flash_equipos'] = empty($errores)
    ? ['ok' => true,  'mensajes' => [$exito]]
    : ['ok' => false, 'mensajes' => $errores];

header('Location: mis-equipos.php');
exit;
