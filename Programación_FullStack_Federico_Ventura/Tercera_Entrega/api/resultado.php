<?php
/**
 * api/resultado.php
 *
 * Carga o corrige un resultado sin recargar la página (lo llama script.js con
 * fetch). Misma lógica y mismos permisos que gestionar-torneo.php: solo el
 * Administrador general o el Organizador asignado a ese torneo.
 *
 * POST: accion = cargar | corregir, torneo_id, enfrentamiento_id,
 *       puntaje_local, puntaje_visitante, csrf
 *
 * Responde con la ronda y la tabla de posiciones ya redibujadas (HTML armado
 * con las mismas partials que usa detalle.php), así la pantalla queda igual
 * que si se hubiera recargado. Si el cambio altera el estado del torneo
 * (queda sin partidos pendientes, o se cierra) devuelve "recargar": true
 * para que la página se recargue y muestre los botones de gestión correctos.
 */
session_start();
require_once __DIR__ . '/../app/Helpers/manejador_errores.php';
require_once __DIR__ . '/../app/Helpers/roles.php';
require_once __DIR__ . '/../app/Helpers/texto.php';
require_once __DIR__ . '/../app/Helpers/csrf.php';
require_once __DIR__ . '/../app/Helpers/api.php';
require_once __DIR__ . '/../app/Models/Torneo.php';
require_once __DIR__ . '/../app/Models/Enfrentamiento.php';
require_once __DIR__ . '/../app/Models/TablaPosiciones.php';
require_once __DIR__ . '/../app/Controllers/CompetenciaController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderError('Método no permitido.', 405);
}
if (empty($_SESSION['usuario_id'])) {
    responderError('Tu sesión expiró. Volvé a iniciar sesión.', 401);
}
if (!csrfValido()) {
    responderError('La página quedó desactualizada. Recargala e intentá de nuevo.', 403);
}

$torneoId = (int) ($_POST['torneo_id'] ?? 0);
$modeloTorneo = new Torneo();
$torneo = $torneoId > 0 ? $modeloTorneo->obtenerPorId($torneoId) : false;
if (!$torneo || !puedeGestionarTorneo($torneo)) {
    responderError('No tenés permiso para gestionar este torneo.', 403);
}

$accion           = $_POST['accion'] ?? '';
$enfrentamientoId = (int) ($_POST['enfrentamiento_id'] ?? 0);
$local            = filter_var($_POST['puntaje_local'] ?? '', FILTER_VALIDATE_INT);
$visitante        = filter_var($_POST['puntaje_visitante'] ?? '', FILTER_VALIDATE_INT);

if (!in_array($accion, ['cargar', 'corregir'], true)) {
    responderError('Acción desconocida.');
}
if ($local === false || $visitante === false) {
    responderError('Los puntajes tienen que ser números enteros.');
}

$modeloEnf = new Enfrentamiento();
$enf = $modeloEnf->obtenerConFormato($enfrentamientoId);
if (!$enf || (int) $enf['torneo_id'] !== $torneoId) {
    responderError('Ese enfrentamiento no pertenece a este torneo.');
}

$competencia = new CompetenciaController();
$actorId     = (int) $_SESSION['usuario_id'];
$errores     = $accion === 'corregir'
    ? $competencia->corregirResultado($enfrentamientoId, $local, $visitante, $actorId)
    : $competencia->cargarResultado($enfrentamientoId, $local, $visitante, $actorId);

if (!empty($errores)) {
    responderJson(['ok' => false, 'mensajes' => $errores], 422);
}

// --- Todo salió bien: se vuelve a leer el estado y se redibuja lo que cambió ---
$torneoDespues = $modeloTorneo->obtenerPorId($torneoId);
$rondas        = $modeloEnf->listarRondasConEnfrentamientos($torneoId);

$jugados = $pendientes = 0;
$rondaAfectada = null;
foreach ($rondas as $r) {
    foreach ($r['enfrentamientos'] as $e) {
        if ($e['inscripcion_visitante_id'] === null) {
            continue;
        }
        (int) $e['tiene_resultado'] === 1 ? $jugados++ : $pendientes++;
        if ((int) $e['id'] === $enfrentamientoId) {
            $rondaAfectada = $r;
        }
    }
}

$puedeGestionar = true;
$ronda          = $rondaAfectada;
ob_start();
include __DIR__ . '/../app/Views/partials/ronda.php';
$rondaHtml = ob_get_clean();

$tablaHtml = null;
if ($torneo['formato'] !== 'eliminacion') {
    $tabla  = (new TablaPosiciones())->listarPorTorneo($torneoId);
    $unidad = $torneo['modalidad'] === 'individual' ? 'participantes' : 'equipos';
    ob_start();
    include __DIR__ . '/../app/Views/partials/tabla_posiciones.php';
    $tablaHtml = ob_get_clean();
}

// Si el estado del torneo cambió, o ya no quedan pendientes (aparece el botón de avanzar
// o cerrar), la página se recarga para mostrar los controles de gestión correctos.
$recargar = $torneoDespues['estado'] !== $torneo['estado']
    || $torneoDespues['estado'] === 'finalizado'
    || $pendientes === 0;

responderJson([
    'ok'        => true,
    'mensajes'  => [$accion === 'corregir' ? 'Resultado corregido.' : 'Resultado guardado.'],
    'rondaId'   => (int) $rondaAfectada['id'],
    'rondaHtml' => $rondaHtml,
    'tablaHtml' => $tablaHtml,
    'stats'     => ['jugados' => $jugados, 'pendientes' => $pendientes],
    'recargar'  => $recargar,
]);
