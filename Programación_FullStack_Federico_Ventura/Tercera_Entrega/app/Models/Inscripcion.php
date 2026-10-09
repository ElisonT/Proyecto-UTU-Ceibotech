<?php
/**
 * app/Models/Inscripcion.php
 *
 * Inscripción real a torneos: individual, o de un equipo ya armado en
 * "Mis equipos" (lo inscribe su capitán), y darse de baja. Todas las operaciones que escriben usan UNA sola
 * conexión y una transacción que bloquea la fila del torneo (SELECT ... FOR
 * UPDATE): así dos personas que se anotan a la vez no pueden pasarse del
 * cupo ni del tamaño de un equipo.
 *
 * Las reglas de negocio que no se cumplen se informan con DomainException
 * (el mensaje es apto para mostrarle al usuario); lo atrapa
 * InscripcionController.
 */

require_once __DIR__ . '/../../config/database.php';

class Inscripcion
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    // ---------------------------------------------------------
    // Consultas (solo lectura)
    // ---------------------------------------------------------

    /** Inscripción activa del usuario en un torneo: propia (individual) o a través de su equipo. */
    public function obtenerActivaDeUsuario(int $torneoId, int $usuarioId): array|false
    {
        $consulta = $this->conexion->prepare(
            "SELECT i.id, i.equipo_id, i.usuario_id, eq.nombre_equipo, eq.capitan_id
               FROM inscripciones i
          LEFT JOIN equipos eq         ON eq.id = i.equipo_id
          LEFT JOIN equipo_miembros em ON em.equipo_id = i.equipo_id
              WHERE i.torneo_id = :torneo_id
                AND i.estado_inscripcion = 'activa'
                AND (i.usuario_id = :usuario_id OR em.usuario_id = :usuario_id)
              LIMIT 1"
        );
        $consulta->execute(['torneo_id' => $torneoId, 'usuario_id' => $usuarioId]);
        return $consulta->fetch();
    }

    // ---------------------------------------------------------
    // Operaciones (escriben, dentro de una transacción)
    // ---------------------------------------------------------

    /** Inscribe a un usuario como participante individual. Devuelve el id de la inscripción. */
    public function crearIndividual(int $torneoId, int $usuarioId): int
    {
        return $this->enTransaccion(function () use ($torneoId, $usuarioId) {
            $torneo = $this->bloquearTorneo($torneoId);
            $this->exigirInscripcionesAbiertas($torneo);
            if ($torneo['modalidad'] !== 'individual') {
                throw new DomainException('Este torneo es por equipos: creá un equipo o unite a uno.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT id, estado_inscripcion FROM inscripciones WHERE torneo_id = :torneo_id AND usuario_id = :usuario_id'
            );
            $consulta->execute(['torneo_id' => $torneoId, 'usuario_id' => $usuarioId]);
            $previa = $consulta->fetch();

            if ($previa && $previa['estado_inscripcion'] === 'activa') {
                throw new DomainException('Ya estás inscripto en este torneo.');
            }
            if ($previa && $previa['estado_inscripcion'] === 'descalificada') {
                throw new DomainException('Fuiste descalificado de este torneo y no podés volver a inscribirte.');
            }
            $this->exigirCupo($torneo);

            if ($previa) { // se había dado de baja: se reactiva la misma fila
                $this->conexion->prepare(
                    "UPDATE inscripciones SET estado_inscripcion = 'activa', fecha_inscripcion = NOW() WHERE id = :id"
                )->execute(['id' => $previa['id']]);
                return (int) $previa['id'];
            }

            $this->conexion->prepare(
                'INSERT INTO inscripciones (torneo_id, usuario_id) VALUES (:torneo_id, :usuario_id)'
            )->execute(['torneo_id' => $torneoId, 'usuario_id' => $usuarioId]);
            return (int) $this->conexion->lastInsertId();
        });
    }

    /**
     * El capitán inscribe a uno de sus equipos. El equipo tiene que ser del
     * mismo deporte que el torneo y tener el plantel completo (tamaño estándar
     * del deporte); ningún integrante puede estar ya inscripto en este torneo
     * con otro equipo.
     * Devuelve el id de la inscripción.
     */
    public function inscribirEquipo(int $torneoId, int $equipoId, int $usuarioId): int
    {
        return $this->enTransaccion(function () use ($torneoId, $equipoId, $usuarioId) {
            $torneo = $this->bloquearTorneo($torneoId);
            $this->exigirInscripcionesAbiertas($torneo);
            if ($torneo['modalidad'] !== 'equipo') {
                throw new DomainException('Este torneo es individual: no se inscriben equipos.');
            }

            // Se bloquea también el equipo para que nadie cambie el plantel mientras se valida.
            $consulta = $this->conexion->prepare('SELECT id, capitan_id, deporte_id FROM equipos WHERE id = :id FOR UPDATE');
            $consulta->execute(['id' => $equipoId]);
            $equipo = $consulta->fetch();
            if (!$equipo || (int) $equipo['capitan_id'] !== $usuarioId) {
                throw new DomainException('Solo el capitán puede inscribir al equipo a un torneo.');
            }

            // El equipo tiene que ser del mismo deporte que el torneo y estar completo
            // según el tamaño estándar de ese deporte.
            if ($equipo['deporte_id'] === null) {
                throw new DomainException('Este equipo no tiene un deporte asignado: disolvelo y creá uno nuevo en "Mis equipos".');
            }
            $consulta = $this->conexion->prepare('SELECT nombre, jugadores_por_equipo FROM deportes WHERE id = :id');
            $consulta->execute(['id' => $equipo['deporte_id']]);
            $deporteEquipo = $consulta->fetch();
            if (!$deporteEquipo || $deporteEquipo['nombre'] !== $torneo['deporte']) {
                $nombreEquipoDeporte = $deporteEquipo ? $deporteEquipo['nombre'] : 'otro deporte';
                throw new DomainException("Tu equipo es de {$nombreEquipoDeporte} y este torneo es de {$torneo['deporte']}.");
            }

            $requeridos  = (int) $deporteEquipo['jugadores_por_equipo'];
            $consulta = $this->conexion->prepare('SELECT COUNT(*) AS total FROM equipo_miembros WHERE equipo_id = :equipo');
            $consulta->execute(['equipo' => $equipoId]);
            $integrantes = (int) $consulta->fetch()['total'];
            if ($integrantes !== $requeridos) {
                throw new DomainException(
                    "{$torneo['deporte']} se juega de a {$requeridos} y tu equipo tiene {$integrantes}. "
                    . 'Completá el plantel en "Mis equipos".'
                );
            }

            $consulta = $this->conexion->prepare(
                "SELECT COUNT(*) AS total
                   FROM inscripciones i
                   JOIN equipo_miembros otro ON otro.equipo_id = i.equipo_id
                   JOIN equipo_miembros mio  ON mio.usuario_id = otro.usuario_id AND mio.equipo_id = :equipo
                  WHERE i.torneo_id = :torneo_id
                    AND i.estado_inscripcion = 'activa'
                    AND i.equipo_id <> :equipo_otro"
            );
            $consulta->execute(['equipo' => $equipoId, 'torneo_id' => $torneoId, 'equipo_otro' => $equipoId]);
            if ((int) $consulta->fetch()['total'] > 0) {
                throw new DomainException('Algún integrante del equipo ya está inscripto en este torneo con otro equipo.');
            }

            $consulta = $this->conexion->prepare(
                'SELECT id, estado_inscripcion FROM inscripciones WHERE torneo_id = :torneo_id AND equipo_id = :equipo'
            );
            $consulta->execute(['torneo_id' => $torneoId, 'equipo' => $equipoId]);
            $previa = $consulta->fetch();

            if ($previa && $previa['estado_inscripcion'] === 'activa') {
                throw new DomainException('Ese equipo ya está inscripto en este torneo.');
            }
            if ($previa && $previa['estado_inscripcion'] === 'descalificada') {
                throw new DomainException('Ese equipo fue descalificado de este torneo y no puede volver a inscribirse.');
            }
            $this->exigirCupo($torneo);

            if ($previa) { // el equipo se había retirado: se reactiva la misma fila
                $this->conexion->prepare(
                    "UPDATE inscripciones SET estado_inscripcion = 'activa', fecha_inscripcion = NOW() WHERE id = :id"
                )->execute(['id' => $previa['id']]);
                return (int) $previa['id'];
            }

            $this->conexion->prepare('INSERT INTO inscripciones (torneo_id, equipo_id) VALUES (:torneo_id, :equipo)')
                           ->execute(['torneo_id' => $torneoId, 'equipo' => $equipoId]);
            return (int) $this->conexion->lastInsertId();
        });
    }

    /**
     * Baja del torneo mientras las inscripciones sigan abiertas. Individual:
     * el participante retira su inscripción. Equipo: solo el capitán retira
     * al equipo (los integrantes no se tocan: eso se hace en "Mis equipos").
     * La inscripción queda "retirada". Devuelve su id.
     */
    public function darseDeBaja(int $torneoId, int $usuarioId): int
    {
        return $this->enTransaccion(function () use ($torneoId, $usuarioId) {
            $torneo = $this->bloquearTorneo($torneoId);
            $this->exigirInscripcionesAbiertas($torneo);

            $inscripcion = $this->obtenerActivaDeUsuario($torneoId, $usuarioId);
            if (!$inscripcion) {
                throw new DomainException('No tenés una inscripción activa en este torneo.');
            }
            if ($inscripcion['equipo_id'] !== null && (int) $inscripcion['capitan_id'] !== $usuarioId) {
                throw new DomainException('Solo el capitán puede retirar al equipo del torneo.');
            }

            $this->conexion->prepare("UPDATE inscripciones SET estado_inscripcion = 'retirada' WHERE id = :id")
                           ->execute(['id' => $inscripcion['id']]);
            return (int) $inscripcion['id'];
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

    /** Lee el torneo bloqueando su fila hasta terminar la transacción. */
    private function bloquearTorneo(int $torneoId): array
    {
        $consulta = $this->conexion->prepare(
            'SELECT id, estado, modalidad, cupos, deporte FROM torneos WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $torneoId]);
        $torneo = $consulta->fetch();
        if (!$torneo) {
            throw new DomainException('El torneo no existe.');
        }
        return $torneo;
    }

    private function exigirInscripcionesAbiertas(array $torneo): void
    {
        if ($torneo['estado'] !== 'inscripciones_abiertas') {
            throw new DomainException('Las inscripciones de este torneo están cerradas.');
        }
    }

    private function exigirCupo(array $torneo): void
    {
        if ($torneo['cupos'] === null) {
            return; // sin límite
        }
        $consulta = $this->conexion->prepare(
            "SELECT COUNT(*) AS total FROM inscripciones WHERE torneo_id = :torneo_id AND estado_inscripcion = 'activa'"
        );
        $consulta->execute(['torneo_id' => $torneo['id']]);
        if ((int) $consulta->fetch()['total'] >= (int) $torneo['cupos']) {
            throw new DomainException('No quedan cupos disponibles en este torneo.');
        }
    }
}
