<?php
/**
 * app/Controllers/EquipoController.php
 *
 * Valida lo que llega de los formularios de "Mis equipos" (mis-equipos.php
 * -> gestionar-equipo.php), delega las reglas en el modelo Equipo y deja
 * todo registrado en auditoría.
 */

require_once __DIR__ . '/../Models/Equipo.php';
require_once __DIR__ . '/../Models/Auditoria.php';

class EquipoController
{
    private Equipo $modelo;
    private Auditoria $auditoria;

    public function __construct()
    {
        $this->modelo    = new Equipo();
        $this->auditoria = new Auditoria();
    }

    /** @return string[] Lista de errores. Vacía si salió bien. */
    public function crear(string $nombreEquipo, int $usuarioId, int $deporteId): array
    {
        if ($deporteId <= 0) {
            return ['Elegí el deporte para el que es el equipo.'];
        }

        $nombreEquipo = trim(preg_replace('/\s+/', ' ', $nombreEquipo));
        $largo = mb_strlen($nombreEquipo);
        if ($largo < 3 || $largo > 100) {
            return ['El nombre del equipo tiene que tener entre 3 y 100 caracteres.'];
        }

        return $this->ejecutar(
            fn () => $this->modelo->crear($nombreEquipo, $usuarioId, $deporteId),
            $usuarioId, 'CREACION_EQUIPO', "Equipo '{$nombreEquipo}' (deporte #{$deporteId})"
        );
    }

    /** @return string[] */
    public function invitar(int $equipoId, int $capitanId, string $nombreUsuario): array
    {
        if (trim($nombreUsuario) === '') {
            return ['Escribí el nombre de usuario de la persona que querés invitar.'];
        }

        return $this->ejecutar(
            fn () => $this->modelo->invitar($equipoId, $capitanId, $nombreUsuario),
            $capitanId, 'INVITACION_EQUIPO', "Equipo #{$equipoId}: invitó a '" . ltrim(trim($nombreUsuario), '@') . "'"
        );
    }

    /** @return string[] */
    public function responderInvitacion(int $invitacionId, int $usuarioId, bool $acepta): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->responderInvitacion($invitacionId, $usuarioId, $acepta),
            $usuarioId, 'RESPUESTA_INVITACION', "Invitación #{$invitacionId}: " . ($acepta ? 'aceptada' : 'rechazada')
        );
    }

    /** @return string[] */
    public function cancelarInvitacion(int $invitacionId, int $capitanId): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->cancelarInvitacion($invitacionId, $capitanId),
            $capitanId, 'CANCELACION_INVITACION', "Invitación #{$invitacionId} cancelada"
        );
    }

    /** @return string[] */
    public function quitarMiembro(int $equipoId, int $capitanId, int $usuarioId): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->quitarMiembro($equipoId, $capitanId, $usuarioId),
            $capitanId, 'QUITA_INTEGRANTE', "Equipo #{$equipoId}: sacó al usuario #{$usuarioId}"
        );
    }

    /** @return string[] */
    public function salir(int $equipoId, int $usuarioId): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->salir($equipoId, $usuarioId),
            $usuarioId, 'BAJA_EQUIPO', "Equipo #{$equipoId}: abandonó el equipo"
        );
    }

    /** @return string[] */
    public function disolver(int $equipoId, int $capitanId): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->disolver($equipoId, $capitanId),
            $capitanId, 'DISOLUCION_EQUIPO', "Equipo #{$equipoId} disuelto"
        );
    }

    /** Ejecuta la operación del modelo; las reglas incumplidas (DomainException) vuelven como error. */
    private function ejecutar(callable $operacion, int $usuarioId, string $codigo, string $detalle): array
    {
        try {
            $registroId = $operacion();
        } catch (DomainException $error) {
            return [$error->getMessage()];
        }

        $this->auditoria->registrar($usuarioId, $codigo, 'equipos', $registroId, $detalle);
        return [];
    }
}
