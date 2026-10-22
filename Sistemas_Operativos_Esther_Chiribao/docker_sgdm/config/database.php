<?php
/**
 * config/database.php
 *
 * Centraliza la conexión a la base de datos mediante PDO.
 * Se conecta con el usuario 'sgdm_app' (creado en bd/sgdm_dcl.sql),
 * NUNCA con root, siguiendo el principio de menor privilegio.
 *
 * Versión para Docker: lee host, base, usuario y contraseña de variables
 * de entorno (SGDM_DB_HOST, SGDM_DB_NAME, SGDM_DB_USER, SGDM_DB_PASS).
 * Si no están definidas, usa los valores originales (modo servidor sin Docker).
 */

function obtenerConexion(): PDO
{
    $host        = getenv('SGDM_DB_HOST') ?: 'localhost';
    $baseDatos   = getenv('SGDM_DB_NAME') ?: 'sgdm';
    $usuario     = getenv('SGDM_DB_USER') ?: 'sgdm_app';
    $contrasena  = getenv('SGDM_DB_PASS') ?: 'CAMBIAR_ESTA_CONTRASEÑA';

    $dsn = "mysql:host={$host};dbname={$baseDatos};charset=utf8mb4";

    try {
        return new PDO($dsn, $usuario, $contrasena, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $error) {
        // No mostramos el detalle del error al usuario final (podría
        // filtrar información sensible del servidor); queda en el log.
        error_log('Error de conexión a la base de datos: ' . $error->getMessage());
        die('No se pudo conectar con la base de datos. Intentá más tarde.');
    }
}
