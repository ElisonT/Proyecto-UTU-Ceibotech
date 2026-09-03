<?php
session_start();
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Helpers/roles.php';

// Solo el Administrador general puede ver este panel.
// Si no hay sesión, o la sesión es de otro rol, se lo manda para afuera.
$esAdmin = !empty($_SESSION['usuario_id']) && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ADMINISTRADOR;
if (!$esAdmin) {
    header('Location: index.php');
    exit;
}

$nombreAdmin     = $_SESSION['usuario_nombre'] ?? 'Administrador';
$inicialesAdmin  = obtenerIniciales($nombreAdmin);
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
  <title>Panel de administración — SGDM</title>
  <!-- styles.css guarda todos los estilos visuales del sitio. -->
  <link rel="stylesheet" href="styles.css?v=25" />
  <!-- Font Awesome aporta los iconos usados en botones, tarjetas y menus. -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="admin-body">
  <!-- BODY: contiene todo lo visible de la pagina: navegacion, contenido principal y pie. -->

  <!-- Botón que abre/cierra el sidebar en pantallas angostas -->
  <button class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Abrir menú" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
  </button>
  <button class="theme-toggle admin-theme-toggle" id="themeToggle" aria-label="Cambiar a modo oscuro">
    <i class="fa-solid fa-moon"></i>
  </button>

  <!-- =====================
       SIDEBAR: menu lateral exclusivo del panel de administracion.
       Permite acceder a dashboard, usuarios, torneos y configuracion.
       ===================== -->
  <aside class="admin-sidebar" id="adminSidebar">

    <!-- LOGO: muestra la imagen y el nombre de la aplicacion dentro del sidebar. -->
    <div class="admin-sidebar-logo">
      <img src="Imagenes/Logo_Pagina.png" alt="Logo" class="nav-logo-img" />
      <span class="nav-logo-text">PrimeCup</span>
    </div>

    <!-- NAVEGACION: enlaces internos del panel administrativo. -->
    <nav class="admin-nav">
      <span class="admin-nav-label">General</span>
      <a href="#dashboard" class="admin-nav-link admin-nav-active">
        <i class="fa-solid fa-gauge"></i> Dashboard
      </a>
      <a href="#usuarios" class="admin-nav-link">
        <i class="fa-solid fa-users"></i> Usuarios
      </a>
      <a href="#torneos" class="admin-nav-link">
        <i class="fa-solid fa-trophy"></i> Torneos
      </a>

      <span class="admin-nav-label">Sistema</span>
      <a href="#modulos" class="admin-nav-link">
        <i class="fa-solid fa-puzzle-piece"></i> Módulos
      </a>
      <a href="#configuracion" class="admin-nav-link">
        <i class="fa-solid fa-gear"></i> Configuración
      </a>
    </nav>

    <!-- ADMIN INFO: datos resumidos del usuario administrador y enlace para salir. -->
    <div class="admin-sidebar-footer">
      <div class="admin-sidebar-user">
        <div class="admin-sidebar-avatar"><?= htmlspecialchars($inicialesAdmin) ?></div>
        <div class="admin-sidebar-info">
          <span class="admin-sidebar-nombre"><?= htmlspecialchars($nombreAdmin) ?></span>
          <span class="admin-sidebar-rol">Super admin</span>
        </div>
      </div>
      <a href="logout.php" class="admin-sidebar-salir">
        <i class="fa-solid fa-right-from-bracket"></i> Salir
      </a>
    </div>

  </aside>

  <!-- CONTENIDO PRINCIPAL: zona derecha donde se muestra la informacion del dashboard. -->
  <main class="admin-main">
    <!-- MAIN: contenido central y especifico de esta pagina. -->

    <!-- ENCABEZADO: titulo de la pantalla y acciones principales de la pagina. -->
    <div class="admin-topbar">
      <div>
        <h1 class="admin-page-titulo">Dashboard</h1>
        <p class="admin-page-subtitulo">Resumen general del sistema</p>
      </div>
      <div class="admin-topbar-acciones">
        <a href="crear-torneo.php" class="btn-primary">
          <i class="fa-solid fa-plus"></i> Nuevo torneo
        </a>
      </div>
    </div>

    <!-- =====================
       ESTADISTICAS: resumen visual con numeros importantes del sistema.
       Ayuda a mostrar actividad en el mockup.
       ===================== -->
    <div class="admin-stats" data-view="dashboard">
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-azul">
          <i class="fa-solid fa-users"></i>
        </div>
        <div class="admin-stat-info">
          <span class="admin-stat-num">1.4k</span>
          <span class="admin-stat-label">Usuarios registrados</span>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-verde">
          <i class="fa-solid fa-trophy"></i>
        </div>
        <div class="admin-stat-info">
          <span class="admin-stat-num">128</span>
          <span class="admin-stat-label">Torneos activos</span>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-naranja">
          <i class="fa-solid fa-calendar-days"></i>
        </div>
        <div class="admin-stat-info">
          <span class="admin-stat-num">342</span>
          <span class="admin-stat-label">Partidos jugados</span>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-rojo">
          <i class="fa-solid fa-flag"></i>
        </div>
        <div class="admin-stat-info">
          <span class="admin-stat-num">3</span>
          <span class="admin-stat-label">Reportes pendientes</span>
        </div>
      </div>
    </div>

    <!-- FILA: USUARIOS + TORNEOS -->
    <div class="admin-fila">

      <!-- USUARIOS RECIENTES -->
      <section class="admin-card" id="usuarios" data-view="dashboard usuarios">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-users"></i> Usuarios recientes</h2>
          <a href="#usuarios" class="section-link">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="admin-tabla">
          <div class="admin-tabla-header">
            <span>Usuario</span>
            <span>Rol</span>
            <span>Estado</span>
            <span>Acciones</span>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-user-info">
              <div class="admin-user-avatar">JG</div>
              <div>
                <span class="admin-user-nombre">Juan García</span>
                <span class="admin-user-email">juan@correo.com</span>
              </div>
            </div>
            <span class="admin-rol admin-rol-organizador">Organizador</span>
            <span class="badge estado-verde" style="margin-top:0;">Activo</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Editar" aria-label="Editar" data-action="editar-usuario"><i class="fa-solid fa-pen"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Suspender" aria-label="Suspender" data-action="suspender-usuario"><i class="fa-solid fa-ban"></i></button>
            </div>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-user-info">
              <div class="admin-user-avatar">MP</div>
              <div>
                <span class="admin-user-nombre">María Pérez</span>
                <span class="admin-user-email">maria@correo.com</span>
              </div>
            </div>
            <span class="admin-rol admin-rol-participante">Participante</span>
            <span class="badge estado-verde" style="margin-top:0;">Activo</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Editar" aria-label="Editar" data-action="editar-usuario"><i class="fa-solid fa-pen"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Suspender" aria-label="Suspender" data-action="suspender-usuario"><i class="fa-solid fa-ban"></i></button>
            </div>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-user-info">
              <div class="admin-user-avatar">CR</div>
              <div>
                <span class="admin-user-nombre">Carlos Rodríguez</span>
                <span class="admin-user-email">carlos@correo.com</span>
              </div>
            </div>
            <span class="admin-rol admin-rol-participante">Participante</span>
            <span class="badge estado-rojo" style="margin-top:0;">Suspendido</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Editar" aria-label="Editar" data-action="editar-usuario"><i class="fa-solid fa-pen"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Eliminar" aria-label="Eliminar" data-action="eliminar-usuario"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-user-info">
              <div class="admin-user-avatar">AL</div>
              <div>
                <span class="admin-user-nombre">Ana López</span>
                <span class="admin-user-email">ana@correo.com</span>
              </div>
            </div>
            <span class="admin-rol admin-rol-organizador">Organizador</span>
            <span class="badge estado-verde" style="margin-top:0;">Activo</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Editar" aria-label="Editar" data-action="editar-usuario"><i class="fa-solid fa-pen"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Suspender" aria-label="Suspender" data-action="suspender-usuario"><i class="fa-solid fa-ban"></i></button>
            </div>
          </div>

        </div>
      </section>

      <!-- TORNEOS RECIENTES -->
      <section class="admin-card" id="torneos" data-view="dashboard torneos">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-trophy"></i> Torneos recientes</h2>
          <a href="asignar-organizador.php" class="section-link"><i class="fa-solid fa-user-tie"></i> Asignar organizador</a>
        </div>
        <div class="admin-tabla">
          <div class="admin-tabla-header admin-tabla-header-torneos">
            <span>Torneo</span>
            <span>Tipo</span>
            <span>Estado</span>
            <span>Acciones</span>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-torneo-info">
              <i class="fa-solid fa-futbol admin-torneo-icon"></i>
              <span class="admin-user-nombre">Mundialito 2026</span>
            </div>
            <span class="admin-tipo">Liga</span>
            <span class="badge estado-naranja" style="margin-top:0;">En curso</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Ver" aria-label="Ver" data-action="ver-torneo"><i class="fa-solid fa-eye"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Eliminar" aria-label="Eliminar" data-action="eliminar-torneo"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-torneo-info">
              <i class="fa-solid fa-chess admin-torneo-icon"></i>
              <span class="admin-user-nombre">Torneo de ajedrez UTU</span>
            </div>
            <span class="admin-tipo">Suizo</span>
            <span class="badge estado-naranja" style="margin-top:0;">En curso</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Ver" aria-label="Ver" data-action="ver-torneo"><i class="fa-solid fa-eye"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Eliminar" aria-label="Eliminar" data-action="eliminar-torneo"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-torneo-info">
              <i class="fa-solid fa-gamepad admin-torneo-icon"></i>
              <span class="admin-user-nombre">CS:2 Gaming Cup</span>
            </div>
            <span class="admin-tipo">Eliminación</span>
            <span class="badge estado-verde" style="margin-top:0;">Abierto</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Ver" aria-label="Ver" data-action="ver-torneo"><i class="fa-solid fa-eye"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Eliminar" aria-label="Eliminar" data-action="eliminar-torneo"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>

          <div class="admin-tabla-fila">
            <div class="admin-torneo-info">
              <i class="fa-solid fa-basketball admin-torneo-icon"></i>
              <span class="admin-user-nombre">Liga Barrial de Básquet</span>
            </div>
            <span class="admin-tipo">Liga</span>
            <span class="badge estado-rojo" style="margin-top:0;">Finalizado</span>
            <div class="admin-acciones">
              <button type="button" class="admin-btn-icono" title="Ver" aria-label="Ver" data-action="ver-torneo"><i class="fa-solid fa-eye"></i></button>
              <button type="button" class="admin-btn-icono admin-btn-danger" title="Eliminar" aria-label="Eliminar" data-action="eliminar-torneo"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>

        </div>
      </section>

    </div>

    <!-- MÓDULOS DE COMPETENCIA: habilitar o deshabilitar formatos de torneo disponibles en la plataforma -->
    <section class="admin-card" id="modulos" data-view="modulos">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-puzzle-piece"></i> Módulos de competencia</h2>
      </div>
      <div class="admin-modulos-lista">

        <div class="admin-modulo-fila">
          <div class="admin-modulo-info">
            <span class="admin-modulo-icono"><i class="fa-solid fa-table-cells"></i></span>
            <div>
              <span class="admin-modulo-nombre">Liga</span>
              <span class="admin-modulo-desc">Todos los equipos se enfrentan entre sí. Se acumulan puntos por resultado.</span>
            </div>
          </div>
          <label class="admin-toggle">
            <input type="checkbox" checked data-modulo="Liga" aria-label="Activar módulo Liga" />
            <span class="admin-toggle-slider"></span>
          </label>
        </div>

        <div class="admin-modulo-fila">
          <div class="admin-modulo-info">
            <span class="admin-modulo-icono"><i class="fa-solid fa-sitemap"></i></span>
            <div>
              <span class="admin-modulo-nombre">Eliminación directa</span>
              <span class="admin-modulo-desc">El perdedor queda eliminado. Se generan llaves automáticamente por ronda.</span>
            </div>
          </div>
          <label class="admin-toggle">
            <input type="checkbox" checked data-modulo="Eliminación directa" aria-label="Activar módulo Eliminación directa" />
            <span class="admin-toggle-slider"></span>
          </label>
        </div>

        <div class="admin-modulo-fila">
          <div class="admin-modulo-info">
            <span class="admin-modulo-icono"><i class="fa-solid fa-shuffle"></i></span>
            <div>
              <span class="admin-modulo-nombre">Sistema suizo</span>
              <span class="admin-modulo-desc">Emparejamiento por rendimiento acumulado, sin eliminar a nadie hasta el final.</span>
            </div>
          </div>
          <label class="admin-toggle">
            <input type="checkbox" checked data-modulo="Sistema suizo" aria-label="Activar módulo Sistema suizo" />
            <span class="admin-toggle-slider"></span>
          </label>
        </div>

      </div>
    </section>

    <!-- ACCESOS RÁPIDOS -->
    <section class="admin-card" data-view="dashboard">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-bolt"></i> Accesos rápidos</h2>
      </div>
      <div class="admin-accesos">
        <a href="crear-torneo.php" class="admin-acceso">
          <i class="fa-solid fa-plus admin-acceso-icon"></i>
          <span>Crear torneo</span>
        </a>
        <a href="#usuarios" class="admin-acceso">
          <i class="fa-solid fa-user-plus admin-acceso-icon"></i>
          <span>Agregar usuario</span>
        </a>
        <a href="#modulos" class="admin-acceso">
          <i class="fa-solid fa-puzzle-piece admin-acceso-icon"></i>
          <span>Gestionar módulos</span>
        </a>
        <a href="#reportes" class="admin-acceso">
          <i class="fa-solid fa-flag admin-acceso-icon"></i>
          <span>Ver reportes</span>
        </a>
        <a href="#configuracion" class="admin-acceso">
          <i class="fa-solid fa-gear admin-acceso-icon"></i>
          <span>Configuración</span>
        </a>
        <a href="#exportar" class="admin-acceso">
          <i class="fa-solid fa-file-export admin-acceso-icon"></i>
          <span>Exportar datos</span>
        </a>
      </div>
    </section>

  </main>

  <script src="script.js?v=25"></script>
</body>
</html>
