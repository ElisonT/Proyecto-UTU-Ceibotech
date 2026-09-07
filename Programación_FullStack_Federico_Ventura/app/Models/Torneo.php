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

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    public function contarJugados(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS total
               FROM inscripciones i
               JOIN torneos t ON t.id = i.torneo_id
              WHERE i.usuario_id = :usuario_id
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
              WHERE i.usuario_id = :usuario_id
                AND t.estado IN ('inscripciones_abiertas', 'en_curso')"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
    }

    public function contarGanados(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            'SELECT COUNT(*) AS total FROM torneos WHERE ganador_id = :usuario_id'
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
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
        $consulta = $this->conexion->prepare(
            'UPDATE torneos SET organizador_asignado_id = :organizador WHERE id = :torneo'
        );
        return $consulta->execute(['organizador' => $organizadorId, 'torneo' => $torneoId]);
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
              WHERE i.usuario_id = :usuario_id
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
    public function crear(string $nombre, string $deporte, string $formato, ?string $fechaInicio, int $creadoPor): int|false
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO torneos (nombre, deporte, formato, fecha_inicio, creado_por)
             VALUES (:nombre, :deporte, :formato, :fecha_inicio, :creado_por)'
        );

        $exito = $consulta->execute([
            'nombre'       => $nombre,
            'deporte'      => $deporte,
            'formato'      => $formato,
            'fecha_inicio' => $fechaInicio,
            'creado_por'   => $creadoPor,
        ]);

        return $exito ? (int) $this->conexion->lastInsertId() : false;
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
}
