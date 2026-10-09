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
        if ($fechaInicio === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            $errores[] = 'Ingresá una fecha de inicio válida.';
        }

        $cupos = filter_var($datos['max_participantes'] ?? '', FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2, 'max_range' => 512]]);
        if ($cupos === false) {
            $errores[] = 'El máximo de participantes tiene que ser un número entre 2 y 512.';
        }

        // Más de 1 jugador por equipo => se juega por equipos; si no, individual.
        $modalidad = ($deporteFila && (int) $deporteFila['jugadores_por_equipo'] >= 2) ? 'equipo' : 'individual';

        if (!empty($errores)) {
            return [$errores, null];
        }

        $deporte = $deporteFila['nombre'];
        $id = $this->modeloTorneo->crear($nombre, $deporte, $formato, $fechaInicio, $creadoPor, $modalidad, $cupos);

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
