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
  <title>Buscar torneos — SGDM</title>
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
       ENCABEZADO Y FILTROS: titulo de busqueda, caja de texto y selectores.
       Sirve para representar como el usuario encontraria torneos.
       ===================== -->
  <div class="busqueda-header">
    <div class="busqueda-header-inner">
      <h1 class="busqueda-titulo">Buscar torneos</h1>

      <!-- BARRA DE BUSQUEDA: campo principal para escribir el nombre del torneo. -->
      <div class="busqueda-barra">
        <div class="busqueda-input-wrap">
          <i class="fa-solid fa-magnifying-glass busqueda-icon"></i>
          <input type="text" id="busquedaTexto" placeholder="Buscar por nombre de torneo..." class="busqueda-input" aria-label="Buscar por nombre de torneo" />
        </div>
        <button type="button" id="btnBuscarTexto" class="btn-primary">Buscar</button>
      </div>

      <!-- FILTROS: selectores para limitar resultados por deporte, tipo y estado. -->
      <div class="busqueda-filtros">
        <div class="custom-select">
          <select class="filtro-select" id="filtroDeporte">
            <option value="">Todos los deportes</option>
            <optgroup label="Deportes tradicionales">
              <option>Fútbol 11</option>
              <option>Fútbol 5</option>
              <option>Básquetbol</option>
              <option>Tenis</option>
              <option>Pádel</option>
              <option>Ping pong</option>
            </optgroup>
            <optgroup label="Juegos de mesa / carta">
              <option>Ajedrez</option>
              <option>Truco</option>
              <option>Damas</option>
            </optgroup>
            <optgroup label="Videojuegos">
              <option>CS2</option>
              <option>League of Legends</option>
              <option>Fortnite</option>
              <option>Valorant</option>
              <option>Rocket League</option>
            </optgroup>
            <optgroup label="Otro">
              <option>Otro</option>
            </optgroup>
          </select>
        </div>

        <div class="custom-select">
          <select class="filtro-select" id="filtroTipo">
            <option value="">Todos los tipos</option>
            <option>Liga</option>
            <option>Eliminación directa</option>
            <option>Sistema suizo</option>
          </select>
        </div>

        <div class="custom-select">
          <select class="filtro-select" id="filtroEstado">
            <option value="">Todos los estados</option>
            <option>Inscripciones abiertas</option>
            <option>En curso</option>
            <option>Finalizado</option>
          </select>
        </div>
      </div>

      <!-- CONTADOR DE RESULTADOS: texto que indica cuantos torneos coinciden con la busqueda. -->
      <div class="busqueda-count">
        <span>6 torneos encontrados</span>
      </div>

    </div>
  </div>

  <!-- =====================
       RESULTADOS: listado de tarjetas encontradas.
       Cada resultado resume deporte, formato, fecha, cupos y estado.
       ===================== -->
  <main class="busqueda-main">
    <!-- MAIN: contenido central y especifico de esta pagina. -->

    <!-- RESULTADO 1: tarjeta de ejemplo de un torneo encontrado. -->
    <a href="detalle.php?id=1" class="resultado-card" data-deporte="Fútbol 11" data-tipo="Liga" data-estado="En curso">
      <div class="resultado-img resultado-img-futbol">
        <i class="fa-solid fa-futbol"></i>
      </div>
      <div class="resultado-info">
        <div class="resultado-categoria">Fútbol 11 · Liga</div>
        <h2 class="resultado-nombre">Mundialito 2026</h2>
        <p class="resultado-desc">Torneo de fútbol 11 para equipos amateur. Todos contra todos, puntos por victoria.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 8 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> 15 jun — 30 ago 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-naranja">En curso · Fecha 4</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 2: segunda tarjeta de ejemplo del listado. -->
    <a href="detalle.php?id=2" class="resultado-card" data-deporte="Ajedrez" data-tipo="Sistema suizo" data-estado="Inscripciones abiertas">
      <div class="resultado-img resultado-img-ajedrez">
        <i class="fa-solid fa-chess"></i>
      </div>
      <div class="resultado-info">
        <div class="resultado-categoria">Ajedrez · Sistema suizo</div>
        <h2 class="resultado-nombre">Torneo de ajedrez UTU</h2>
        <p class="resultado-desc">Competencia de ajedrez por sistema suizo. Rondas semanales, clasificación acumulada.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 128 participantes</span>
          <span><i class="fa-solid fa-calendar"></i> 28 jul</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 3: tercera tarjeta de ejemplo del listado. -->
    <a href="detalle.php?id=3" class="resultado-card" data-deporte="CS2" data-tipo="Eliminación directa" data-estado="Finalizado">
      <div class="resultado-img resultado-img-cs">
        <i class="fa-solid fa-gamepad"></i>
      </div>
      <div class="resultado-info">
        <div class="resultado-categoria">CS2 · Eliminación directa</div>
        <h2 class="resultado-nombre">CS:2 Gaming Cup</h2>
        <p class="resultado-desc">Copa de Counter-Strike 2 en formato eliminación directa. 16 equipos, llaves automáticas.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 16 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> Finalizado 20 jun</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-rojo">Finalizado</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 4: cuarta tarjeta de ejemplo del listado. -->
    <a href="detalle.php?id=4" class="resultado-card" data-deporte="Básquetbol" data-tipo="Liga" data-estado="Inscripciones abiertas">
      <div class="resultado-img resultado-img-basquet">
        <i class="fa-solid fa-basketball"></i>
      </div>
      <div class="resultado-info">
        <div class="resultado-categoria">Básquetbol · Liga</div>
        <h2 class="resultado-nombre">Liga Barrial de Básquet 2026</h2>
        <p class="resultado-desc">Liga de básquetbol barrial, abierta a equipos de todo Montevideo.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 8 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> 1 jul — 15 sep 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 5: quinta tarjeta de ejemplo del listado. -->
    <a href="detalle.php?id=5" class="resultado-card" data-deporte="Valorant" data-tipo="Eliminación directa" data-estado="Inscripciones abiertas">
      <div class="resultado-img resultado-img-valorant">
        <i class="fa-solid fa-crosshairs"></i>
      </div>
      <div class="resultado-info">
        <div class="resultado-categoria">Valorant · Eliminación directa</div>
        <h2 class="resultado-nombre">Valorant Open UY</h2>
        <p class="resultado-desc">Torneo abierto de Valorant para equipos uruguayos. Cupos limitados.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 32 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> 10 jul 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 6: sexta tarjeta de ejemplo del listado. -->
    <a href="detalle.php?id=6" class="resultado-card" data-deporte="Tenis" data-tipo="Eliminación directa" data-estado="Finalizado">
      <div class="resultado-img resultado-img-tenis">
        <i class="fa-solid fa-table-tennis-paddle-ball"></i>
      </div>
      <div class="resultado-info">
        <div class="resultado-categoria">Tenis · Eliminación directa</div>
        <h2 class="resultado-nombre">Copa Tenis Arias 2025</h2>
        <p class="resultado-desc">Torneo de tenis individual, categorías A y B. Ya finalizado.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 24 participantes</span>
          <span><i class="fa-solid fa-calendar"></i> Oct — Nov 2025</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-rojo">Finalizado</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 7 -->
    <a href="detalle.php?id=7" class="resultado-card" data-deporte="Fútbol 5" data-tipo="Sistema suizo" data-estado="Inscripciones abiertas">
      <div class="resultado-img"><i class="fa-solid fa-futbol"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Fútbol 5 · Sistema suizo</div>
        <h2 class="resultado-nombre">Copa Fútbol 5 Ciudad Vieja</h2>
        <p class="resultado-desc">Torneo de fútbol 5 entre equipos del barrio Ciudad Vieja. Formato suizo a 5 rondas.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 12 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> 5 ago 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 8 -->
    <a href="detalle.php?id=8" class="resultado-card" data-deporte="Pádel" data-tipo="Eliminación directa" data-estado="En curso">
      <div class="resultado-img"><i class="fa-solid fa-table-tennis-paddle-ball"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Pádel · Eliminación directa</div>
        <h2 class="resultado-nombre">Open de Pádel Punta Carretas</h2>
        <p class="resultado-desc">Torneo de pádel en parejas, eliminación directa desde octavos.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 16 parejas</span>
          <span><i class="fa-solid fa-calendar"></i> En curso · Fecha 2</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-naranja">En curso</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 9 -->
    <a href="detalle.php?id=9" class="resultado-card" data-deporte="Ping pong" data-tipo="Liga" data-estado="Inscripciones abiertas">
      <div class="resultado-img"><i class="fa-solid fa-table-tennis-paddle-ball"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Ping pong · Liga</div>
        <h2 class="resultado-nombre">Liga de Ping Pong ITS</h2>
        <p class="resultado-desc">Liga interna de ping pong entre estudiantes del instituto.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 10 participantes</span>
          <span><i class="fa-solid fa-calendar"></i> 12 ago 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 10 -->
    <a href="detalle.php?id=10" class="resultado-card" data-deporte="Truco" data-tipo="Sistema suizo" data-estado="Inscripciones abiertas">
      <div class="resultado-img"><i class="fa-solid fa-heart"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Truco · Sistema suizo</div>
        <h2 class="resultado-nombre">Torneo de Truco Amistoso</h2>
        <p class="resultado-desc">Competencia de truco en parejas, sistema suizo, clasificación por puntos.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 20 parejas</span>
          <span><i class="fa-solid fa-calendar"></i> 20 ago 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 11 -->
    <a href="detalle.php?id=11" class="resultado-card" data-deporte="Damas" data-tipo="Eliminación directa" data-estado="Finalizado">
      <div class="resultado-img"><i class="fa-solid fa-chess-board"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Damas · Eliminación directa</div>
        <h2 class="resultado-nombre">Copa Damas Clásicas</h2>
        <p class="resultado-desc">Torneo de damas 1 contra 1, eliminación directa a partido único.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 32 participantes</span>
          <span><i class="fa-solid fa-calendar"></i> Finalizado 5 may</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-rojo">Finalizado</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 12 -->
    <a href="detalle.php?id=12" class="resultado-card" data-deporte="League of Legends" data-tipo="Liga" data-estado="En curso">
      <div class="resultado-img"><i class="fa-solid fa-dragon"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">League of Legends · Liga</div>
        <h2 class="resultado-nombre">LoL PrimeCup Season 1</h2>
        <p class="resultado-desc">Liga de League of Legends 5 contra 5 entre equipos amateurs.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 10 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> En curso · Fecha 6</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-naranja">En curso</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 13 -->
    <a href="detalle.php?id=13" class="resultado-card" data-deporte="Fortnite" data-tipo="Eliminación directa" data-estado="Inscripciones abiertas">
      <div class="resultado-img"><i class="fa-solid fa-explosion"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Fortnite · Eliminación directa</div>
        <h2 class="resultado-nombre">Fortnite Solo Showdown</h2>
        <p class="resultado-desc">Torneo de Fortnite en modalidad solo, eliminación directa por puntaje.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 50 participantes</span>
          <span><i class="fa-solid fa-calendar"></i> 18 ago 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 14 -->
    <a href="detalle.php?id=14" class="resultado-card" data-deporte="Rocket League" data-tipo="Sistema suizo" data-estado="Inscripciones abiertas">
      <div class="resultado-img"><i class="fa-solid fa-rocket"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Rocket League · Sistema suizo</div>
        <h2 class="resultado-nombre">Rocket League 3v3 Cup</h2>
        <p class="resultado-desc">Torneo de Rocket League en equipos de 3, sistema suizo.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 12 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> 25 ago 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- RESULTADO 15 -->
    <a href="detalle.php?id=15" class="resultado-card" data-deporte="Otro" data-tipo="Liga" data-estado="Inscripciones abiertas">
      <div class="resultado-img"><i class="fa-solid fa-shapes"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria">Otro · Liga</div>
        <h2 class="resultado-nombre">Torneo Libre Multideporte</h2>
        <p class="resultado-desc">Espacio para competencias que no encajan en las categorías tradicionales.</p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> 6 equipos</span>
          <span><i class="fa-solid fa-calendar"></i> 1 sep 2026</span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge estado-verde">Inscripciones abiertas</span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>

    <!-- PAGINACIÓN: navegación entre páginas de resultados. -->
    <!-- ESTADO VACÍO: se muestra solo si ningún torneo coincide con los filtros elegidos. -->
    <div class="busqueda-vacio" id="busquedaVacio" style="display:none;">
      <i class="fa-solid fa-magnifying-glass busqueda-vacio-icono"></i>
      <p class="busqueda-vacio-titulo">No se encontraron torneos</p>
      <p class="busqueda-vacio-desc">Probá cambiando los filtros o buscando otro nombre.</p>
    </div>

    <nav class="pagination" id="paginacion" aria-label="Paginación de resultados">
      <button type="button" class="pagination-btn" id="paginaAnterior" aria-label="Página anterior">
        <i class="fa-solid fa-chevron-left"></i>
      </button>
      <div id="paginacionNumeros" class="pagination-numeros"></div>
      <button type="button" class="pagination-btn" id="paginaSiguiente" aria-label="Página siguiente">
        <i class="fa-solid fa-chevron-right"></i>
      </button>
    </nav>

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