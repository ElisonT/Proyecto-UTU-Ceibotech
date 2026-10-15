<?php
/**
 * app/Helpers/csrf.php
 *
 * Protección contra CSRF (que otro sitio haga que el navegador de un usuario
 * con sesión iniciada mande un formulario sin que se dé cuenta). Cada sesión
 * tiene un token al azar; los formularios y las llamadas fetch lo mandan, y
 * el servidor solo acepta el pedido si coincide con el de la sesión.
 * Necesita session_start() previo.
 */

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto para poner dentro de cualquier <form method="post">. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') . '" />';
}

/** <meta> para que el JavaScript pueda leer el token y mandarlo en sus fetch. */
function metaCsrf(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') . '" />';
}

/** true si el pedido trae el token correcto (campo "csrf" o encabezado X-CSRF-Token). */
function csrfValido(): bool
{
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf']) && is_string($enviado) && hash_equals($_SESSION['csrf'], $enviado);
}

/**
 * Corta cualquier POST que no traiga el token correcto. Se llama arriba de cada
 * página que procesa formularios, así ningún formulario queda sin proteger aunque
 * alguien se olvide de revisarlo más abajo.
 * $volverA: página a la que ofrecer volver (ej. 'login.php').
 */
function exigirCsrf(string $volverA = 'index.php'): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || csrfValido()) {
        return;
    }
    http_response_code(403);
    $volver = htmlspecialchars($volverA, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" />'
       . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
       . '<title>Página desactualizada · PrimeCup</title>'
       . '<link rel="stylesheet" href="styles.css?v=29" /></head>'
       . '<body style="display:flex;min-height:100vh;align-items:center;justify-content:center;padding:1rem;">'
       . '<main class="admin-card" style="max-width:28rem;text-align:center;">'
       . '<h1 class="seccion-titulo" style="justify-content:center;">La página quedó desactualizada</h1>'
       . '<p>No se pudo confirmar que el formulario salió de este sitio (puede pasar si la página estuvo mucho tiempo abierta '
       . 'o si se cerró la sesión). No se guardó ningún cambio.</p>'
       . '<p><a class="btn-primary" href="' . $volver . '">Volver y reintentar</a></p>'
       . '</main></body></html>';
    exit;
}
