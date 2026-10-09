<?php
/**
 * app/Models/Equipo.php
 *
 * Equipos e invitaciones. Un equipo existe por sí mismo (no nace dentro de
 * un torneo): lo crea un participante PARA un deporte, queda como capitán, y
 * el capitán invita a otros por nombre de usuario. El invitado tiene que
 * aceptar. El tamaño del equipo es el estándar del deporte (tabla deportes):
 * no se puede invitar a más gente que eso.
 *
 * Mientras el equipo esté inscripto en un torneo que no terminó (con
 * inscripciones abiertas o en curso) la formación queda BLOQUEADA: no se
 * puede invitar, aceptar, sacar ni salir, para que el plantel con el que se
 * inscribió no cambie. Para modificarlo hay que retirar al equipo del
 * torneo antes (mientras siga abierto).
 *
 * Las escrituras van en una transacción que bloquea la fila del equipo
 * (SELECT ... FOR UPDATE). Las reglas incumplidas se informan con
 * DomainException (mensaje apto para el usuario); las atrapa EquipoController.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Helpers/roles.php';

class Equipo
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    // ---------------------------------------------------------
    // Consultas (solo lectura)
    // ---------------------------------------------------------

    /** Equipos de los que el usuario es integrante, con cantidad de integrantes y si está bloqueado. */
    public function listarDeUsuario(int $usuarioId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT eq.id, eq.nombre_equipo, eq.capitan_id,
                    d.nombre AS deporte, d.jugadores_por_equipo AS tamano,
                    cap.nombre_completo AS nombre_capitan,
                    (eq.capitan_id = :usuario_id) AS es_capitan,
                    (SELECT COUNT(*) FROM equipo_miembros m WHERE m.equipo_id = eq.id) AS miembros,
                    (SELECT t.nombre
                       FROM inscripciones i
                       JOIN torneos t ON t.id = i.torneo_id
                      WHERE i.equipo_id = eq.id
                        AND i.estado_inscripcion = 'activa'
                        AND t.estado IN ('inscripciones_abiertas', 'en_curso')
                      LIMIT 1) AS torneo_bloqueante
               FROM equipos eq
               JOIN equipo_miembros em ON em.equipo_id = eq.id AND em.usuario_id = :usuario_id
          LEFT JOIN usuarios cap ON cap.id = eq.capitan_id
          LEFT JOIN deportes d   ON d.id = eq.deporte_id
              ORDER BY eq.nombre_equipo ASC"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return $consulta->fetchAll();
    }

    /** Equipos de los que el usuario es capitán (los que puede inscribir a un torneo). */
    public function listarComoCapitan(int $usuarioId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT eq.id, eq.nombre_equipo,
                    d.nombre AS deporte, d.jugadores_por_equipo AS tamano,
                    (SELECT COUNT(*) FROM equipo_miembros m WHERE m.equipo_id = eq.id) AS miembros
               FROM equipos eq
          LEFT JOIN deportes d ON d.id = eq.deporte_id
              WHERE eq.capitan_id = :usuario_id
              ORDER BY eq.nombre_equipo ASC"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return $consulta->fetchAll();
    }

    public function listarMiembros(int $equipoId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT u.id AS usuario_id, u.nombre_completo, u.nombre_usuario,
                    (u.id = eq.capitan_id) AS es_capitan
               FROM equipo_miembros em
               JOIN equipos  eq ON eq.id = em.equipo_id
               JOIN usuarios u  ON u.id  = em.usuario_id
              WHERE em.equipo_id = :equipo_id
              ORDER BY es_capitan DESC, u.nombre_completo ASC"
        );
        $consulta->execute(['equipo_id' => $equipoId]);
        return $consulta->fetchAll();
    }

    /** Invitaciones pendientes que envió un equipo (las ve el capitán). */
    public function listarInvitacionesDeEquipo(int $equipoId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT inv.id, u.nombre_completo, u.nombre_usuario
               FROM invitaciones_equipo inv
               JOIN usuarios u ON u.id = inv.usuario_id
              WHERE inv.equipo_id = :equipo_id AND inv.estado = 'pendiente'
              ORDER BY inv.fecha_creacion ASC"
        );
        $consulta->execute(['equipo_id' => $equipoId]);
        return $consulta->fetchAll();
    }

    /** Invitaciones pendientes que recibió un usuario. */
    public function listarInvitacionesRecibidas(int $usuarioId): array
    {
        $consulta = $this->conexion->prepare(
            "SELECT inv.id, eq.nombre_equipo, cap.nombre_completo AS nombre_capitan,
                    (SELECT COUNT(*) FROM equipo_miembros m WHERE m.equipo_id = eq.id) AS miembros
               FROM invitaciones_equipo inv
               JOIN equipos  eq  ON eq.id  = inv.equipo_id
               JOIN usuarios cap ON cap.id = inv.invitado_por
              WHERE inv.usuario_id = :usuario_id AND inv.estado = 'pendiente'
              ORDER BY inv.fecha_creacion DESC"
        );
        $consulta->execute(['usuario_id' => $usuarioId]);
        return $consulta->fetchAll();
    }

    // ---------------------------------------------------------
    // Operaciones (escriben, dentro de una transacción)
    // ---------------------------------------------------------

    /**
     * Crea un equipo PARA un deporte; quien lo crea queda como capitán y
     * primer integrante. El tamaño lo fija el deporte. Devuelve el id del equipo.
     */
    public function crear(string $nombreEquipo, int $capitanId, int $deporteId): int
    {
        return $this->enTransaccion(function () use ($nombreEquipo, $capitanId, $deporteId) {
            $consulta = $this->conexion->prepare('SELECT jugadores_por_equipo FROM deportes WHERE id = :id');
            $consulta->execute(['id' => $deporteId]);
            $deporte = $consulta->fetch();
            if (!$deporte) {
                throw new DomainException('Elegí un deporte válido para el equipo.');
            }
            if ((int) $deporte['jugadores_por_equipo'] < 2) {
                throw new DomainException('Ese deporte se juega de forma individual: no lleva equipos.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT COUNT(*) AS total FROM equipos
                  WHERE capitan_id = :capitan AND deporte_id = :deporte AND LOWER(nombre_equipo) = LOWER(:nombre)'
            );
            $consulta->execute(['capitan' => $capitanId, 'deporte' => $deporteId, 'nombre' => $nombreEquipo]);
            if ((int) $consulta->fetch()['total'] > 0) {
                throw new DomainException('Ya tenés un equipo con ese nombre para ese deporte.');
            }

            $this->conexion->prepare(
                'INSERT INTO equipos (nombre_equipo, capitan_id, deporte_id) VALUES (:nombre, :capitan, :deporte)'
            )->execute(['nombre' => $nombreEquipo, 'capitan' => $capitanId, 'deporte' => $deporteId]);
            $equipoId = (int) $this->conexion->lastInsertId();

            $this->conexion->prepare('INSERT INTO equipo_miembros (equipo_id, usuario_id) VALUES (:equipo, :usuario)')
                           ->execute(['equipo' => $equipoId, 'usuario' => $capitanId]);
            return $equipoId;
        });
    }

    /** El capitán invita a un usuario (por nombre de usuario). Devuelve el id de la invitación. */
    public function invitar(int $equipoId, int $capitanId, string $nombreUsuario): int
    {
        return $this->enTransaccion(function () use ($equipoId, $capitanId, $nombreUsuario) {
            $equipo = $this->bloquearComoCapitan($equipoId, $capitanId, 'invitar');
            $this->exigirNoBloqueado($equipoId);

            $nombreUsuario = ltrim(trim($nombreUsuario), '@');
            $consulta = $this->conexion->prepare(
                'SELECT id, rol_id FROM usuarios WHERE nombre_usuario = :nombre AND activo = 1'
            );
            $consulta->execute(['nombre' => $nombreUsuario]);
            $usuario = $consulta->fetch();

            if (!$usuario) {
                throw new DomainException('No existe ningún usuario con ese nombre de usuario.');
            }
            if ((int) $usuario['rol_id'] !== ROL_PARTICIPANTE) {
                throw new DomainException('Solo se puede invitar a cuentas de participante.');
            }
            $invitadoId = (int) $usuario['id'];

            $consulta = $this->conexion->prepare(
                'SELECT COUNT(*) AS total FROM equipo_miembros WHERE equipo_id = :equipo AND usuario_id = :usuario'
            );
            $consulta->execute(['equipo' => $equipoId, 'usuario' => $invitadoId]);
            if ((int) $consulta->fetch()['total'] > 0) {
                throw new DomainException('Esa persona ya es parte del equipo.');
            }

            $consulta = $this->conexion->prepare(
                "SELECT COUNT(*) AS total FROM invitaciones_equipo
                  WHERE equipo_id = :equipo AND usuario_id = :usuario AND estado = 'pendiente'"
            );
            $consulta->execute(['equipo' => $equipoId, 'usuario' => $invitadoId]);
            if ((int) $consulta->fetch()['total'] > 0) {
                throw new DomainException('Esa persona ya tiene una invitación pendiente de este equipo.');
            }

            // Integrantes + invitaciones pendientes no pueden pasar el tamaño del deporte.
            $tamano = $this->tamanoDelEquipo($equipo);
            $consulta = $this->conexion->prepare(
                "SELECT (SELECT COUNT(*) FROM equipo_miembros WHERE equipo_id = :e1)
                      + (SELECT COUNT(*) FROM invitaciones_equipo WHERE equipo_id = :e2 AND estado = 'pendiente') AS total"
            );
            $consulta->execute(['e1' => $equipoId, 'e2' => $equipoId]);
            if ((int) $consulta->fetch()['total'] >= $tamano) {
                throw new DomainException(
                    "El equipo ya está completo ({$tamano} de {$tamano}, contando invitaciones pendientes). "
                    . 'Si querés invitar a otra persona, cancelá una invitación o sacá a un integrante.'
                );
            }

            $this->conexion->prepare(
                'INSERT INTO invitaciones_equipo (equipo_id, usuario_id, invitado_por) VALUES (:equipo, :usuario, :capitan)'
            )->execute(['equipo' => $equipoId, 'usuario' => $invitadoId, 'capitan' => $capitanId]);
            return (int) $this->conexion->lastInsertId();
        });
    }

    /** El invitado acepta o rechaza. Devuelve el id del equipo. */
    public function responderInvitacion(int $invitacionId, int $usuarioId, bool $acepta): int
    {
        return $this->enTransaccion(function () use ($invitacionId, $usuarioId, $acepta) {
            $consulta = $this->conexion->prepare(
                "SELECT id, equipo_id FROM invitaciones_equipo
                  WHERE id = :id AND usuario_id = :usuario AND estado = 'pendiente' FOR UPDATE"
            );
            $consulta->execute(['id' => $invitacionId, 'usuario' => $usuarioId]);
            $invitacion = $consulta->fetch();
            if (!$invitacion) {
                throw new DomainException('Esa invitación ya no está disponible.');
            }
            $equipoId = (int) $invitacion['equipo_id'];

            if ($acepta) {
                $equipo = $this->bloquearEquipo($equipoId);
                $this->exigirNoBloqueado($equipoId);
                if ($this->contarIntegrantes($equipoId) >= $this->tamanoDelEquipo($equipo)) {
                    throw new DomainException('Ese equipo ya está completo.');
                }

                $consulta = $this->conexion->prepare(
                    'SELECT COUNT(*) AS total FROM equipo_miembros WHERE equipo_id = :equipo AND usuario_id = :usuario'
                );
                $consulta->execute(['equipo' => $equipoId, 'usuario' => $usuarioId]);
                if ((int) $consulta->fetch()['total'] === 0) {
                    $this->conexion->prepare('INSERT INTO equipo_miembros (equipo_id, usuario_id) VALUES (:equipo, :usuario)')
                                   ->execute(['equipo' => $equipoId, 'usuario' => $usuarioId]);
                }
            }

            $this->conexion->prepare(
                'UPDATE invitaciones_equipo SET estado = :estado, fecha_respuesta = NOW() WHERE id = :id'
            )->execute(['estado' => $acepta ? 'aceptada' : 'rechazada', 'id' => $invitacionId]);
            return $equipoId;
        });
    }

    /** El capitán cancela una invitación que todavía no fue respondida. Devuelve el id del equipo. */
    public function cancelarInvitacion(int $invitacionId, int $capitanId): int
    {
        return $this->enTransaccion(function () use ($invitacionId, $capitanId) {
            $consulta = $this->conexion->prepare(
                "SELECT inv.id, inv.equipo_id
                   FROM invitaciones_equipo inv
                   JOIN equipos eq ON eq.id = inv.equipo_id
                  WHERE inv.id = :id AND inv.estado = 'pendiente' AND eq.capitan_id = :capitan
                  FOR UPDATE"
            );
            $consulta->execute(['id' => $invitacionId, 'capitan' => $capitanId]);
            $invitacion = $consulta->fetch();
            if (!$invitacion) {
                throw new DomainException('Esa invitación ya no está disponible.');
            }

            $this->conexion->prepare(
                "UPDATE invitaciones_equipo SET estado = 'cancelada', fecha_respuesta = NOW() WHERE id = :id"
            )->execute(['id' => $invitacionId]);
            return (int) $invitacion['equipo_id'];
        });
    }

    /** El capitán saca a un integrante (no a sí mismo). Devuelve el id del equipo. */
    public function quitarMiembro(int $equipoId, int $capitanId, int $usuarioId): int
    {
        return $this->enTransaccion(function () use ($equipoId, $capitanId, $usuarioId) {
            $this->bloquearComoCapitan($equipoId, $capitanId, 'sacar integrantes');
            $this->exigirNoBloqueado($equipoId);
            if ($usuarioId === $capitanId) {
                throw new DomainException('El capitán no puede sacarse a sí mismo: disolvé el equipo si ya no lo querés.');
            }
            $this->borrarMiembro($equipoId, $usuarioId);
            return $equipoId;
        });
    }

    /** Un integrante (que no sea el capitán) abandona el equipo. Devuelve el id del equipo. */
    public function salir(int $equipoId, int $usuarioId): int
    {
        return $this->enTransaccion(function () use ($equipoId, $usuarioId) {
            $equipo = $this->bloquearEquipo($equipoId);
            if ((int) $equipo['capitan_id'] === $usuarioId) {
                throw new DomainException('El capitán no puede abandonar el equipo: podés disolverlo.');
            }
            $this->exigirNoBloqueado($equipoId);
            $this->borrarMiembro($equipoId, $usuarioId);
            return $equipoId;
        });
    }

    /**
     * El capitán disuelve el equipo. Solo se puede si nunca se inscribió a
     * un torneo (las inscripciones apuntan al equipo y guardan el historial).
     */
    public function disolver(int $equipoId, int $capitanId): int
    {
        return $this->enTransaccion(function () use ($equipoId, $capitanId) {
            $this->bloquearComoCapitan($equipoId, $capitanId, 'disolver el equipo');

            $consulta = $this->conexion->prepare('SELECT COUNT(*) AS total FROM inscripciones WHERE equipo_id = :equipo');
            $consulta->execute(['equipo' => $equipoId]);
            if ((int) $consulta->fetch()['total'] > 0) {
                throw new DomainException('El equipo tiene inscripciones a torneos y no se puede disolver.');
            }

            $this->conexion->prepare('DELETE FROM invitaciones_equipo WHERE equipo_id = :equipo')->execute(['equipo' => $equipoId]);
            $this->conexion->prepare('DELETE FROM equipo_miembros WHERE equipo_id = :equipo')->execute(['equipo' => $equipoId]);
            $this->conexion->prepare('DELETE FROM equipos WHERE id = :equipo')->execute(['equipo' => $equipoId]);
            return $equipoId;
        });
    }

    // ---------------------------------------------------------
    // Auxiliares privados
    // ---------------------------------------------------------

    private function enTransaccion(callable $operacion): int
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();
            return $resultado;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    private function bloquearEquipo(int $equipoId): array
    {
        $consulta = $this->conexion->prepare('SELECT id, capitan_id, deporte_id FROM equipos WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $equipoId]);
        $equipo = $consulta->fetch();
        if (!$equipo) {
            throw new DomainException('El equipo no existe.');
        }
        return $equipo;
    }

    private function bloquearComoCapitan(int $equipoId, int $usuarioId, string $accion): array
    {
        $equipo = $this->bloquearEquipo($equipoId);
        if ((int) $equipo['capitan_id'] !== $usuarioId) {
            throw new DomainException("Solo el capitán puede {$accion}.");
        }
        return $equipo;
    }

    /** La formación no se toca mientras el equipo esté inscripto en un torneo abierto o en curso. */
    private function exigirNoBloqueado(int $equipoId): void
    {
        $consulta = $this->conexion->prepare(
            "SELECT t.nombre
               FROM inscripciones i
               JOIN torneos t ON t.id = i.torneo_id
              WHERE i.equipo_id = :equipo
                AND i.estado_inscripcion = 'activa'
                AND t.estado IN ('inscripciones_abiertas', 'en_curso')
              LIMIT 1"
        );
        $consulta->execute(['equipo' => $equipoId]);
        $torneo = $consulta->fetch();
        if ($torneo) {
            throw new DomainException(
                "El equipo está inscripto en \"{$torneo['nombre']}\" y su formación no se puede modificar. "
                . 'Si el torneo sigue en inscripciones, el capitán puede retirar al equipo desde la página del torneo.'
            );
        }
    }

    private function contarIntegrantes(int $equipoId): int
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) AS total FROM equipo_miembros WHERE equipo_id = :equipo');
        $consulta->execute(['equipo' => $equipoId]);
        return (int) $consulta->fetch()['total'];
    }

    /** Tamaño estándar del equipo = jugadores por equipo de su deporte. */
    private function tamanoDelEquipo(array $equipo): int
    {
        if ($equipo['deporte_id'] === null) {
            throw new DomainException('Este equipo no tiene un deporte asignado: disolvelo y creá uno nuevo.');
        }
        $consulta = $this->conexion->prepare('SELECT jugadores_por_equipo FROM deportes WHERE id = :id');
        $consulta->execute(['id' => $equipo['deporte_id']]);
        $fila = $consulta->fetch();
        if (!$fila) {
            throw new DomainException('El deporte del equipo ya no existe en el catálogo.');
        }
        return (int) $fila['jugadores_por_equipo'];
    }

    /** Invitaciones pendientes que tiene un usuario (para el aviso del menú). */
    public function contarInvitacionesPendientes(int $usuarioId): int
    {
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS total FROM invitaciones_equipo WHERE usuario_id = :usuario AND estado = 'pendiente'"
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return (int) $consulta->fetch()['total'];
    }

    private function borrarMiembro(int $equipoId, int $usuarioId): void
    {
        $consulta = $this->conexion->prepare('DELETE FROM equipo_miembros WHERE equipo_id = :equipo AND usuario_id = :usuario');
        $consulta->execute(['equipo' => $equipoId, 'usuario' => $usuarioId]);
        if ($consulta->rowCount() === 0) {
            throw new DomainException('Esa persona no es parte del equipo.');
        }
    }
}
