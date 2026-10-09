<?php
/**
 * app/Helpers/roles.php
 * IDs de roles según bd/sgdm_schema.sql (tabla roles).
 */

const ROL_ADMINISTRADOR = 1; // Administrador general
const ROL_ORGANIZADOR   = 2; // Organizador de torneo
const ROL_PARTICIPANTE  = 3; // Participante

/**
 * Quién puede gestionar un torneo (generar fixture, avanzar rondas, cargar
 * resultados): el Administrador general, o el Organizador al que se lo
 * asignaron (ver letra 5.1 y 5.2). Necesita session_start() previo.
 */
function puedeGestionarTorneo(array $torneo): bool
{
    if (empty($_SESSION['usuario_id'])) {
        return false;
    }
    $rol = (int) ($_SESSION['usuario_rol'] ?? 0);
    if ($rol === ROL_ADMINISTRADOR) {
        return true;
    }
    return $rol === ROL_ORGANIZADOR
        && (int) ($torneo['organizador_asignado_id'] ?? 0) === (int) $_SESSION['usuario_id'];
}
