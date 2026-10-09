<?php
/**
 * app/Models/Enfrentamiento.php
 *
 * Acceso a datos de rondas y enfrentamientos. La generación automática
 * de enfrentamientos según el formato (liga, eliminación, suizo) vive
 * en app/Controllers/CompetenciaController.php; este modelo solo sabe
 * leer y escribir filas, no decide el algoritmo de cada formato.
 */

require_once __DIR__ . '/../../config/database.php';

class Enfrentamiento
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    /** Ids de las inscripciones activas de un torneo (equipo o individual, no importa cuál). */
    public function listarInscripcionesActivas(int $torneoId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT id FROM inscripciones WHERE torneo_id = :torneo_id AND estado_inscripcion = 'activa'"
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        return array_column($consulta->fetchAll(), 'id');
    }

    public function crearRonda(int $torneoId, int $numero, ?string $nombre = null): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO rondas (torneo_id, numero, nombre) VALUES (:torneo_id, :numero, :nombre)'
        );
        $consulta->execute(['torneo_id' => $torneoId, 'numero' => $numero, 'nombre' => $nombre]);
        return (int) $this->conexion->lastInsertId();
    }

    public function crearEnfrentamiento(int $rondaId, int $inscripcionLocalId, int $inscripcionVisitanteId): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO enfrentamientos (ronda_id, inscripcion_local_id, inscripcion_visitante_id)
             VALUES (:ronda_id, :local, :visitante)'
        );
        $consulta->execute([
            'ronda_id'   => $rondaId,
            'local'      => $inscripcionLocalId,
            'visitante'  => $inscripcionVisitanteId,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    /**
     * Crea un enfrentamiento "bye": el local pasa de ronda sin jugar,
     * porque no le tocó rival (cantidad de inscriptos no es potencia de 2).
     * Queda marcado como jugado, con un resultado automático a su favor.
     */
    public function crearBye(int $rondaId, int $inscripcionLocalId): int
    {
        $consulta = $this->conexion->prepare(
            "INSERT INTO enfrentamientos (ronda_id, inscripcion_local_id, inscripcion_visitante_id, estado)
             VALUES (:ronda_id, :local, NULL, 'jugado')"
        );
        $consulta->execute(['ronda_id' => $rondaId, 'local' => $inscripcionLocalId]);
        $idEnfrentamiento = (int) $this->conexion->lastInsertId();

        $consultaResultado = $this->conexion->prepare(
            'INSERT INTO resultados (enfrentamiento_id, inscripcion_ganadora_id, validado)
             VALUES (:enfrentamiento_id, :ganador, 1)'
        );
        $consultaResultado->execute(['enfrentamiento_id' => $idEnfrentamiento, 'ganador' => $inscripcionLocalId]);

        return $idEnfrentamiento;
    }

    /** El número de la última ronda cargada de un torneo (0 si no hay ninguna). */
    public function obtenerUltimaRonda(int $torneoId): int
    {
        $consulta = $this->conexion->prepare(
            'SELECT MAX(numero) AS ultima FROM rondas WHERE torneo_id = :torneo_id'
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        return (int) ($consulta->fetch()['ultima'] ?? 0);
    }

    public function obtenerRondaPorNumero(int $torneoId, int $numero): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT * FROM rondas WHERE torneo_id = :torneo_id AND numero = :numero'
        );
        $consulta->execute(['torneo_id' => $torneoId, 'numero' => $numero]);
        return $consulta->fetch();
    }

    /**
     * Ganadores de todos los enfrentamientos de una ronda, en el mismo
     * orden en que se crearon (indispensable para que el cuadro de
     * eliminación directa arme bien los cruces de la ronda siguiente).
     * Si algún enfrentamiento todavía no tiene resultado validado,
     * devuelve null (todavía no se puede avanzar de ronda).
     */
    public function obtenerGanadoresDeRonda(int $rondaId): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT e.id AS enfrentamiento_id, r.inscripcion_ganadora_id
               FROM enfrentamientos e
          LEFT JOIN resultados r ON r.enfrentamiento_id = e.id AND r.validado = 1
              WHERE e.ronda_id = :ronda_id
              ORDER BY e.id ASC'
        );
        $consulta->execute(['ronda_id' => $rondaId]);
        $filas = $consulta->fetchAll();

        $ganadores = [];
        foreach ($filas as $fila) {
            if ($fila['inscripcion_ganadora_id'] === null) {
                return null; // todavía falta cargar algún resultado
            }
            $ganadores[] = (int) $fila['inscripcion_ganadora_id'];
        }

        return $ganadores;
    }

    /** Cuántas rondas ya existen para un torneo (para no pisar una generación anterior). */
    public function contarRondas(int $torneoId): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) AS total FROM rondas WHERE torneo_id = :torneo_id');
        $consulta->execute(['torneo_id' => $torneoId]);
        return (int) $consulta->fetch()['total'];
    }

    /** Todas las rondas de un torneo, con sus enfrentamientos y el nombre de cada inscripto. */
    public function listarRondasConEnfrentamientos(int $torneoId): array
    {
        $rondas = $this->conexion->prepare(
            'SELECT id, numero, nombre, fecha FROM rondas WHERE torneo_id = :torneo_id ORDER BY numero'
        );
        $rondas->execute(['torneo_id' => $torneoId]);
        $listaRondas = $rondas->fetchAll();

        foreach ($listaRondas as &$ronda) {
            $consulta = $this->conexion->prepare(
                "SELECT e.*,
                        COALESCE(ul.nombre_completo, eql.nombre_equipo) AS nombre_local,
                        COALESCE(uv.nombre_completo, eqv.nombre_equipo) AS nombre_visitante,
                        res.puntaje_local, res.puntaje_visitante,
                        (res.id IS NOT NULL) AS tiene_resultado
                   FROM enfrentamientos e
              LEFT JOIN resultados res ON res.enfrentamiento_id = e.id
                   JOIN inscripciones il ON il.id = e.inscripcion_local_id
                   LEFT JOIN inscripciones iv ON iv.id = e.inscripcion_visitante_id
              LEFT JOIN usuarios ul ON ul.id = il.usuario_id
              LEFT JOIN equipos  eql ON eql.id = il.equipo_id
              LEFT JOIN usuarios uv ON uv.id = iv.usuario_id
              LEFT JOIN equipos  eqv ON eqv.id = iv.equipo_id
                  WHERE e.ronda_id = :ronda_id
               ORDER BY e.id"
            );
            $consulta->execute(['ronda_id' => $ronda['id']]);
            $ronda['enfrentamientos'] = $consulta->fetchAll();
        }

        return $listaRondas;
    }

    /** Cruces ya jugados o armados en el torneo (visitante NULL = bye). */
    public function listarCrucesPrevios(int $torneoId): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT e.inscripcion_local_id AS local_id, e.inscripcion_visitante_id AS visitante_id
               FROM enfrentamientos e
               JOIN rondas r ON r.id = e.ronda_id
              WHERE r.torneo_id = :torneo_id'
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        return $consulta->fetchAll();
    }

    /** true si todos los enfrentamientos de la ronda tienen resultado validado (empates incluidos). */
    public function rondaCompleta(int $rondaId): bool
    {
        $consulta = $this->conexion->prepare(
            'SELECT COUNT(*) AS pendientes
               FROM enfrentamientos e
          LEFT JOIN resultados r ON r.enfrentamiento_id = e.id AND r.validado = 1
              WHERE e.ronda_id = :ronda_id AND r.id IS NULL'
        );
        $consulta->execute(['ronda_id' => $rondaId]);
        return (int) $consulta->fetch()['pendientes'] === 0;
    }

    /** Un enfrentamiento junto con el formato de su torneo y si ya tiene resultado. */
    public function obtenerConFormato(int $enfrentamientoId): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT e.*, t.formato, t.id AS torneo_id,
                    (SELECT COUNT(*) FROM resultados res WHERE res.enfrentamiento_id = e.id) AS tiene_resultado
               FROM enfrentamientos e
               JOIN rondas r ON r.id = e.ronda_id
               JOIN torneos t ON t.id = r.torneo_id
              WHERE e.id = :id'
        );
        $consulta->execute(['id' => $enfrentamientoId]);
        return $consulta->fetch();
    }

    /** Guarda el resultado validado y marca el enfrentamiento como jugado. */
    public function guardarResultado(int $enfrentamientoId, int $puntajeLocal, int $puntajeVisitante, ?int $ganadorId): void
    {
        $insertar = $this->conexion->prepare(
            'INSERT INTO resultados (enfrentamiento_id, puntaje_local, puntaje_visitante, inscripcion_ganadora_id, validado)
             VALUES (:enf, :pl, :pv, :ganador, 1)'
        );
        $insertar->execute([
            'enf' => $enfrentamientoId, 'pl' => $puntajeLocal,
            'pv' => $puntajeVisitante, 'ganador' => $ganadorId,
        ]);

        $this->conexion->prepare("UPDATE enfrentamientos SET estado = 'jugado' WHERE id = :id")
                       ->execute(['id' => $enfrentamientoId]);
    }

    /** Cuántos enfrentamientos (sin contar byes) del torneo todavía no tienen resultado validado. */
    public function contarPendientesTorneo(int $torneoId): int
    {
        $consulta = $this->conexion->prepare(
            'SELECT COUNT(*) AS pendientes
               FROM enfrentamientos e
               JOIN rondas ro ON ro.id = e.ronda_id
          LEFT JOIN resultados r ON r.enfrentamiento_id = e.id AND r.validado = 1
              WHERE ro.torneo_id = :torneo_id
                AND e.inscripcion_visitante_id IS NOT NULL
                AND r.id IS NULL'
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        return (int) $consulta->fetch()['pendientes'];
    }
}
