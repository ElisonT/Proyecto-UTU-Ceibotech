<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Models/Torneo.php';

$torneos = (new Torneo())->listarParaBusqueda();
$etiquetasFormato = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];
$etiquetasEstado  = [
    'inscripciones_abiertas' => ['texto' => 'Inscripciones abiertas', 'clase' => 'estado-verde'],
    'en_curso'               => ['texto' => 'En curso',               'clase' => 'estado-naranja'],
    'finalizado'             => ['texto' => 'Finalizado',             'clase' => 'estado-rojo'],
];
$descripcionesFormato = [
    'liga'        => 'Todos contra todos, puntos por resultado.',
    'eliminacion' => 'Eliminación directa: el que pierde queda afuera.',
    'suizo'       => 'Sistema suizo: rondas fijas, sin repetir rival.',
];

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
  <link rel="stylesheet" href="styles.css?v=26" />
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
        <span><?= count($torneos) ?> torneos encontrados</span>
      </div>

    </div>
  </div>

  <!-- =====================
       RESULTADOS: listado de tarjetas encontradas.
       Cada resultado resume deporte, formato, fecha, cupos y estado.
       ===================== -->
  <main class="busqueda-main">
    <!-- TARJETAS: una por torneo real de la base. Los data-* los usa el filtrado de script.js. -->
    <?php foreach ($torneos as $t):
      $estado   = $etiquetasEstado[$t['estado']];
      $formato  = $etiquetasFormato[$t['formato']];
      $unidad   = $t['modalidad'] === 'individual' ? 'participantes' : 'equipos';
      $fechaTxt = $t['estado'] === 'finalizado'
          ? 'Finalizado'
          : formatearFecha($t['fecha_inicio']);
    ?>
    <a href="detalle.php?id=<?= (int) $t['id'] ?>" class="resultado-card"
       data-deporte="<?= escapar($t['deporte']) ?>"
       data-tipo="<?= escapar($formato) ?>"
       data-estado="<?= escapar($estado['texto']) ?>">
      <div class="resultado-img"><i class="fa-solid fa-trophy"></i></div>
      <div class="resultado-info">
        <div class="resultado-categoria"><?= escapar($t['deporte']) ?> · <?= escapar($formato) ?></div>
        <h2 class="resultado-nombre"><?= escapar($t['nombre']) ?></h2>
        <p class="resultado-desc"><?= escapar($descripcionesFormato[$t['formato']]) ?></p>
        <div class="resultado-meta">
          <span><i class="fa-solid fa-users"></i> <?= (int) $t['cantidad_participantes'] ?> <?= $unidad ?></span>
          <span><i class="fa-solid fa-calendar"></i> <?= escapar($fechaTxt) ?></span>
        </div>
      </div>
      <div class="resultado-accion">
        <span class="badge <?= $estado['clase'] ?>"><?= escapar($estado['texto']) ?></span>
        <span class="resultado-ver">Ver torneo <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>
    <?php endforeach; ?>

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

<script src="script.js?v=26"></script>
</body>
</html>