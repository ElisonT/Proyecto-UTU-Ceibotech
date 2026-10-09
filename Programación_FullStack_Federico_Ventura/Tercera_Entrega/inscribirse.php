<?php
/**
 * inscribirse.php
 *
 * Recibe los formularios de inscripción de detalle.php: inscribirme (torneo
 * individual), inscribir un equipo (solo su capitán) y darme de baja. Solo las
 * cuentas de Participante pueden inscribirse (ver letra). Siempre vuelve a
 * detalle.php con un aviso, usando la misma "flash" que gestionar-torneo.php.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Controllers/InscripcionController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: busqueda.php');
    exit;
}

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$torneoId = (int) ($_POST['torneo_id'] ?? 0);
if ($torneoId <= 0) {
    header('Location: busqueda.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$accion    = $_POST['accion'] ?? '';
$errores   = [];
$exito     = '';

if ((int) ($_SESSION['usuario_rol'] ?? 0) !== ROL_PARTICIPANTE) {
    $errores = ['Solo las cuentas de participante pueden inscribirse a un torneo.'];
} else {
    $controlador = new InscripcionController();

    switch ($accion) {
        case 'inscribirme':
            $errores = $controlador->inscribirIndividual($torneoId, $usuarioId);
            $exito   = '¡Te inscribiste al torneo!';
            break;

        case 'inscribir_equipo':
            $errores = $controlador->inscribirEquipo($torneoId, (int) ($_POST['equipo_id'] ?? 0), $usuarioId);
            $exito   = 'Equipo inscripto al torneo.';
            break;

        case 'darme_de_baja':
            $errores = $controlador->darseDeBaja($torneoId, $usuarioId);
            $exito   = 'Te diste de baja del torneo.';
            break;

        default:
            $errores = ['Acción desconocida.'];
    }
}

$_SESSION['flash_torneo'] = empty($errores)
    ? ['ok' => true,  'mensajes' => [$exito]]
    : ['ok' => false, 'mensajes' => $errores];

header('Location: detalle.php?id=' . $torneoId);
exit;
