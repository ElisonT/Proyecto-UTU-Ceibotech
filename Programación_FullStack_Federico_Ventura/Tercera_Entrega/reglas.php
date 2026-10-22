<?php
/**
 * reglas.php?id=N
 * Entrega el PDF de reglas de un torneo (el que se sube en el paso "Reglas" al crearlo).
 * Es público igual que la página del torneo (también para los privados: se llega con el link directo).
 * Se sirve siempre como PDF "inline" con nosniff, para que el navegador no lo interprete como otra cosa.
 * (Sin CSP propia ni sandbox: algunos navegadores no abren su visor de PDF bajo esas políticas; el archivo
 * ya se verificó como PDF real al subirlo.)
 */
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Models/Torneo.php';

$id  = (int) ($_GET['id'] ?? 0);
$pdf = $id > 0 ? (new Torneo())->obtenerReglasPdf($id) : false;
if (!$pdf) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Este torneo no tiene reglas en PDF.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="reglas-torneo-' . $id . '.pdf"');
header('Content-Length: ' . strlen($pdf['contenido']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
echo $pdf['contenido'];
