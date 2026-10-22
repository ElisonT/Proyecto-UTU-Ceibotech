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

    /**
     * Tabla completa de un torneo, ya ordenada. Criterios de desempate, en este orden:
     *   1. Puntos.
     *   2. Enfrentamiento directo: entre los que empatan en puntos, se arma una mini-tabla solo con los
     *      partidos que jugaron entre ellos (puntos y, si siguen igualados, diferencia de tantos).
     *   3. Diferencia de tantos general (a favor menos en contra).
     *   4. Tantos a favor.
     *   5. Partidos ganados.
     *   6. Nombre (orden alfabético), para que el orden sea siempre el mismo.
     * Cada fila trae además 'favor', 'contra' y 'diferencia', calculados de los resultados guardados.
     */
    public function listarPorTorneo(int $torneoId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT tp.*,
                    COALESCE(u.nombre_completo, eq.nombre_equipo) AS nombre
               FROM tabla_posiciones tp
               JOIN inscripciones i ON i.id = tp.inscripcion_id
          LEFT JOIN usuarios u  ON u.id  = i.usuario_id
          LEFT JOIN equipos  eq ON eq.id = i.equipo_id
              WHERE i.torneo_id = :torneo_id"
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        $filas = $consulta->fetchAll();
        if (count($filas) < 2) {
            foreach ($filas as &$f) {
                $f['favor'] = $f['contra'] = $f['diferencia'] = 0;
            }
            return $filas;
        }

        // Partidos jugados entre dos inscripciones (un bye no tiene rival, así que no suma tantos).
        $partidos = $this->conexion->prepare(
            'SELECT e.inscripcion_local_id AS l, e.inscripcion_visitante_id AS v,
                    r.puntaje_local AS pl, r.puntaje_visitante AS pv
               FROM enfrentamientos e
               JOIN rondas ro ON ro.id = e.ronda_id
               JOIN resultados r ON r.enfrentamiento_id = e.id AND r.validado = 1
              WHERE ro.torneo_id = :t AND e.inscripcion_visitante_id IS NOT NULL'
        );
        $partidos->execute(['t' => $torneoId]);
        $partidos = $partidos->fetchAll();

        return $this->ordenar($filas, $partidos);
    }

    /** Aplica los criterios de desempate (ver listarPorTorneo) sobre filas y partidos ya leídos. */
    private function ordenar(array $filas, array $partidos): array
    {
        $favor = $contra = [];
        foreach ($partidos as $p) {
            $l = (int) $p['l'];
            $v = (int) $p['v'];
            $favor[$l]  = ($favor[$l]  ?? 0) + (int) $p['pl'];
            $contra[$l] = ($contra[$l] ?? 0) + (int) $p['pv'];
            $favor[$v]  = ($favor[$v]  ?? 0) + (int) $p['pv'];
            $contra[$v] = ($contra[$v] ?? 0) + (int) $p['pl'];
        }
        foreach ($filas as &$f) {
            $id = (int) $f['inscripcion_id'];
            $f['favor']      = $favor[$id]  ?? 0;
            $f['contra']     = $contra[$id] ?? 0;
            $f['diferencia'] = $f['favor'] - $f['contra'];
        }
        unset($f);

        // Primero por puntos; después se desempata cada grupo con los mismos puntos.
        usort($filas, static fn (array $a, array $b): int => (int) $b['puntos'] <=> (int) $a['puntos']);

        $resultado = [];
        for ($i = 0, $n = count($filas); $i < $n;) {
            $j = $i;
            while ($j < $n && (int) $filas[$j]['puntos'] === (int) $filas[$i]['puntos']) {
                $j++;
            }
            $grupo = array_slice($filas, $i, $j - $i);
            if (count($grupo) > 1) {
                $grupo = $this->desempatarGrupo($grupo, $partidos);
            }
            array_push($resultado, ...$grupo);
            $i = $j;
        }
        return $resultado;
    }

    /** Nombre en minúsculas y sin tildes, para ordenar alfabéticamente como lo haría una persona (Álamo antes que Zeta). */
    private static function claveDeOrden(string $nombre): string
    {
        return strtr(mb_strtolower($nombre), [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    /** Ordena un grupo empatado en puntos: enfrentamiento directo, diferencia, tantos a favor, ganados, nombre. */
    private function desempatarGrupo(array $grupo, array $partidos): array
    {
        $ids = array_map(static fn (array $f): int => (int) $f['inscripcion_id'], $grupo);

        // Mini-tabla con los partidos jugados solo entre los empatados.
        $miniPuntos = $miniDif = array_fill_keys($ids, 0);
        foreach ($partidos as $p) {
            $l = (int) $p['l'];
            $v = (int) $p['v'];
            if (!in_array($l, $ids, true) || !in_array($v, $ids, true)) {
                continue;
            }
            $pl = (int) $p['pl'];
            $pv = (int) $p['pv'];
            $miniDif[$l] += $pl - $pv;
            $miniDif[$v] += $pv - $pl;
            if ($pl > $pv) {
                $miniPuntos[$l] += 3;
            } elseif ($pv > $pl) {
                $miniPuntos[$v] += 3;
            } else {
                $miniPuntos[$l] += 1;
                $miniPuntos[$v] += 1;
            }
        }

        usort($grupo, static function (array $a, array $b) use ($miniPuntos, $miniDif): int {
            $ia = (int) $a['inscripcion_id'];
            $ib = (int) $b['inscripcion_id'];
            return [$miniPuntos[$ib], $miniDif[$ib], $b['diferencia'], $b['favor'], (int) $b['partidos_ganados']]
                <=> [$miniPuntos[$ia], $miniDif[$ia], $a['diferencia'], $a['favor'], (int) $a['partidos_ganados']]
                ?: strnatcmp(self::claveDeOrden((string) $a['nombre']), self::claveDeOrden((string) $b['nombre']));
        });
        return $grupo;
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
     * Solo cuentan los resultados validados. Un bye cuenta como victoria solo en sistema suizo (en eliminación no hay tabla).
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
               JOIN resultados r ON r.enfrentamiento_id = e.id AND r.validado = 1
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
