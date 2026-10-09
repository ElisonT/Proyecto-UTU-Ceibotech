<?php
/**
 * app/Helpers/manejador_errores.php
 *
 * Manejo centralizado de errores (ver letra, sección 4.2: "manejo
 * centralizado de errores"). En vez de que cada página se las arregle
 * sola con try/catch propios y dispares, cualquier error o excepción
 * no controlada de todo el sitio pasa por acá:
 *
 *   1) Siempre se registra en el log del servidor (nunca se pierde
 *      información para investigar qué pasó).
 *   2) Al usuario final se le muestra un mensaje genérico, nunca el
 *      detalle técnico (ruta del servidor, consulta SQL, stack trace),
 *      que podría filtrar información sensible del sistema.
 *
 * Se incluye al principio de cada página, antes de cualquier otra
 * lógica. Los errores que sí tienen un mensaje específico y útil para
 * el usuario (por ejemplo, "no se pudo conectar a la base de datos"
 * en config/database.php) se siguen manejando puntualmente ahí mismo;
 * este manejador es la red de contención para todo lo demás.
 */

function manejarExcepcion(Throwable $error): void
{
    error_log('[SGDM] Excepción no controlada: ' . $error->getMessage()
        . ' en ' . $error->getFile() . ':' . $error->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Ocurrió un error inesperado. Ya quedó registrado; probá de nuevo más tarde.';
    exit;
}

function manejarErrorPHP(int $nivel, string $mensaje, string $archivo, int $linea): bool
{
    error_log("[SGDM] Error PHP (nivel {$nivel}): {$mensaje} en {$archivo}:{$linea}");

    // Devolver true evita que además PHP muestre el error crudo en pantalla
    // (que en un servidor de producción nunca debería verse).
    return true;
}

set_exception_handler('manejarExcepcion');
set_error_handler('manejarErrorPHP');
