<?php
/**
 * app/Helpers/contrasena.php
 *
 * UNA sola política de contraseñas para todo el sitio: la usan el registro y
 * el cambio de contraseña (antes cada uno tenía una regla distinta). El mismo
 * criterio está repetido en script.js y en los atributos "pattern" de los
 * formularios solo para avisar rápido al usuario; la validación que cuenta
 * es esta, la del servidor.
 *
 * Reglas:
 *   - entre 8 y 72 caracteres (72 bytes es lo máximo que usa bcrypt, el
 *     algoritmo de password_hash: lo que pase de ahí se ignoraría en silencio),
 *   - al menos una mayúscula, una minúscula y un número.
 */

const MENSAJE_POLITICA_CONTRASENA =
    'La contraseña debe tener entre 8 y 72 caracteres, con al menos una mayúscula, una minúscula y un número.';

/** Devuelve el mensaje de error, o null si la contraseña cumple la política. */
function errorPoliticaContrasena(string $contrasena): ?string
{
    // preg_match con /u devuelve false si el texto no es UTF-8 válido: también se rechaza.
    $cumple = mb_strlen($contrasena, 'UTF-8') >= 8
        && strlen($contrasena) <= 72
        && preg_match('/\p{Lu}/u', $contrasena) === 1
        && preg_match('/\p{Ll}/u', $contrasena) === 1
        && preg_match('/[0-9]/', $contrasena) === 1;

    return $cumple ? null : MENSAJE_POLITICA_CONTRASENA;
}
