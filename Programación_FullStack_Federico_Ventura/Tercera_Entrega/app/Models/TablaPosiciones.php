<?php
/**
 * app/Models/TablaPosiciones.php
 *
 * Acceso a la tabla de posiciones, que según el DER se guarda como
 * tabla propia (1 a 1 con cada inscripción) en vez de calcularse al
 * vuelo en cada consulta. Se actualiza cada vez que se valida un
 * resultado (ver CompetenciaController).
 */

require_once __DIR__ . '/../../config/database.php';

class TablaPosiciones
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        // Se puede compartir una conexión para trabajar dentro de una misma transacción.
        $this->conexion = $conexion ?? obtenerConexion();
    }

    /** Crea la fila en cero para una inscripción, si todavía no existe. */
    public function inicializar(int $inscripcionId): void
    {
        $consulta = $this->conexion->prepare(
            'INSERT IGNORE INTO tabla_posiciones (inscripcion_id) VALUES (:id)'
        );
        $consulta->execute(['id' => $inscripcionId]);
    }

    /** Tabla completa de un torneo, ordenada por puntos (y diferencia de partidos ganados como desempate simple). */
    public function listarPorTorneo(int $torneoId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT tp.*,
                    COALESCE(u.nombre_completo, eq.nombre_equipo) AS nombre
               FROM tabla_posiciones tp
               JOIN inscripciones i ON i.id = tp.inscripcion_id
          LEFT JOIN usuarios u  ON u.id  = i.usuario_id
          LEFT JOIN equipos  eq ON eq.id = i.equipo_id
              WHERE i.torneo_id = :torneo_id
              ORDER BY tp.puntos DESC, tp.partidos_ganados DESC, nombre ASC"
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        return $consulta->fetchAll();
    }

    /** Suma un resultado a la fila de una inscripción: 'ganado', 'empatado' o 'perdido'. */
    public function registrarResultado(int $inscripcionId, string $resultado): void
    {
        $puntos  = ['ganado' => 3, 'empatado' => 1, 'perdido' => 0];
        $columna = [
            'ganado'   => 'partidos_ganados',
            'empatado' => 'partidos_empatados',
            'perdido'  => 'partidos_perdidos',
        ];
        if (!isset($puntos[$resultado])) {
            return;
        }

        // $col sale de la lista cerrada de arriba, nunca del usuario.
        $col = $columna[$resultado];
        $consulta = $this->conexion->prepare(
            "UPDATE tabla_posiciones
                SET puntos = puntos + :puntos,
                    partidos_jugados = partidos_jugados + 1,
                    {$col} = {$col} + 1
              WHERE inscripcion_id = :id"
        );
        $consulta->execute(['puntos' => $puntos[$resultado], 'id' => $inscripcionId]);
    }

    /**
     * Reconstruye TODA la tabla del torneo a partir de los resultados guardados.
     * Es lo que se usa al cargar y al corregir un resultado: en vez de sumar y
     * restar a mano (y arriesgar que la tabla quede desfasada), se vuelve a
     * contar todo, así la tabla siempre coincide con los resultados.
     * Un bye cuenta como victoria solo en sistema suizo (en eliminación no hay tabla).
     */
    public function recalcularDesdeResultados(int $torneoId, string $formato): void
    {
        $this->conexion->prepare(
            'UPDATE tabla_posiciones tp JOIN inscripciones i ON i.id = tp.inscripcion_id
                SET tp.puntos = 0, tp.partidos_jugados = 0, tp.partidos_ganados = 0,
                    tp.partidos_empatados = 0, tp.partidos_perdidos = 0
              WHERE i.torneo_id = :t'
        )->execute(['t' => $torneoId]);

        $consulta = $this->conexion->prepare(
            'SELECT e.inscripcion_local_id AS local_id, e.inscripcion_visitante_id AS visitante_id,
                    r.inscripcion_ganadora_id AS ganador_id
               FROM enfrentamientos e
               JOIN rondas ro ON ro.id = e.ronda_id
               JOIN resultados r ON r.enfrentamiento_id = e.id
              WHERE ro.torneo_id = :t'
        );
        $consulta->execute(['t' => $torneoId]);

        foreach ($consulta->fetchAll() as $fila) {
            $local = (int) $fila['local_id'];
            if ($fila['visitante_id'] === null) {
                if ($formato === 'suizo') {
                    $this->registrarResultado($local, 'ganado');
                }
                continue;
            }
            $visitante = (int) $fila['visitante_id'];
            if ($fila['ganador_id'] === null) {
                $this->registrarResultado($local, 'empatado');
                $this->registrarResultado($visitante, 'empatado');
            } else {
                $ganador = (int) $fila['ganador_id'];
                $this->registrarResultado($ganador, 'ganado');
                $this->registrarResultado($ganador === $local ? $visitante : $local, 'perdido');
            }
        }

        $this->recalcularPosiciones($torneoId);
    }

    /** Guarda el número de posición (1, 2, 3...) según el orden de listarPorTorneo(). */
    public function recalcularPosiciones(int $torneoId): void
    {
        $actualizar = $this->conexion->prepare(
            'UPDATE tabla_posiciones SET posicion = :posicion WHERE inscripcion_id = :id'
        );
        foreach ($this->listarPorTorneo($torneoId) as $indice => $fila) {
            $actualizar->execute(['posicion' => $indice + 1, 'id' => $fila['inscripcion_id']]);
        }
    }
}
