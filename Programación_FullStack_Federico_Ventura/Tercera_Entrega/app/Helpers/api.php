<?php
/**
 * app/Helpers/api.php
 *
 * Utilidades de los endpoints JSON de la carpeta api/ (los que usa el
 * JavaScript con fetch, o sea la comunicación asincrónica del sitio).
 */

/** Responde en JSON y corta la ejecución. */
function responderJson(array $datos, int $codigoHttp = 200): never
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Corta con un error de pedido (mensaje legible para mostrar al usuario). */
function responderError(string $mensaje, int $codigoHttp = 400): never
{
    responderJson(['ok' => false, 'mensajes' => [$mensaje]], $codigoHttp);
}
