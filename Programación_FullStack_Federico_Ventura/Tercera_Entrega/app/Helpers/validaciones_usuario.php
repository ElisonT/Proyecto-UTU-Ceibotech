<?php
/**
 * app/Helpers/validaciones_usuario.php
 *
 * Validaciones de los datos de una cuenta que comparten el registro y el panel
 * de administración (así los dos aplican exactamente las mismas reglas del lado
 * del servidor, que son las que realmente cuentan: las del navegador se pueden
 * saltear). La contraseña se valida aparte, con app/Helpers/contrasena.php.
 */

/**
 * Valida nombre de usuario, nombre completo y correo.
 * Pasar null en un dato para no validarlo (por ejemplo, el nombre de usuario no se
 * puede cambiar al editar una cuenta).
 * @return string[] Lista de errores (vacía si todo está bien).
 */
function erroresDatosCuenta(?string $nombreUsuario, ?string $nombreCompleto, ?string $email): array
{
    $errores = [];
    if ($nombreUsuario !== null && !preg_match('/^[a-zA-Z0-9_]{1,30}$/', $nombreUsuario)) {
        $errores[] = 'El nombre de usuario solo puede tener letras, números y guion bajo (hasta 30 caracteres).';
    }
    if ($nombreCompleto !== null && ($nombreCompleto === '' || !str_contains($nombreCompleto, ' ') || mb_strlen($nombreCompleto, 'UTF-8') > 100)) {
        $errores[] = 'Ingresá nombre y apellido (hasta 100 caracteres).';
    }
    if ($email !== null && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)) {
        $errores[] = 'El correo electrónico no es válido.';
    }
    return $errores;
}
