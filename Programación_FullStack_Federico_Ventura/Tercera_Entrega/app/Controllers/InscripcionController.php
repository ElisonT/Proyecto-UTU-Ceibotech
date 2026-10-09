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
