<?php
/**
 * reporte-torneo.php
 *
 * Reporte de UN torneo (letra 5.2): datos, inscriptos, tabla de posiciones y resultados, en HTML
 * imprimible o CSV. Lo puede pedir quien gestiona ese torneo: el Administrador general o el
 * Organizador al que se lo asignaron. Solo lectura; queda en auditoría (REPORTE_GENERADO).
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/reporte.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Reporte.php';
require_once __DIR__ . '/app/Models/Auditoria.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$torneoId = (int) ($_GET['id'] ?? 0);
$torneo   = $torneoId > 0 ? (new Torneo())->obtenerPorId($torneoId) : false;
if (!$torneo || !puedeGestionarTorneo($torneo)) {
    header('Location: index.php');
    exit;
}

$reporte = (new Reporte())->torneo($torneoId);
if (!$reporte) {
    header('Location: index.php');
    exit;
}

$formato = ($_GET['formato'] ?? 'html') === 'csv' ? 'csv' : 'html';
(new Auditoria())->registrar((int) $_SESSION['usuario_id'], 'REPORTE_GENERADO', 'torneos', $torneoId,
    "Reporte del torneo #{$torneoId} ({$formato})");

if ($formato === 'csv') {
    reporte_csv($reporte, reporteNombreArchivo('torneo-' . $torneo['nombre']));
    exit;
}

reporte_html($reporte, 'detalle.php?id=' . $torneoId, 'reporte-torneo.php?id=' . $torneoId . '&formato=csv');
