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

class TorneoController
{
    private Torneo $modeloTorneo;

    // Etiquetas legibles para los códigos que manda el <select> de deporte
    // en crear-torneo.php (ver ese archivo, sección "Info básica").
    private const ETIQUETAS_DEPORTE = [
        'futbol11'     => 'Fútbol 11',
        'futbol5'      => 'Fútbol 5',
        'basquetbol'   => 'Básquetbol',
        'tenis'        => 'Tenis',
        'padel'        => 'Pádel',
        'pingpong'     => 'Ping pong',
        'ajedrez'      => 'Ajedrez',
        'truco'        => 'Truco',
        'damas'        => 'Damas',
        'cs2'          => 'CS2',
        'lol'          => 'League of Legends',
        'fortnite'     => 'Fortnite',
        'valorant'     => 'Valorant',
        'rocketleague' => 'Rocket League',
        'otro'         => 'Otro',
    ];

    public function __construct()
    {
        $this->modeloTorneo = new Torneo();
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
        if (!isset(self::ETIQUETAS_DEPORTE[$deporteCodigo])) {
            $errores[] = 'Seleccioná un deporte válido.';
        }
        if (!in_array($formato, ['liga', 'eliminacion', 'suizo'], true)) {
            $errores[] = 'Seleccioná un tipo de torneo válido.';
        }
        if ($fechaInicio === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
            $errores[] = 'Ingresá una fecha de inicio válida.';
        }

        if (!empty($errores)) {
            return [$errores, null];
        }

        $deporte = self::ETIQUETAS_DEPORTE[$deporteCodigo];
        $id = $this->modeloTorneo->crear($nombre, $deporte, $formato, $fechaInicio, $creadoPor);

        if ($id === false) {
            return [['No se pudo crear el torneo. Probá de nuevo.'], null];
        }

        return [[], $id];
    }
}
