<?php
/**
 * logout.php
 * Destruye la sesión actual y vuelve al inicio.
 */
session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
