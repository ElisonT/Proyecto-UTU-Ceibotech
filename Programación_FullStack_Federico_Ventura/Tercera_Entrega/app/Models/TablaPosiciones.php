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

    public function __construct()
    {
        $this->conexion = obtenerConexion();
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
