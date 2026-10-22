<?php
/**
 * logout.php
 * Cierra la sesión. Solo acepta POST con token CSRF (botón "Cerrar sesión"): con un link GET cualquier
 * página ajena podría cerrarle la sesión a alguien con un simple <img src="logout.php">.
 * Un GET no hace nada: vuelve al inicio.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
exigirCsrf('index.php');

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $c = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
}
session_destroy();
header('Location: index.php');
exit;
