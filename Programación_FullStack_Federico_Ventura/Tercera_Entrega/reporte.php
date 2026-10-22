<?php
/**
 * reporte.php
 *
 * Reportes del Administrador general (letra 5.1): resumen del sistema, torneos, usuarios y auditoría.
 * Se piden por GET desde la vista "Reportes" de admin.php y salen en HTML imprimible o en CSV.
 * Solo lectura. Cada generación queda en auditoría (REPORTE_GENERADO), porque el de usuarios
 * contiene datos personales.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/reporte.php';
require_once __DIR__ . '/app/Models/Reporte.php';
require_once __DIR__ . '/app/Models/Auditoria.php';

if (!esAdministrador()) {
    header('Location: index.php');
    exit;
}

/** Fecha YYYY-MM-DD válida o cadena vacía. */
function fechaFiltro(string $valor): string
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return $valor;
    }
    return '';
}

$tipo    = $_GET['tipo'] ?? '';
$formato = ($_GET['formato'] ?? 'html') === 'csv' ? 'csv' : 'html';
$modelo  = new Reporte();

switch ($tipo) {
    case 'resumen':
        $reporte = $modelo->resumen();
        $filtros = '';
        break;

    case 'torneos':
        $estado  = isset(Reporte::ETIQUETA_ESTADO[$_GET['estado'] ?? '']) ? $_GET['estado'] : '';
        $reporte = $modelo->torneos($estado);
        $filtros = "estado=" . ($estado ?: 'todos');
        break;

    case 'usuarios':
        $estado  = in_array($_GET['estado'] ?? '', ['activo', 'suspendido', 'eliminado'], true) ? $_GET['estado'] : '';
        $rol     = (int) ($_GET['rol'] ?? 0);
        $rol     = in_array($rol, [ROL_ADMINISTRADOR, ROL_ORGANIZADOR, ROL_PARTICIPANTE], true) ? $rol : 0;
        $reporte = $modelo->usuarios($estado, $rol);
        $filtros = 'estado=' . ($estado ?: 'todos') . ', rol=' . ($rol ?: 'todos');
        break;

    case 'auditoria':
        $desde   = fechaFiltro((string) ($_GET['desde'] ?? ''));
        $hasta   = fechaFiltro((string) ($_GET['hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $accion  = mb_substr(trim((string) ($_GET['accion'] ?? '')), 0, 60);
        $reporte = $modelo->auditoria($desde, $hasta, $accion);
        $filtros = "desde=" . ($desde ?: '-') . ', hasta=' . ($hasta ?: '-') . ', accion=' . ($accion ?: 'todas');
        break;

    default:
        header('Location: admin.php#reportes');
        exit;
}

(new Auditoria())->registrar((int) $_SESSION['usuario_id'], 'REPORTE_GENERADO', 'reportes', null,
    "Reporte {$tipo} ({$formato})" . ($filtros !== '' ? " — {$filtros}" : ''));

if ($formato === 'csv') {
    reporte_csv($reporte, reporteNombreArchivo('reporte-' . $tipo));
    exit;
}

$consulta = $_GET;
$consulta['formato'] = 'csv';
reporte_html($reporte, 'admin.php#reportes', 'reporte.php?' . http_build_query($consulta));
