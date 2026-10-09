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

/** Escapa texto para imprimirlo de forma segura dentro de HTML. */
function escapar(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Fecha "2026-09-14" -> "14 sep 2026". Si no hay fecha, un texto neutro. */
function formatearFecha(?string $fecha): string
{
    if (!$fecha || strtotime($fecha) === false) {
        return 'Fecha a definir';
    }
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $t = strtotime($fecha);
    return date('j', $t) . ' ' . $meses[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}
