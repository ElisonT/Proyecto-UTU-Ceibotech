<?php
/**
 * app/Controllers/InscripcionController.php
 *
 * Valida lo que llega de los formularios de inscripción (detalle.php ->
 * inscribirse.php): individual o de un equipo armado en "Mis equipos", delega las reglas y la escritura en el modelo
 * Inscripcion, y deja todo registrado en auditoría.
 */

require_once __DIR__ . '/../Models/Inscripcion.php';
require_once __DIR__ . '/../Models/Auditoria.php';
require_once __DIR__ . '/../Models/Usuario.php';
require_once __DIR__ . '/../Helpers/roles.php';

class InscripcionController
{
    private Inscripcion $modelo;
    private Auditoria $auditoria;

    public function __construct()
    {
        $this->modelo    = new Inscripcion();
        $this->auditoria = new Auditoria();
    }

    /** @return string[] Lista de errores. Vacía si salió bien. */
    public function inscribirIndividual(int $torneoId, int $usuarioId): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->crearIndividual($torneoId, $usuarioId),
            $usuarioId, 'INSCRIPCION', "Torneo #{$torneoId}: inscripción individual"
        );
    }

    /** @return string[] */
    public function inscribirEquipo(int $torneoId, int $equipoId, int $usuarioId): array
    {
        if ($equipoId <= 0) {
            return ['Elegí uno de tus equipos.'];
        }

        return $this->ejecutar(
            fn () => $this->modelo->inscribirEquipo($torneoId, $equipoId, $usuarioId),
            $usuarioId, 'INSCRIPCION_EQUIPO', "Torneo #{$torneoId}: inscribió al equipo #{$equipoId}"
        );
    }

    /** @return string[] */
    public function darseDeBaja(int $torneoId, int $usuarioId): array
    {
        return $this->ejecutar(
            fn () => $this->modelo->darseDeBaja($torneoId, $usuarioId),
            $usuarioId, 'BAJA_INSCRIPCION', "Torneo #{$torneoId}: baja de la inscripción"
        );
    }

    /**
     * Quien gestiona el torneo (permiso ya verificado en gestionar-torneo.php) inscribe a una persona
     * por su nombre de usuario. Tiene que ser una cuenta de participante, activa. (Letra 5.2.)
     *
     * @return string[]
     */
    public function inscribirPersonaPorOrganizador(int $torneoId, string $nombreUsuario, int $actorId): array
    {
        $nombreUsuario = ltrim(trim($nombreUsuario), '@');
        if ($nombreUsuario === '') {
            return ['Escribí el nombre de usuario de la persona a inscribir.'];
        }

        $modeloUsuario = new Usuario();
        $persona = $modeloUsuario->buscarPorLogin($nombreUsuario);
        // Se busca solo por nombre de usuario: el correo no se expone ni se acepta acá.
        if (!$persona || $persona['nombre_usuario'] !== $nombreUsuario || $modeloUsuario->estaDadaDeBaja($persona)) {
            return ["No existe ningún usuario con el nombre \"{$nombreUsuario}\"."];
        }
        if ((int) $persona['rol_id'] !== ROL_PARTICIPANTE || (int) $persona['activo'] !== 1) {
            return ['Solo se puede inscribir a cuentas de participante activas.'];
        }

        return $this->ejecutar(
            fn () => $this->modelo->crearIndividual($torneoId, (int) $persona['id'], true),
            $actorId, 'INSCRIPCION_POR_ORGANIZADOR',
            "Torneo #{$torneoId}: inscribió a {$persona['nombre_usuario']} (usuario #{$persona['id']})"
        );
    }

    /** @return string[] */
    public function inscribirEquipoPorOrganizador(int $torneoId, int $equipoId, int $actorId): array
    {
        if ($equipoId <= 0) {
            return ['Elegí un equipo de la lista.'];
        }
        return $this->ejecutar(
            fn () => $this->modelo->inscribirEquipoPorOrganizador($torneoId, $equipoId),
            $actorId, 'INSCRIPCION_EQUIPO_POR_ORGANIZADOR', "Torneo #{$torneoId}: inscribió al equipo #{$equipoId}"
        );
    }

    /** @return string[] */
    public function retirarPorOrganizador(int $torneoId, int $inscripcionId, int $actorId): array
    {
        if ($inscripcionId <= 0) {
            return ['Inscripción inválida.'];
        }
        try {
            $nombre = $this->modelo->retirarPorOrganizador($torneoId, $inscripcionId);
        } catch (DomainException $error) {
            return [$error->getMessage()];
        }
        $this->auditoria->registrar($actorId, 'BAJA_INSCRIPCION_POR_ORGANIZADOR', 'inscripciones', $inscripcionId,
            "Torneo #{$torneoId}: retiró la inscripción de {$nombre}");
        return [];
    }

    /** Ejecuta la operación del modelo; las reglas incumplidas (DomainException) vuelven como error. */
    private function ejecutar(callable $operacion, int $usuarioId, string $codigo, string $detalle): array
    {
        try {
            $inscripcionId = $operacion();
        } catch (DomainException $error) {
            return [$error->getMessage()];
        }

        $this->auditoria->registrar($usuarioId, $codigo, 'inscripciones', $inscripcionId, $detalle);
        return [];
    }
}
