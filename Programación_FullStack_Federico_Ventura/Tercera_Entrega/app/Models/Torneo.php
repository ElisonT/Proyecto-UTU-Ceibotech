<?php
/**
 * app/Models/Torneo.php
 *
 * Modelo mínimo de la entidad Torneo. Se irá ampliando en las
 * próximas entregas con equipos, rondas, enfrentamientos y
 * resultados; por ahora alcanza para mostrar datos reales en
 * el perfil del usuario.
 */

require_once __DIR__ . '/../../config/database.php';

class Torneo
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        // Se puede compartir una conexión para trabajar dentro de una misma transacción.
        $this->conexion = $conexion ?? obtenerConexion();
    }

    public function contarJugados(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS total
               FROM inscripciones i
               JOIN torneos t ON t.id = i.torneo_id
          LEFT JOIN equipo_miembros em ON em.equipo_id = i.equipo_id
              WHERE (i.usuario_id = :usuario_id OR em.usuario_id = :usuario_id)
                AND i.estado_inscripcion = 'activa'
                AND t.estado = 'finalizado'"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
    }

    public function contarActivos(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS total
               FROM inscripciones i
               JOIN torneos t ON t.id = i.torneo_id
          LEFT JOIN equipo_miembros em ON em.equipo_id = i.equipo_id
              WHERE (i.usuario_id = :usuario_id OR em.usuario_id = :usuario_id)
                AND i.estado_inscripcion = 'activa'
                AND t.estado IN ('inscripciones_abiertas', 'en_curso')"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
    }

    /** Cuenta victorias propias (individual) y como integrante de un equipo ganador. */
    public function contarGanados(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(DISTINCT t.id) AS total
               FROM torneos t
               JOIN inscripciones i ON i.id = t.ganador_inscripcion_id
          LEFT JOIN equipo_miembros em ON em.equipo_id = i.equipo_id
              WHERE i.usuario_id = :usuario_id OR em.usuario_id = :usuario_id"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
    }

    /** Marca el torneo como finalizado y registra al ganador (una inscripción: equipo o individual). */
    public function finalizar(int $torneoId, int $ganadorInscripcionId): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE torneos SET estado = 'finalizado', ganador_inscripcion_id = :ganador WHERE id = :id"
        );
        return $consulta->execute(['ganador' => $ganadorInscripcionId, 'id' => $torneoId]);
    }

    /** Se usa para bloquear la eliminación de cuenta: no tiene sentido borrar
     *  a alguien que organiza torneos en los que otras personas participan. */
    public function contarCreados(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            'SELECT COUNT(*) AS total FROM torneos WHERE creado_por = :usuario_id'
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
    }

    /**
     * El Administrador general crea el torneo; después se lo puede ASIGNAR
     * a un Organizador para que lo gestione (ver letra, puntos 5.1 y 5.2).
     * $organizadorId puede ser null para "sin asignar todavía".
     */
    public function asignarOrganizador(int $torneoId, ?int $organizadorId): bool
    {
        // La autorización para corregir resultados es de la persona asignada: si cambia el
        // organizador (o se lo quita), vuelve a 0. Va ANTES que la otra columna porque MySQL y
        // MariaDB evalúan el SET de izquierda a derecha y necesitan comparar con el valor viejo.
        $consulta = $this->conexion->prepare(
            'UPDATE torneos
                SET correccion_autorizada   = IF(organizador_asignado_id <=> :nuevo_a, correccion_autorizada, 0),
                    organizador_asignado_id = :nuevo_b
              WHERE id = :torneo'
        );
        return $consulta->execute(['nuevo_a' => $organizadorId, 'nuevo_b' => $organizadorId, 'torneo' => $torneoId]);
    }

    /** Actualiza los datos editables de un torneo (nombre, cupos y fechas). */
    public function actualizarDatos(int $torneoId, array $d): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE torneos
                SET nombre = :nombre, cupos = :cupos, fecha_apertura_inscripcion = :apertura,
                    fecha_cierre_inscripcion = :cierre, fecha_inicio = :inicio, fecha_fin = :fin,
                    tipo_premio = :tipo_premio, monto_premio = :monto_premio, desc_premio = :desc_premio,
                    reglas_texto = :reglas_texto, visibilidad = :visibilidad
              WHERE id = :id AND estado IN (\'inscripciones_abiertas\', \'en_curso\')'
        );
        return $consulta->execute([
            'nombre' => $d['nombre'], 'cupos' => $d['cupos'], 'apertura' => $d['apertura'],
            'cierre' => $d['cierre'], 'inicio' => $d['inicio'], 'fin' => $d['fin'],
            'tipo_premio' => $d['tipo_premio'], 'monto_premio' => $d['monto_premio'], 'desc_premio' => $d['desc_premio'],
            'reglas_texto' => $d['reglas_texto'], 'visibilidad' => $d['visibilidad'], 'id' => $torneoId,
        ]);
    }

    /** Pasa a 'cancelado' un torneo que todavía no terminó. Devuelve false si no estaba en un estado cancelable. */
    public function cancelar(int $torneoId): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE torneos SET estado = 'cancelado' WHERE id = :id AND estado IN ('inscripciones_abiertas', 'en_curso')"
        );
        $consulta->execute(['id' => $torneoId]);
        return $consulta->rowCount() > 0;
    }

    /** Habilita (o quita) que el organizador asignado pueda corregir resultados de este torneo. */
    public function establecerAutorizacionCorreccion(int $torneoId, bool $autorizada): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE torneos SET correccion_autorizada = :valor WHERE id = :id'
        );
        return $consulta->execute(['valor' => $autorizada ? 1 : 0, 'id' => $torneoId]);
    }

    /** Lista todos los torneos con el nombre de quién lo creó y quién lo gestiona (para el admin). */
    public function listarTodos(): array
    {
        $consulta = $this->conexion->query(
            "SELECT t.*,
                    creador.nombre_completo     AS nombre_creador,
                    organizador.nombre_completo AS nombre_organizador
               FROM torneos t
               JOIN usuarios creador      ON creador.id = t.creado_por
          LEFT JOIN usuarios organizador  ON organizador.id = t.organizador_asignado_id
              ORDER BY t.fecha_creacion DESC"
        );
        return $consulta->fetchAll();
    }

    /**
     * Torneos en los que participa un usuario (para el carrusel del perfil).
     * Incluye la cantidad de inscriptos de cada torneo con una subconsulta.
     */
    public function listarParticipando(int $usuarioId, int $limite = 6): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM inscripciones i2 WHERE i2.torneo_id = t.id) AS cantidad_participantes
               FROM torneos t
               JOIN inscripciones i ON i.torneo_id = t.id
          LEFT JOIN equipo_miembros em ON em.equipo_id = i.equipo_id
              WHERE (i.usuario_id = :usuario_id OR em.usuario_id = :usuario_id)
                AND i.estado_inscripcion = 'activa'
              ORDER BY t.fecha_inicio DESC
              LIMIT :limite"
        );
        $consulta->bindValue('usuario_id', $usuarioId, PDO::PARAM_INT);
        $consulta->bindValue('limite', $limite, PDO::PARAM_INT);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    /**
     * Crea un torneo nuevo. Devuelve el id del torneo creado, o false si falló.
     * Solo guarda los campos mínimos (nombre, deporte, formato, fecha de
     * inicio); el resto del formulario (premio, reglas, visibilidad, etc.)
     * se conecta más adelante cuando se amplíe el modelo de datos.
     */
    public function crear(
        string $nombre,
        string $deporte,
        string $formato,
        ?string $fechaInicio,
        int $creadoPor,
        string $modalidad = 'equipo',
        ?int $cupos = null,
        ?int $jugadoresPorEquipo = null,
        ?string $aperturaInscripcion = null,
        ?string $cierreInscripcion = null,
        ?string $fechaFin = null,
        array $extras = []
    ): int|false
    {
        // $extras trae premio, reglas y visibilidad (ver TorneoController::validarExtras); el PDF de reglas,
        // si hay, va en $extras['pdf']. Todo se guarda junto o no se guarda nada.
        try {
            $this->conexion->beginTransaction();

            $consulta = $this->conexion->prepare(
                'INSERT INTO torneos (nombre, deporte, formato, fecha_inicio, creado_por, modalidad, cupos, jugadores_por_equipo,
                                     fecha_apertura_inscripcion, fecha_cierre_inscripcion, fecha_fin,
                                     tipo_premio, monto_premio, desc_premio, reglas_texto, visibilidad)
                 VALUES (:nombre, :deporte, :formato, :fecha_inicio, :creado_por, :modalidad, :cupos, :jugadores,
                         :apertura, :cierre, :fin, :tipo_premio, :monto_premio, :desc_premio, :reglas_texto, :visibilidad)'
            );
            $consulta->execute([
                'nombre'       => $nombre,
                'deporte'      => $deporte,
                'formato'      => $formato,
                'fecha_inicio' => $fechaInicio,
                'creado_por'   => $creadoPor,
                'modalidad'    => $modalidad,
                'cupos'        => $cupos,
                'jugadores'    => $jugadoresPorEquipo,
                'apertura'     => $aperturaInscripcion,
                'cierre'       => $cierreInscripcion,
                'fin'          => $fechaFin,
                'tipo_premio'  => $extras['tipo_premio']  ?? 'ninguno',
                'monto_premio' => $extras['monto_premio'] ?? null,
                'desc_premio'  => $extras['desc_premio']  ?? null,
                'reglas_texto' => $extras['reglas_texto'] ?? null,
                'visibilidad'  => $extras['visibilidad']  ?? 'publico',
            ]);
            $id = (int) $this->conexion->lastInsertId();

            if (!empty($extras['pdf'])) {
                $this->guardarReglasPdfSinTransaccion($id, $extras['pdf']);
            }
            $this->conexion->commit();
            return $id;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            error_log('No se pudo crear el torneo: ' . $error->getMessage());
            return false;
        }
    }

    /** @param array{nombre: string, tamano: int, contenido: string} $pdf */
    private function guardarReglasPdfSinTransaccion(int $torneoId, array $pdf): void
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO torneo_reglas_pdf (torneo_id, nombre_archivo, tamano, contenido)
             VALUES (:id, :nombre, :tamano, :contenido)'
        );
        $consulta->bindValue('id', $torneoId, PDO::PARAM_INT);
        $consulta->bindValue('nombre', $pdf['nombre']);
        $consulta->bindValue('tamano', $pdf['tamano'], PDO::PARAM_INT);
        $consulta->bindValue('contenido', $pdf['contenido'], PDO::PARAM_LOB);
        $consulta->execute();
    }

    /** PDF de reglas de un torneo, o false si no tiene. */
    public function obtenerReglasPdf(int $torneoId): array|false
    {
        $consulta = $this->conexion->prepare('SELECT nombre_archivo, tamano, contenido FROM torneo_reglas_pdf WHERE torneo_id = :id');
        $consulta->execute(['id' => $torneoId]);
        return $consulta->fetch();
    }

    /** Torneos que un Organizador tiene asignados para gestionar (ver letra 5.2). */
    public function listarAsignados(int $organizadorId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM inscripciones i2 WHERE i2.torneo_id = t.id) AS cantidad_participantes
               FROM torneos t
              WHERE t.organizador_asignado_id = :organizador_id
              ORDER BY t.fecha_inicio ASC"
        );
        $consulta->execute(['organizador_id' => $organizadorId]);
        return $consulta->fetchAll();
    }

    /** Un torneo con quién lo creó, quién lo gestiona y cuántos inscriptos activos tiene. */
    public function obtenerPorId(int $id): array|false
    {
        $consulta = $this->conexion->prepare(
            "SELECT t.*,
                    creador.nombre_completo     AS nombre_creador,
                    organizador.nombre_completo AS nombre_organizador,
                    (SELECT COUNT(*) FROM inscripciones i
                      WHERE i.torneo_id = t.id AND i.estado_inscripcion = 'activa') AS cantidad_participantes,
                    (SELECT COUNT(*) FROM torneo_reglas_pdf p WHERE p.torneo_id = t.id) AS tiene_reglas_pdf
               FROM torneos t
               JOIN usuarios creador          ON creador.id = t.creado_por
          LEFT JOIN usuarios organizador      ON organizador.id = t.organizador_asignado_id
              WHERE t.id = :id"
        );
        $consulta->execute(['id' => $id]);
        return $consulta->fetch();
    }

    /** Todos los torneos para el buscador, con cantidad de inscriptos activos. */
    public function listarParaBusqueda(): array
    {
        $consulta = $this->conexion->query(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM inscripciones i
                      WHERE i.torneo_id = t.id AND i.estado_inscripcion = 'activa') AS cantidad_participantes
               FROM torneos t
              WHERE t.visibilidad = 'publico'      -- los privados solo se alcanzan con el link directo
              ORDER BY t.fecha_creacion DESC"
        );
        return $consulta->fetchAll();
    }

    /** Inscriptos activos de un torneo (nombre del equipo o del participante individual). */
    public function listarInscriptos(int $torneoId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT i.id AS inscripcion_id,
                    COALESCE(u.nombre_completo, eq.nombre_equipo) AS nombre
               FROM inscripciones i
          LEFT JOIN usuarios u  ON u.id  = i.usuario_id
          LEFT JOIN equipos  eq ON eq.id = i.equipo_id
              WHERE i.torneo_id = :torneo_id AND i.estado_inscripcion = 'activa'
              ORDER BY nombre ASC"
        );
        $consulta->execute(['torneo_id' => $torneoId]);
        return $consulta->fetchAll();
    }

    /** Pasa el torneo de "inscripciones abiertas" a "en curso" (al generar el fixture). */
    public function iniciar(int $torneoId): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE torneos SET estado = 'en_curso' WHERE id = :id AND estado = 'inscripciones_abiertas'"
        );
        return $consulta->execute(['id' => $torneoId]);
    }
}
