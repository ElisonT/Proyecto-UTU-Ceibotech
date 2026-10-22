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

/**
 * Quién puede CORREGIR un resultado ya cargado (letra 5.2: el organizador corrige
 * "si cuenta con autorización"): el Administrador general siempre; el Organizador
 * asignado solo si el administrador le habilitó la corrección en ese torneo
 * (columna torneos.correccion_autorizada). Cargar un resultado nuevo no lo exige.
 */
function puedeCorregirResultados(array $torneo): bool
{
    if (!puedeGestionarTorneo($torneo)) {
        return false;
    }
    if ((int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ADMINISTRADOR) {
        return true;
    }
    return (int) ($torneo['correccion_autorizada'] ?? 0) === 1;
}

/** Solo el Administrador general decide la autorización de corrección. */
function esAdministrador(): bool
{
    return !empty($_SESSION['usuario_id'])
        && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ADMINISTRADOR;
}

/**
 * ¿El resultado que está por cargar esta persona queda validado al instante? Sí si es Administrador o
 * si la validación de resultados está apagada; si no, queda pendiente hasta que un administrador lo valide.
 */
function resultadoQuedaValidado(): bool
{
    if (esAdministrador()) {
        return true;
    }
    require_once __DIR__ . '/../Models/Configuracion.php';
    return !(new Configuracion())->validacionActiva();
}
