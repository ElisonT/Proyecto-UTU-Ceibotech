<?php
/**
 * logout.php
 * Destruye la sesión actual y vuelve al inicio.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
