<?php
/**
 * config/database.php
 *
 * Centraliza la conexión a la base de datos mediante PDO.
 * Se conecta con el usuario 'sgdm_app' (creado en bd/sgdm_dcl.sql),
 * NUNCA con root, siguiendo el principio de menor privilegio.
 */

function obtenerConexion(): PDO
{
    // Los datos de conexión salen de variables de entorno (así Docker, y también un
    // servidor común, pueden cambiarlos sin tocar el código). Si una variable no está
    // definida se usa el valor de siempre, o sea que en la VM nada cambia.
    //   SGDM_DB_HOST, SGDM_DB_NAME, SGDM_DB_USER, SGDM_DB_PASS
    $host        = getenv('SGDM_DB_HOST') ?: 'localhost';
    $baseDatos   = getenv('SGDM_DB_NAME') ?: 'sgdm';
    $usuario     = getenv('SGDM_DB_USER') ?: 'sgdm_app';
    // Ojo: si en un servidor la contraseña está escrita acá (en vez de en la variable
    // SGDM_DB_PASS), no hay que pisar este archivo al subir una versión nueva.
    $contrasena  = getenv('SGDM_DB_PASS') !== false ? getenv('SGDM_DB_PASS') : 'CAMBIAR_ESTA_CONTRASEÑA';

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
