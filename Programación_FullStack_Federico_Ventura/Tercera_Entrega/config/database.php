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
    $host        = 'localhost';
    $baseDatos   = 'sgdm';
    $usuario     = 'sgdm_app';
    // TODO: antes de subir esto a un repositorio público, mover
    // la contraseña a una variable de entorno en vez de dejarla
    // escrita acá (por ejemplo con getenv('SGDM_DB_PASS')).
    $contrasena  = 'CAMBIAR_ESTA_CONTRASEÑA';

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
