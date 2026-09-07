<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';

$puedeCrearTorneo = !empty($_SESSION['usuario_id'])
    && in_array((int) ($_SESSION['usuario_rol'] ?? 0), [ROL_ADMINISTRADOR], true);
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
  <title>Fechas — Mundialito 2026 — SGDM</title>
  <!-- styles.css guarda todos los estilos visuales del sitio. -->
  <link rel="stylesheet" href="styles.css?v=25" />
  <!-- Font Awesome aporta los iconos usados en botones, tarjetas y menus. -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <!-- BODY: contiene todo lo visible de la pagina: navegacion, contenido principal y pie. -->

  <!-- NAVBAR: menu principal para moverse entre las pantallas del mockup. -->
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
    <?php $mostrarComoFunciona = false; include __DIR__ . '/app/Views/partials/nav_links.php'; ?>
  </nav>

  <main class="detalle-main">
    <!-- MAIN: contenido central y especifico de esta pagina. -->

    <!-- ENCABEZADO: titulo de la pantalla y acciones principales de la pagina. -->
    <div class="config-encabezado">
      <a href="detalle.php?id=1" class="config-volver" id="fechasVolverLink">
        <i class="fa-solid fa-arrow-left"></i> Volver al torneo
      </a>
      <h1 class="config-titulo">Calendario completo</h1>
      <p class="config-subtitulo" id="fechasSubtitulo">Mundialito 2026 · Fútbol 11 · Liga · 8 equipos · 7 fechas</p>
    </div>

    <!-- RESUMEN: datos generales del fixture, se calculan con JS según el torneo. -->
    <div class="perfil-stats">
      <div class="pstat">
        <div class="pstat-num" id="fechasStatJugadas">-</div>
        <div class="pstat-label">Fechas jugadas</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" id="fechasStatEnCurso">-</div>
        <div class="pstat-label">Fecha en curso</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" id="fechasStatPendientes">-</div>
        <div class="pstat-label">Fechas pendientes</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" id="fechasStatPartidos">-</div>
        <div class="pstat-label">Partidos jugados</div>
      </div>
    </div>

    <!-- FECHAS: se generan con JS (ver generarFixture() en script.js), a partir
         de la lista de equipos/participantes de cada torneo. -->
    <div id="fechasContenedor"></div>

  </main>

  <!-- FOOTER: informacion final comun del sistema. -->
  <footer class="footer">
    <div class="footer-links">
      <a href="como-funciona.php">Cómo funciona</a>
      <?php if ($puedeCrearTorneo): ?>
        <a href="crear-torneo.php">Crear torneo</a>
      <?php endif; ?>
      <a href="#">Términos</a>
      <a href="#">Privacidad</a>
    </div>
    <div class="footer-brand">
      <img src="Imagenes/Logo_Pagina.png" alt="Logo PrimeCup" class="footer-logo">
      <span>&copy; 2026 CeiboTech</span>
    </div>
  </footer>

<script src="script.js?v=25"></script>
</body>
</html>
