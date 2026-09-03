<?php
session_start();
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
  <title>Sistema de Gestión Deportiva Modular</title>
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
      <img src="Imagenes/Logo_Pagina.png" alt="Logo_Página"    class="nav-logo-img"/>
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
    <?php $mostrarComoFunciona = true; include __DIR__ . '/app/Views/partials/nav_links.php'; ?>
  </nav>

  <!-- =====================
       HERO: primera seccion visible de la pagina de inicio.
       Presenta el objetivo del sistema y los botones principales.
       ===================== -->
  <section class="hero">
    <h1>Gestioná cualquier competencia desde un solo lugar</h1>
    <p>Torneos deportivos, mentales y electrónicos. Liga, eliminación directa o sistema suizo.</p>
    <div class="hero-actions">
      <a href="busqueda.php" class="btn-primary btn-lg">Explorar torneos</a>
      <?php if ($puedeCrearTorneo): ?>
        <a href="crear-torneo.php" class="btn btn-lg">Crear torneo</a>
      <?php else: ?>
        <a href="busqueda.php" class="btn btn-lg">Unirse a torneos</a>
      <?php endif; ?>
    </div>
  </section>

  <!-- BARRA DE BUSQUEDA: campo principal para escribir el nombre del torneo. -->
  <div class="search-wrap">
    <div class="search-box">
      <input type="text" id="heroBusquedaTexto" placeholder="Buscar torneos..." aria-label="Buscar torneos" />
      <div class="search-selects">
        <div class="custom-select">
          <select id="heroSelectDeporte">
            <option value="">Seleccioná un deporte</option>
            <optgroup label="Deportes tradicionales">
              <option value="futbol11">Fútbol 11</option>
              <option value="futbol5">Fútbol 5</option>
              <option value="basquetbol">Básquetbol</option>
              <option value="tenis">Tenis</option>
              <option value="padel">Pádel</option>
              <option value="pingpong">Ping pong</option>
            </optgroup>
            <optgroup label="Juegos de mesa / carta">
              <option value="ajedrez">Ajedrez</option>
              <option value="truco">Truco</option>
              <option value="damas">Damas</option>
            </optgroup>
            <optgroup label="Videojuegos">
              <option value="cs2">CS2</option>
              <option value="lol">League of Legends</option>
              <option value="fortnite">Fortnite</option>
              <option value="valorant">Valorant</option>
              <option value="rocketleague">Rocket League</option>
            </optgroup>
            <optgroup label="Otro">
              <option value="otro">Otro</option>
            </optgroup>
          </select>
        </div>
        <div class="custom-select">
          <select id="heroSelectTipo">
            <option>Todos los tipos</option>
            <option>Liga</option>
            <option>Eliminación directa</option>
            <option>Sistema suizo</option>
          </select>
        </div>
      </div>
      <a href="busqueda.php" id="btnBuscarHero" class="btn-primary">
        <i class="fa-solid fa-magnifying-glass"></i> Buscar
      </a>
    </div>
  </div>

  <!-- =====================
       ESTADISTICAS: resumen visual con numeros importantes del sistema.
       Ayuda a mostrar actividad en el mockup.
       ===================== -->
  <!-- =====================
       DEPORTES Y DISCIPLINAS: variedad real de lo que se puede gestionar en la plataforma.
       Reemplaza a los numeros de estadisticas, que se pisaban con el resto de la pagina.
       ===================== -->
  <section class="section">
    <div class="section-header section-header-centrado">
      <span class="section-title">Deportes y disciplinas que podés gestionar</span>
    </div>
    <div class="carrusel-wrap">
      <button class="carrusel-flecha" data-carrusel-dir="-1" aria-label="Ver deportes anteriores">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <div class="tarjetas-carrusel tarjetas-carrusel-deportes">

        <div class="chip-deporte" data-deporte="Fútbol 11">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Fútbol 11</span>
        </div>
        <div class="chip-deporte" data-deporte="Fútbol 5">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Fútbol 5</span>
        </div>
        <div class="chip-deporte" data-deporte="Básquetbol">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Básquetbol</span>
        </div>
        <div class="chip-deporte" data-deporte="Tenis">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Tenis</span>
        </div>
        <div class="chip-deporte" data-deporte="Pádel">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Pádel</span>
        </div>
        <div class="chip-deporte" data-deporte="Ping pong">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Ping pong</span>
        </div>
        <div class="chip-deporte" data-deporte="Ajedrez">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Ajedrez</span>
        </div>
        <div class="chip-deporte" data-deporte="Truco">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Truco</span>
        </div>
        <div class="chip-deporte" data-deporte="Damas">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Damas</span>
        </div>
        <div class="chip-deporte" data-deporte="CS2">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">CS2</span>
        </div>
        <div class="chip-deporte" data-deporte="League of Legends">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">League of Legends</span>
        </div>
        <div class="chip-deporte" data-deporte="Fortnite">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Fortnite</span>
        </div>
        <div class="chip-deporte" data-deporte="Valorant">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Valorant</span>
        </div>
        <div class="chip-deporte" data-deporte="Rocket League">
          <span class="chip-deporte-icono"><i class="fa-solid fa-circle"></i></span>
          <span class="chip-deporte-nombre">Rocket League</span>
        </div>

      </div>
      <button class="carrusel-flecha" data-carrusel-dir="1" aria-label="Ver más deportes">
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>
  </section>

  <!-- =====================
       TORNEOS ACTIVOS: tarjetas de torneos destacados o en curso.
       Cada tarjeta funciona como acceso al detalle del torneo.
       ===================== -->
  <section class="section">
    <div class="section-header">
      <span class="section-title">Torneos activos</span>
      <a href="busqueda.php" class="section-link">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="carrusel-wrap">
      <button class="carrusel-flecha" data-carrusel-dir="-1" aria-label="Ver torneos anteriores">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <div class="tarjetas-carrusel">

        <a href="detalle.php?id=1" class="card card-link">
          <div class="card-sport"><i class="fa-solid fa-futbol"></i> Fútbol</div>
          <div class="card-name">Mundialito 2026</div>
          <div class="card-meta">
            <div class="card-row"><i class="fa-solid fa-users"></i> 8 equipos</div>
            <div class="card-row"><i class="fa-solid fa-calendar"></i> Inicia 20 jun</div>
            <div class="card-row"><i class="fa-solid fa-chart-bar"></i> Liga</div>
          </div>
          <span class="badge estado-naranja">En curso — Fecha 1</span>
        </a>

        <a href="detalle.php?id=2" class="card card-link">
          <div class="card-sport"><i class="fa-solid fa-chess"></i> Ajedrez</div>
          <div class="card-name">Torneo de ajedrez</div>
          <div class="card-meta">
            <div class="card-row"><i class="fa-solid fa-users"></i> 128 participantes</div>
            <div class="card-row"><i class="fa-solid fa-calendar"></i> Inicia 28 jul</div>
            <div class="card-row"><i class="fa-solid fa-chart-bar"></i> Sistema suizo</div>
          </div>
          <span class="badge estado-verde">Inscripciones abiertas</span>
        </a>

        <a href="detalle.php?id=3" class="card card-link">
          <div class="card-sport"><i class="fa-solid fa-gamepad"></i> Videojuegos</div>
          <div class="card-name">CS:2 Gaming Cup</div>
          <div class="card-meta">
            <div class="card-row"><i class="fa-solid fa-users"></i> 16 equipos</div>
            <div class="card-row"><i class="fa-solid fa-calendar"></i> 20 jul</div>
            <div class="card-row"><i class="fa-solid fa-chart-bar"></i> Eliminación directa</div>
          </div>
          <span class="badge estado-rojo">Finalizado</span>
        </a>

      </div>
      <button class="carrusel-flecha" data-carrusel-dir="1" aria-label="Ver más torneos">
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>
  </section>

  <!-- =====================
       FORMATOS: explica los tipos de competencia disponibles.
       Se muestran como bloques informativos con icono, nombre y descripcion.
       ===================== -->
  <section class="section">
    <div class="section-header">
      <span class="section-title">Formatos de competencia</span>
    </div>
    <div class="tipos">

      <div class="tipo">
        <div class="tipo-visual tipo-visual-liga">
          <div class="mini-liga-fila">
            <span class="mini-liga-pos">1</span>
            <span class="mini-liga-barra"><span class="mini-liga-fill" style="--valor:88%"></span></span>
          </div>
          <div class="mini-liga-fila">
            <span class="mini-liga-pos">2</span>
            <span class="mini-liga-barra"><span class="mini-liga-fill" style="--valor:64%"></span></span>
          </div>
          <div class="mini-liga-fila">
            <span class="mini-liga-pos">3</span>
            <span class="mini-liga-barra"><span class="mini-liga-fill" style="--valor:40%"></span></span>
          </div>
        </div>
        <div class="tipo-name">Liga</div>
        <div class="tipo-desc">Todos los equipos se enfrentan entre sí. Se acumulan puntos por resultado.</div>
      </div>

      <div class="tipo">
        <div class="tipo-visual tipo-visual-elim">
          <div class="mini-elim-fila mini-elim-r1">
            <span class="mini-elim-chip"></span>
            <span class="mini-elim-chip"></span>
            <span class="mini-elim-chip"></span>
            <span class="mini-elim-chip"></span>
          </div>
          <div class="mini-elim-fila mini-elim-r2">
            <span class="mini-elim-chip"></span>
            <span class="mini-elim-chip"></span>
          </div>
          <div class="mini-elim-fila mini-elim-r3">
            <span class="mini-elim-chip mini-elim-chip-final"><i class="fa-solid fa-trophy"></i></span>
          </div>
        </div>
        <div class="tipo-name">Eliminación directa</div>
        <div class="tipo-desc">El perdedor queda eliminado. Se generan llaves automáticamente por ronda.</div>
      </div>

      <div class="tipo">
        <svg class="mini-suizo" viewBox="0 0 120 80" width="100%" height="64" aria-hidden="true">
          <circle class="mini-suizo-punto" cx="15" cy="14" r="5" />
          <circle class="mini-suizo-punto" cx="60" cy="14" r="5" />
          <circle class="mini-suizo-punto" cx="105" cy="14" r="5" />
          <circle class="mini-suizo-punto" cx="15" cy="66" r="5" />
          <circle class="mini-suizo-punto" cx="60" cy="66" r="5" />
          <circle class="mini-suizo-punto" cx="105" cy="66" r="5" />
          <g class="mini-suizo-ronda mini-suizo-ronda-1">
            <line x1="15" y1="14" x2="60" y2="66" />
            <line x1="60" y1="14" x2="15" y2="66" />
            <line x1="105" y1="14" x2="105" y2="66" />
          </g>
          <g class="mini-suizo-ronda mini-suizo-ronda-2">
            <line x1="15" y1="14" x2="105" y2="66" />
            <line x1="60" y1="14" x2="60" y2="66" />
            <line x1="105" y1="14" x2="15" y2="66" />
          </g>
        </svg>
        <div class="tipo-name">Sistema suizo</div>
        <div class="tipo-desc">Emparejamiento por rendimiento acumulado. Sin eliminar a nadie hasta el final.</div>
      </div>

    </div>
  </section>

  
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
