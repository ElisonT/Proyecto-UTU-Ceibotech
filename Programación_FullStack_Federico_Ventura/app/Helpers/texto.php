<?php
/**
 * app/Helpers/texto.php
 * Funciones auxiliares de texto, reutilizadas en varias vistas.
 */

/**
 * Devuelve las iniciales de un nombre completo para mostrar en el avatar
 * circular del nav (ej: "Nombre Apellido" -> "NA").
 */
function obtenerIniciales(string $nombreCompleto): string
{
    $partes = preg_split('/\s+/', trim($nombreCompleto));
    $partes = array_filter($partes);

    if (empty($partes)) {
        return '?';
    }

    $primera = mb_strtoupper(mb_substr($partes[0], 0, 1));
    $ultima  = count($partes) > 1 ? mb_strtoupper(mb_substr(end($partes), 0, 1)) : '';

    return $primera . $ultima;
}
