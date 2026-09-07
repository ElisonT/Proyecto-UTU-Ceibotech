<?php
require_once __DIR__ . '/app/Controllers/UsuarioController.php';
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador = new UsuarioController();
    $errores = $controlador->procesarRegistro($_POST);

    if (empty($errores)) {
        header('Location: login.php?registrado=1');
        exit;
    }
}

// Guarda lo que el usuario ya había escrito, para no hacerle repetir todo
// el formulario si algo falló en la validación del servidor.
$valores = $_POST ?? [];
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
  <title>Registrarse — SGDM</title>
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
    <!-- TARJETA DE REGISTRO: contenedor central para crear una cuenta nueva. -->
    <div class="auth-card">

      <div class="auth-header">
        <h1 class="auth-title">Crear cuenta</h1>
        <p class="auth-subtitle">Registrate para empezar a participar en torneos</p>
      </div>

      <?php if (!empty($errores)): ?>
        <div class="form-alert form-alert-error" style="display:flex; align-items:flex-start;">
          <i class="fa-solid fa-circle-exclamation"></i>
          <ul style="margin:0; padding-left:1.1rem;">
            <?php foreach ($errores as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- FORMULARIO DE REGISTRO: campos basicos para crear usuario. -->
      <form class="auth-form" id="registerForm" method="post" action="register.php">

        <!-- FILA 1: campo para crear el nombre de usuario. -->
        <div class="form-group" id="group-usuario">
          <label class="form-label" for="usuario">Nombre de usuario</label>
          <input
            type="text"
            id="usuario"
            name="usuario"
            class="form-input"
            placeholder="ej: usuario_user1"
            pattern="[a-zA-Z0-9_]+"
            title="Solo letras, números y guion bajo, sin espacios"
            value="<?= htmlspecialchars($valores['usuario'] ?? '') ?>"
            required
          />
          <span class="form-error" id="error-usuario"></span>
        </div>

        <!-- FILA 2: campo para ingresar nombre y apellido. -->
        <div class="form-group" id="group-nombre">
          <label class="form-label" for="nombre">Nombre completo</label>
          <input
            type="text"
            id="nombre"
            name="nombre"
            class="form-input"
            placeholder="Tu nombre y apellido"
            pattern="[A-Za-záéíóúÁÉÍÓÚñÑ]+ [A-Za-záéíóúÁÉÍÓÚñÑ].*"
            title="Ingresá tu nombre y apellido"
            value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>"
            required
          />
          <span class="form-error" id="error-nombre"></span>
        </div>

        <!-- FILA 3: campo de email; type="email" ayuda a validar formato desde HTML. -->
        <div class="form-group" id="group-email">
          <label class="form-label" for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" class="form-input" placeholder="ejemplo@correo.com" autocomplete="email" value="<?= htmlspecialchars($valores['email'] ?? '') ?>"/>
          <span class="form-error" id="error-email"></span>
        </div>

        <!-- FILA 4: campo telefonico; type="tel" indica que se espera un numero. -->
        <div class="form-group" id="group-celular">
          <label class="form-label" for="celular">Número de celular</label>
          <input type="tel" id="celular" name="celular" class="form-input" placeholder="+598 09X XXX XXX" pattern="[0-9+\s]+" autocomplete="tel" value="<?= htmlspecialchars($valores['celular'] ?? '') ?>"/>
          <span class="form-error" id="error-celular"></span>
        </div>

        <!-- FILA 4.5: campo de genero; incluye opcion para no especificarlo. -->
        <div class="form-group" id="group-genero">
          <label class="form-label">Género</label>
          <div class="radio-inline">
            <label class="radio-opcion">
              <input type="radio" name="genero" value="masculino" <?= ($valores['genero'] ?? '') === 'masculino' ? 'checked' : '' ?> />
              <span>Masculino</span>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="genero" value="femenino" <?= ($valores['genero'] ?? '') === 'femenino' ? 'checked' : '' ?> />
              <span>Femenino</span>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="genero" value="otro" <?= ($valores['genero'] ?? 'otro') === 'otro' ? 'checked' : '' ?> />
              <span>Otro / Prefiero no decirlo</span>
            </label>
          </div>
        </div>

        <!-- FILA 5: campo de contrasena; minlength exige un minimo de caracteres. -->
        <div class="form-row">
          <div class="form-group" id="group-password">
            <label class="form-label" for="password">Contraseña</label>
            <div class="input-password-wrap">
              <input type="password" id="password" name="password" class="form-input" placeholder="Mínimo 8 caracteres" minlength="8" autocomplete="new-password" required/>
            </div>
            <span class="form-hint">Usá al menos 8 caracteres.</span>
            <div class="password-strength" id="passwordStrength">
              <div class="strength-track">
                <div class="strength-fill" id="strengthFill"></div>
              </div>
              <span class="strength-label" id="strengthLabel">Débil</span>
            </div>
            <span class="form-error" id="error-password"></span>
          </div>
          <div class="form-group" id="group-confirm">
            <label class="form-label" for="confirm">Confirmar contraseña</label>
            <div class="input-password-wrap">
              <input type="password" id="confirm" name="confirm" class="form-input" placeholder="Repetí tu contraseña" autocomplete="new-password" required/>
            </div>
            <span class="form-error" id="error-confirm"></span>
          </div>
        </div>

        <button type="submit" class="btn-primary btn-lg btn-block">Crear cuenta</button>

      </form>

      <div class="auth-footer">
        ¿Ya tenés cuenta? <a href="login.php">Iniciá sesión acá</a>
      </div>

    </div>
  </main>

<script src="script.js?v=25"></script>
</body>
</html>
