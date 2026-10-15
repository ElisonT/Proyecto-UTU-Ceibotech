<?php
/**
 * app/Controllers/TorneoController.php
 *
 * Por ahora solo maneja la creación mínima de un torneo (nombre, deporte,
 * formato, fecha de inicio). El resto del formulario de crear-torneo.php
 * (premio, reglas, visibilidad, participantes) se conecta más adelante,
 * cuando se amplíe el modelo de datos con esas entidades.
 */

require_once __DIR__ . '/../Models/Torneo.php';
require_once __DIR__ . '/../Models/Auditoria.php';
require_once __DIR__ . '/../Models/Deporte.php';

class TorneoController
{
    private Torneo $modeloTorneo;
    private Auditoria $auditoria;
    private Deporte $modeloDeporte;

    public function __construct()
    {
        $this->modeloTorneo = new Torneo();
        $this->auditoria    = new Auditoria();
        $this->modeloDeporte = new Deporte();
    }

    /**
     * Procesa el formulario de "Crear torneo" (solo los campos mínimos).
     * @return array{0: string[], 1: int|null} [errores, idDelTorneoCreado]
     */
    public function procesarCreacion(array $datos, int $creadoPor): array
    {
        $errores = [];

        $nombre        = trim($datos['nombre'] ?? '');
        $deporteCodigo = $datos['deporte'] ?? '';
        $formato       = $datos['tipo'] ?? '';
        $fechaInicio   = trim($datos['fecha_inicio_torneo'] ?? '');

        if (strlen($nombre) < 3) {
            $errores[] = 'El nombre del torneo debe tener al menos 3 caracteres.';
        }
        // El deporte sale del catálogo (tabla deportes). Él define la modalidad
        // del torneo y el tamaño de los equipos: no se eligen a mano.
        $deporteFila = $this->modeloDeporte->obtenerPorCodigo((string) $deporteCodigo);
        if (!$deporteFila) {
            $errores[] = 'Seleccioná un deporte válido.';
        }
        if (!in_array($formato, ['liga', 'eliminacion', 'suizo'], true)) {
            $errores[] = 'Seleccioná un tipo de torneo válido.';
        }

        // --- Fechas: inscripciones (apertura y cierre), inicio y fin del torneo ---
        $esFecha = static fn (string $f): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1
            && checkdate((int) substr($f, 5, 2), (int) substr($f, 8, 2), (int) substr($f, 0, 4));
        $hoy       = (new DateTimeImmutable('now', new DateTimeZone('America/Montevideo')))->format('Y-m-d');
        $apertura  = trim($datos['fecha_inicio_inscripcion'] ?? '');
        $cierre    = trim($datos['fecha_cierre_inscripcion'] ?? '');
        $fechaFin  = trim($datos['fecha_fin_torneo'] ?? '');

        if (!$esFecha($apertura)) {
            $errores[] = 'Ingresá una fecha válida para el inicio de las inscripciones.';
        } elseif ($apertura < $hoy) {
            $errores[] = 'El inicio de las inscripciones no puede ser una fecha pasada.';
        }
        if (!$esFecha($cierre)) {
            $errores[] = 'Ingresá una fecha válida para el cierre de las inscripciones.';
        } elseif ($esFecha($apertura) && $cierre < $apertura) {
            $errores[] = 'El cierre de las inscripciones no puede ser anterior a su inicio.';
        }
        if (!$esFecha($fechaInicio)) {
            $errores[] = 'Ingresá una fecha de inicio válida.';
        } elseif ($esFecha($cierre) && $fechaInicio < $cierre) {
            $errores[] = 'El torneo no puede empezar antes del cierre de las inscripciones.';
        }
        if (!$esFecha($fechaFin)) {
            $errores[] = 'Ingresá una fecha válida para el fin del torneo.';
        } elseif ($esFecha($fechaInicio) && $fechaFin < $fechaInicio) {
            $errores[] = 'La fecha de fin del torneo no puede ser anterior a su inicio.';
        }

        $cupos = filter_var($datos['max_participantes'] ?? '', FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2, 'max_range' => 512]]);
        if ($cupos === false) {
            $errores[] = 'El máximo de participantes tiene que ser un número entre 2 y 512.';
        }

        // Nombre del deporte y tamaño de equipo: salen del catálogo, salvo "Otro" (libre),
        // donde el administrador escribe el nombre y cuántos jugadores lleva cada equipo.
        $deporte            = $deporteFila ? $deporteFila['nombre'] : '';
        $jugadoresPorEquipo = $deporteFila ? (int) $deporteFila['jugadores_por_equipo'] : 1;

        if ($deporteFila && (int) $deporteFila['es_libre'] === 1) {
            $deporte = trim(preg_replace('/\s+/', ' ', (string) ($datos['deporte_otro'] ?? '')));
            $largo   = mb_strlen($deporte);
            if ($largo < 3 || $largo > 60) {
                $errores[] = 'Escribí el nombre del deporte o juego (entre 3 y 60 caracteres).';
            } elseif ($this->modeloDeporte->existeNombre($deporte)) {
                $errores[] = "\"{$deporte}\" ya está en la lista de deportes: elegilo ahí en lugar de \"Otro\".";
            }

            $jugadoresPorEquipo = filter_var($datos['jugadores_otro'] ?? '', FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($jugadoresPorEquipo === false) {
                $errores[] = 'Indicá cuántos jugadores lleva cada equipo (1 si es individual, hasta 50).';
                $jugadoresPorEquipo = 1;
            }
        }

        // Más de 1 jugador por equipo => se juega por equipos; si no, individual.
        $modalidad = $jugadoresPorEquipo >= 2 ? 'equipo' : 'individual';

        if (!empty($errores)) {
            return [$errores, null];
        }

        $id = $this->modeloTorneo->crear($nombre, $deporte, $formato, $fechaInicio, $creadoPor, $modalidad, $cupos, $jugadoresPorEquipo, $apertura, $cierre, $fechaFin);

        if ($id === false) {
            return [['No se pudo crear el torneo. Probá de nuevo.'], null];
        }

        $this->auditoria->registrar($creadoPor, 'CREACION_TORNEO', 'torneos', $id, "Torneo '{$nombre}' ({$deporte}, {$formato})");

        return [[], $id];
    }

    /**
     * Asigna (o quita) el organizador de un torneo. $organizadorId puede ser
     * null para dejarlo "sin asignar" de nuevo.
     */
    public function procesarAsignacion(int $torneoId, ?int $organizadorId, int $actorId): bool
    {
        $exito = $this->modeloTorneo->asignarOrganizador($torneoId, $organizadorId);

        if ($exito) {
            $detalle = $organizadorId
                ? "Torneo #{$torneoId} asignado al organizador #{$organizadorId}"
                : "Torneo #{$torneoId} quedó sin organizador asignado";
            $this->auditoria->registrar($actorId, 'ASIGNACION_ORGANIZADOR', 'torneos', $torneoId, $detalle);
        }

        return $exito;
    }
}
