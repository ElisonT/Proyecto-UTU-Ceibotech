<?php
/**
 * app/Helpers/inscripcion_fechas.php
 *
 * Ventana de inscripción de un torneo (fecha de apertura y de cierre). La usan
 * el modelo Inscripcion (para rechazar inscripciones fuera de fecha) y las
 * páginas (para mostrar el estado). Las fechas se comparan como "Y-m-d" y "hoy"
 * es la fecha de Montevideo, no la del servidor.
 */

require_once __DIR__ . '/texto.php';

/**
 * @param array $torneo fila de torneos (con fecha_apertura_inscripcion y fecha_cierre_inscripcion)
 * @return array{estado: string, abre: ?string, cierra: ?string, mensaje: string}
 *   estado: 'abierta' (se puede inscribir hoy), 'no_abrio' o 'cerrada'
 */
function ventanaDeInscripcion(array $torneo, ?DateTimeImmutable $ahora = null): array
{
    $hoy      = ($ahora ?? new DateTimeImmutable('now', new DateTimeZone('America/Montevideo')))->format('Y-m-d');
    $apertura = $torneo['fecha_apertura_inscripcion'] ?? null;
    $cierre   = $torneo['fecha_cierre_inscripcion'] ?? null;

    if ($apertura !== null && $hoy < $apertura) {
        return [
            'estado'  => 'no_abrio', 'abre' => $apertura, 'cierra' => $cierre,
            'mensaje' => 'Las inscripciones abren el ' . formatearFecha($apertura) . '.',
        ];
    }
    if ($cierre !== null && $hoy > $cierre) {
        return [
            'estado'  => 'cerrada', 'abre' => $apertura, 'cierra' => $cierre,
            'mensaje' => 'Las inscripciones cerraron el ' . formatearFecha($cierre) . '.',
        ];
    }
    return [
        'estado'  => 'abierta', 'abre' => $apertura, 'cierra' => $cierre,
        'mensaje' => $cierre !== null ? 'Inscripciones abiertas hasta el ' . formatearFecha($cierre) . '.' : '',
    ];
}
