<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/csrf.php';
require_once __DIR__ . '/app/Models/Panel.php';

// Solo el Administrador general puede ver este panel.
// Si no hay sesión, o la sesión es de otro rol, se lo manda para afuera.
$esAdmin = !empty($_SESSION['usuario_id']) && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ADMINISTRADOR;
if (!$esAdmin) {
    header('Location: index.php');
    exit;
}

$nombreAdmin     = $_SESSION['usuario_nombre'] ?? 'Administrador';
$inicialesAdmin  = obtenerIniciales($nombreAdmin);

// Datos reales: los números salen de la vista v_resumen_admin y las listas de recientes
// de las tablas (ver app/Models/Panel.php). El resto lo pide script.js con fetch a api/admin.php.
$panel           = new Panel();
$resumen         = $panel->resumen();
$porFormato      = $panel->torneosPorFormato();
$usuariosRecientes = $panel->listarUsuarios('', '', 1)['filas'];
$torneosRecientes  = $panel->listarTorneos('', '', 1)['filas'];
$usuariosRecientes = array_slice($usuariosRecientes, 0, 5);
$torneosRecientes  = array_slice($torneosRecientes, 0, 5);

$etiquetaEstadoTorneo = ['inscripciones_abiertas' => 'Abierto', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado'];
$claseEstadoTorneo    = ['inscripciones_abiertas' => 'estado-verde', 'en_curso' => 'estado-naranja', 'finalizado' => 'estado-azul'];
$etiquetaFormato      = ['liga' => 'Liga', 'eliminacion' => 'Eliminación', 'suizo' => 'Suizo'];
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
  <?= metaCsrf() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- title es el texto que aparece en la pestaña del navegador. -->
  <title>Panel de administración — SGDM</title>
  <!-- styles.css guarda todos los estilos visuales del sitio. -->
  <link rel="stylesheet" href="styles.css?v=29" />
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

      <a href="#auditoria" class="admin-nav-link">
        <i class="fa-solid fa-clock-rotate-left"></i> Auditoría
      </a>

      <span class="admin-nav-label">Sistema</span>
      <a href="#modulos" class="admin-nav-link">
        <i class="fa-solid fa-puzzle-piece"></i> Formatos
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
  <main class="admin-main" data-yo="<?= (int) $_SESSION['usuario_id'] ?>">

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

    <!-- ESTADÍSTICAS: salen de la vista v_resumen_admin (datos reales). -->
    <div class="admin-stats" data-view="dashboard">
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-azul"><i class="fa-solid fa-users"></i></div>
        <div class="admin-stat-info">
          <span class="admin-stat-num" data-resumen="usuarios_activos"><?= (int) $resumen['usuarios_activos'] ?></span>
          <span class="admin-stat-label">Usuarios activos</span>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-verde"><i class="fa-solid fa-trophy"></i></div>
        <div class="admin-stat-info">
          <span class="admin-stat-num"><?= (int) $resumen['torneos_en_curso'] ?> <small class="admin-stat-sub">de <?= (int) $resumen['torneos_total'] ?></small></span>
          <span class="admin-stat-label">Torneos en curso</span>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-naranja"><i class="fa-solid fa-calendar-days"></i></div>
        <div class="admin-stat-info">
          <span class="admin-stat-num"><?= (int) $resumen['partidos_jugados'] ?></span>
          <span class="admin-stat-label">Partidos jugados</span>
        </div>
      </div>
      <div class="admin-stat-card">
        <div class="admin-stat-icon estado-rojo"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="admin-stat-info">
          <span class="admin-stat-num"><?= (int) $resumen['partidos_pendientes'] ?></span>
          <span class="admin-stat-label">Partidos pendientes</span>
        </div>
      </div>
    </div>

    <?php if ((int) $resumen['torneos_sin_organizador'] > 0): ?>
      <div class="detalle-aviso detalle-aviso-error" data-view="dashboard" role="status">
        <?= (int) $resumen['torneos_sin_organizador'] ?> torneo(s) sin organizador asignado.
        <a href="asignar-organizador.php">Asignar ahora</a>
      </div>
    <?php endif; ?>

    <div class="admin-fila">

      <!-- USUARIOS RECIENTES (dashboard) -->
      <section class="admin-card" data-view="dashboard">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-users"></i> Usuarios recientes</h2>
          <a href="#usuarios" class="section-link">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="panel-lista">
          <?php foreach ($usuariosRecientes as $u): ?>
            <div class="panel-fila">
              <div class="panel-fila-main">
                <span class="admin-user-nombre"><?= escapar($u['nombre_completo']) ?></span>
                <span class="admin-user-email">@<?= escapar($u['nombre_usuario']) ?></span>
              </div>
              <span class="admin-rol <?= (int) $u['rol_id'] === 2 ? 'admin-rol-organizador' : 'admin-rol-participante' ?>"><?= escapar($u['rol']) ?></span>
              <?php if ((int) $u['eliminado'] === 1): ?>
                <span class="badge estado-gris" style="margin-top:0;">Baja</span>
              <?php elseif ((int) $u['activo'] === 1): ?>
                <span class="badge estado-verde" style="margin-top:0;">Activo</span>
              <?php else: ?>
                <span class="badge estado-rojo" style="margin-top:0;">Suspendido</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- TORNEOS RECIENTES (dashboard) -->
      <section class="admin-card" data-view="dashboard">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-trophy"></i> Torneos recientes</h2>
          <a href="#torneos" class="section-link">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="panel-lista">
          <?php foreach ($torneosRecientes as $t): ?>
            <div class="panel-fila">
              <div class="panel-fila-main">
                <a class="admin-user-nombre" href="detalle.php?id=<?= (int) $t['id'] ?>"><?= escapar($t['nombre']) ?></a>
                <span class="admin-user-email"><?= escapar($t['deporte']) ?> · <?= escapar($etiquetaFormato[$t['formato']] ?? $t['formato']) ?></span>
              </div>
              <span class="badge <?= $claseEstadoTorneo[$t['estado']] ?? '' ?>" style="margin-top:0;"><?= escapar($etiquetaEstadoTorneo[$t['estado']] ?? $t['estado']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

    </div>

    <!-- USUARIOS: lista completa con búsqueda, filtro y paginación (se carga con fetch) -->
    <section class="admin-card" id="usuarios" data-view="usuarios">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-users"></i> Usuarios</h2>
      </div>
      <div class="panel-filtros" data-panel="usuarios">
        <input type="search" data-filtro="q" placeholder="Buscar por nombre, usuario o correo" aria-label="Buscar usuarios" />
        <select data-filtro="estado" aria-label="Filtrar por estado">
          <option value="">Todos</option>
          <option value="activo">Activos</option>
          <option value="suspendido">Suspendidos</option>
          <option value="eliminado">Dados de baja</option>
        </select>
      </div>
      <div class="panel-lista" id="listaUsuarios" aria-live="polite"></div>
      <div class="panel-pag" data-paginacion="usuarios"></div>
    </section>

    <!-- TORNEOS -->
    <section class="admin-card" id="torneos" data-view="torneos">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-trophy"></i> Torneos</h2>
        <a href="asignar-organizador.php" class="section-link"><i class="fa-solid fa-user-tie"></i> Asignar organizador</a>
      </div>
      <div class="panel-filtros" data-panel="torneos">
        <input type="search" data-filtro="q" placeholder="Buscar por nombre o deporte" aria-label="Buscar torneos" />
        <select data-filtro="estado" aria-label="Filtrar por estado">
          <option value="">Todos</option>
          <option value="inscripciones_abiertas">Inscripciones abiertas</option>
          <option value="en_curso">En curso</option>
          <option value="finalizado">Finalizados</option>
        </select>
      </div>
      <div class="panel-lista" id="listaTorneos" aria-live="polite"></div>
      <div class="panel-pag" data-paginacion="torneos"></div>
    </section>

    <!-- AUDITORÍA: historial de todo lo que se modifica (letra 4.1 y 14), vista v_auditoria_detalle -->
    <section class="admin-card" id="auditoria" data-view="auditoria">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-clock-rotate-left"></i> Historial de auditoría</h2>
        <span class="tabla-hint"><?= (int) $resumen['acciones_hoy'] ?> acción(es) hoy · <?= (int) $resumen['resultados_corregidos'] ?> resultado(s) corregido(s)</span>
      </div>
      <div class="panel-filtros" data-panel="auditoria">
        <input type="search" data-filtro="q" placeholder="Buscar en el detalle o por usuario" aria-label="Buscar en la auditoría" />
        <select data-filtro="filtro" aria-label="Filtrar por acción">
          <option value="">Todas las acciones</option>
          <?php foreach ($panel->accionesDeAuditoria() as $accionAud): ?>
            <option value="<?= escapar($accionAud) ?>"><?= escapar($accionAud) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="panel-lista" id="listaAuditoria" aria-live="polite"></div>
      <div class="panel-pag" data-paginacion="auditoria"></div>
    </section>

    <!-- FORMATOS: cuántos torneos hay de cada formato de competencia (datos reales) -->
    <section class="admin-card" id="modulos" data-view="modulos">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-puzzle-piece"></i> Formatos de competencia</h2>
      </div>
      <div class="admin-modulos-lista">
        <?php
        $formatos = [
          ['liga', 'Liga', 'fa-table-cells', 'Todos contra todos. Victoria 3 puntos, empate 1.'],
          ['eliminacion', 'Eliminación directa', 'fa-sitemap', 'El perdedor queda eliminado; se arma una ronda por vez, con byes si hace falta.'],
          ['suizo', 'Sistema suizo', 'fa-shuffle', 'Emparejamiento por puntos sin repetir rival; cantidad de rondas fija.'],
        ];
        foreach ($formatos as [$clave, $nombre, $icono, $descripcion]): ?>
          <div class="admin-modulo-fila">
            <div class="admin-modulo-info">
              <span class="admin-modulo-icono"><i class="fa-solid <?= $icono ?>"></i></span>
              <div>
                <span class="admin-modulo-nombre"><?= $nombre ?></span>
                <span class="admin-modulo-desc"><?= $descripcion ?></span>
              </div>
            </div>
            <span class="badge estado-azul" style="margin-top:0;"><?= (int) ($porFormato[$clave] ?? 0) ?> torneo(s)</span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="admin-card" data-view="dashboard">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-bolt"></i> Accesos rápidos</h2>
      </div>
      <div class="admin-accesos">
        <a href="crear-torneo.php" class="admin-acceso">
          <i class="fa-solid fa-plus admin-acceso-icon"></i><span>Crear torneo</span>
        </a>
        <a href="asignar-organizador.php" class="admin-acceso">
          <i class="fa-solid fa-user-tie admin-acceso-icon"></i><span>Asignar organizador</span>
        </a>
        <a href="#usuarios" class="admin-acceso">
          <i class="fa-solid fa-users admin-acceso-icon"></i><span>Gestionar usuarios</span>
        </a>
        <a href="#auditoria" class="admin-acceso">
          <i class="fa-solid fa-clock-rotate-left admin-acceso-icon"></i><span>Ver auditoría</span>
        </a>
      </div>
    </section>

  </main>

  <script src="script.js?v=28"></script>
</body>
</html>
