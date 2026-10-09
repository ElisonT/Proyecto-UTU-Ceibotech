<?php
/**
 * app/Models/Auditoria.php
 *
 * Registra en la tabla `auditoria` cualquier acción que modifique datos
 * relevantes del sistema (alta de usuario, login, edición de perfil,
 * cambio de contraseña, eliminación de cuenta, creación de torneo,
 * asignación de organizador, etc.), tal como pide la letra del proyecto
 * (secciones 4.1 y 14): mantener una historia de todo lo realizado
 * sobre la base de datos.
 *
 * El registro de auditoría nunca debe frenar la operación principal:
 * si por algún motivo falla, se registra en el log del servidor y se
 * sigue, en vez de tirar abajo la acción del usuario por un problema
 * en el propio sistema de auditoría.
 */

require_once __DIR__ . '/../../config/database.php';

class Auditoria
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    public function registrar(
        ?int $usuarioId,
        string $accion,
        ?string $tablaAfectada = null,
        ?int $registroId = null,
        ?string $detalle = null
    ): void {
        try {
            $consulta = $this->conexion->prepare(
                'INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, detalle, ip)
                 VALUES (:usuario_id, :accion, :tabla, :registro_id, :detalle, :ip)'
            );
            $consulta->execute([
                'usuario_id'  => $usuarioId,
                'accion'      => $accion,
                'tabla'       => $tablaAfectada,
                'registro_id' => $registroId,
                'detalle'     => $detalle,
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $error) {
            // Un fallo al auditar no debe romper la acción que se estaba
            // haciendo; queda registrado igual en el log del servidor.
            error_log('[SGDM] No se pudo registrar auditoría: ' . $error->getMessage());
        }
    }
}
