<?php
/**
 * gestionar-torneo.php
 *
 * Recibe los formularios de gestión que se muestran en detalle.php:
 * generar fixture, avanzar de ronda y cargar un resultado. Solo puede
 * usarlo quien gestiona ese torneo (Administrador general, u Organizador
 * al que se lo asignaron). Siempre vuelve a detalle.php con un aviso.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Enfrentamiento.php';
require_once __DIR__ . '/app/Controllers/CompetenciaController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: busqueda.php');
    exit;
}

$torneoId = (int) ($_POST['torneo_id'] ?? 0);
$torneo   = $torneoId > 0 ? (new Torneo())->obtenerPorId($torneoId) : false;

if (!$torneo || !puedeGestionarTorneo($torneo)) {
    header('Location: index.php');
    exit;
}

$actorId     = (int) $_SESSION['usuario_id'];
$competencia = new CompetenciaController();
$accion      = $_POST['accion'] ?? '';
$errores     = [];
$exito       = '';

switch ($accion) {
    case 'generar_fixture':
        $errores = $competencia->generarFixture($torneoId, $actorId);
        $exito   = 'Fixture generado. El torneo está en curso.';
        break;

    case 'avanzar_ronda':
        $errores = $competencia->avanzarRonda($torneoId, $actorId);
        $exito   = 'Listo, se avanzó de ronda.';
        break;

    case 'cargar_resultado':
        $enfrentamientoId = (int) ($_POST['enfrentamiento_id'] ?? 0);
        $local     = filter_var($_POST['puntaje_local'] ?? '', FILTER_VALIDATE_INT);
        $visitante = filter_var($_POST['puntaje_visitante'] ?? '', FILTER_VALIDATE_INT);

        $enf = (new Enfrentamiento())->obtenerConFormato($enfrentamientoId);
        if (!$enf || (int) $enf['torneo_id'] !== $torneoId) {
            $errores = ['Ese enfrentamiento no pertenece a este torneo.'];
        } elseif ($local === false || $visitante === false) {
            $errores = ['Los puntajes tienen que ser números enteros.'];
        } else {
            $errores = $competencia->cargarResultado($enfrentamientoId, $local, $visitante, $actorId);
            $exito   = 'Resultado guardado.';
        }
        break;

    default:
        $errores = ['Acción desconocida.'];
}

$_SESSION['flash_torneo'] = empty($errores)
    ? ['ok' => true,  'mensajes' => [$exito]]
    : ['ok' => false, 'mensajes' => $errores];

header('Location: detalle.php?id=' . $torneoId);
exit;
