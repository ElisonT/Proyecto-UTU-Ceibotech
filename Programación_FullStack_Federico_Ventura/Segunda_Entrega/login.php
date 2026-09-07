<?php
require_once __DIR__ . '/app/Controllers/UsuarioController.php';
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';

$errorLogin = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador = new UsuarioController();
    $errorLogin = $controlador->procesarLogin($_POST['login'] ?? '', $_POST['password'] ?? '');

    if ($errorLogin === null) {
        // Login exitoso: la sesión ya quedó armada dentro del controlador.
        header('Location: index.php');
        exit;
    }
}

$vieneDeRegistro = isset($_GET['registrado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <!-- HEAD: contiene informacion para el navegador; no se muestra como contenido principal de la pagina. -->
  <!-- charset define la codificacion para que tildes y eñes se lean correctamente. -->
  <meta charset="UTF-8" />
  <script>
    // Aplica el modo oscuro ANTES de que se pinte la página, para evitar el
    // destello blanco al cargar/cambiar de página (si no, se ve un instante
    // en claro y recién después salta a oscuro).
    (function () {
      if (localStorage.getItem('sgdm-tema') === 'oscuro') {
        document.documentElement.classList.add('modo-oscuro');
      }
    })();
  </script>
  <!-- viewport adapta el ancho de la pagina a celulares, tablets y PC. -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- title es el texto que aparece en la pestaña del navegador. -->
  <title>Iniciar sesión — SGDM</title>
  <!-- styles.css guarda todos los estilos visuales del sitio. -->
  <link rel="stylesheet" href="styles.css?v=25" />
  <!-- Font Awesome aporta los iconos usados en botones, tarjetas y menus. -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="auth-body">
  <!-- BODY: contiene todo lo visible de la pagina: navegacion, contenido principal y pie. -->

  <!-- NAVBAR: navegacion simple de autenticacion; permite volver al inicio. -->
  <nav class="nav">
    <a href="index.php" class="nav-logo">
      <img src="Imagenes/Logo_Pagina.png" alt="Logo_Página" class="nav-logo-img"/>
      <span class="nav-logo-text">PrimeCup</span>
    </a>
    <div class="nav-botones">
      <button class="theme-toggle" id="themeToggle" aria-label="Cambiar a modo oscuro">
        <i class="fa-solid fa-moon"></i>
      </button>
      <button class="nav-toggle" id="navToggle" aria-label="Abrir menú" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
    <div class="nav-links" id="navLinks">
      <a href="index.php" class="nav-link-visible"><i class="fa-solid fa-arrow-left nav-link-icon"></i>Volver al inicio</a>
    </div>
  </nav>

  <main class="auth-main">
    <!-- MAIN: contenido central y especifico de esta pagina. -->
    <!-- TARJETA DE LOGIN: contenedor central del formulario de ingreso. -->
    <div class="auth-card">

      <div class="auth-header">
        <h1 class="auth-title">Iniciar sesión</h1>
        <p class="auth-subtitle">Ingresá a tu cuenta para gestionar tus torneos</p>
      </div>

      <?php if ($vieneDeRegistro): ?>
        <div class="form-alert form-alert-success" style="display:flex;">
          <i class="fa-solid fa-circle-check"></i>
          <span>¡Cuenta creada correctamente! Ya podés iniciar sesión.</span>
        </div>
      <?php endif; ?>

      <!-- FORMULARIO DE LOGIN: campos para usuario/correo y contrasena. -->
      <form class="auth-form" id="loginForm" method="post" action="login.php">

        <div class="form-group" id="group-login">
          <label class="form-label" for="login">Usuario o correo electrónico</label>
          <input type="text" id="login" name="login" class="form-input form-input-lg" placeholder="Tu usuario o correo" autocomplete="username" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"/>
          <span class="form-error" id="error-login"></span>
        </div>

        <div class="form-group" id="group-password">
          <label class="form-label" for="password">Contraseña</label>
          <div class="input-password-wrap">
            <input type="password" id="password" name="password" class="form-input form-input-lg" placeholder="Tu contraseña" autocomplete="current-password"/>
          </div>
          <span class="form-error" id="error-password"></span>
        </div>

        <div class="form-forgot">
          <a href="#" id="linkOlvideContrasena">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn-primary btn-lg btn-block">Iniciar sesión</button>

        <div class="form-alert form-alert-error" id="alert-login" style="<?= $errorLogin ? 'display:flex;' : 'display:none;' ?>">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?= htmlspecialchars($errorLogin ?? 'Usuario o contraseña incorrectos.') ?></span>
        </div>

      </form>

      <div class="auth-footer">
        ¿No tenés cuenta? <a href="register.php">Registrate acá</a>
      </div>

    </div>
  </main>

<script src="script.js?v=25"></script>
</body>
</html>
