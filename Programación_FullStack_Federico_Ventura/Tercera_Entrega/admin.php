<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/csrf.php';
require_once __DIR__ . '/app/Models/Panel.php';
require_once __DIR__ . '/app/Models/Configuracion.php';

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
$configModelo    = new Configuracion();
$modulos         = $configModelo->modulos();
$configGeneral   = $configModelo->todas();
$usuariosRecientes = $panel->listarUsuarios('', '', 1)['filas'];
$torneosRecientes  = $panel->listarTorneos('', '', 1)['filas'];
$usuariosRecientes = array_slice($usuariosRecientes, 0, 5);
$torneosRecientes  = array_slice($torneosRecientes, 0, 5);

$etiquetaEstadoTorneo = ['inscripciones_abiertas' => 'Abierto', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'];
$claseEstadoTorneo    = ['inscripciones_abiertas' => 'estado-verde', 'en_curso' => 'estado-naranja', 'finalizado' => 'estado-azul', 'cancelado' => 'estado-gris'];
$etiquetaFormato      = ['liga' => 'Liga', 'eliminacion' => 'Eliminación', 'suizo' => 'Suizo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <!-- HEAD: contiene informacion para el navegador; no se muestra como contenido principal de la pagina. -->
  <!-- charset define la codificacion para que tildes y eñes se lean correctamente. -->
  <meta charset="UTF-8" />
  <script src="tema.js?v=1"></script>
  <!-- viewport adapta el ancho de la pagina a celulares, tablets y PC. -->
  <?= metaCsrf() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- title es el texto que aparece en la pestaña del navegador. -->
  <title>Panel de administración — SGDM</title>
  <!-- styles.css guarda todos los estilos visuales del sitio. -->
  <link rel="stylesheet" href="styles.css?v=32" />
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
      <a href="#reportes" class="admin-nav-link">
        <i class="fa-solid fa-file-lines"></i> Reportes
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
      <form method="post" action="logout.php" style="margin: 0;"> <?= campoCsrf() ?>
        <button type="submit" class="admin-sidebar-salir" style="background: none; border: 0; padding: 0; font-family: inherit; cursor: pointer;">
          <i class="fa-solid fa-right-from-bracket"></i> Salir
        </button>
      </form>
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
        <button type="button" class="btn-primary" id="btnNuevoUsuario"><i class="fa-solid fa-user-plus"></i> Nuevo usuario</button>
      </div>

      <!-- Formulario de alta y edición: lo envía script.js con fetch a api/admin.php -->
      <form id="formUsuario" class="panel-form" hidden>
        <h3 class="panel-form-titulo" id="tituloFormUsuario">Nuevo usuario</h3>
        <input type="hidden" name="usuario_id" value="" />
        <div class="panel-form-grid">
          <div class="form-group">
            <label class="form-label" for="fu-nombre">Nombre completo</label>
            <input class="form-input" type="text" id="fu-nombre" name="nombre" maxlength="100" placeholder="Nombre y apellido" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="fu-usuario">Nombre de usuario</label>
            <input class="form-input" type="text" id="fu-usuario" name="usuario" maxlength="30" pattern="[A-Za-z0-9_]+" title="Letras, números y guion bajo" autocomplete="off" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="fu-email">Correo electrónico</label>
            <input class="form-input" type="email" id="fu-email" name="email" maxlength="150" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="fu-rol">Rol</label>
            <select class="form-input" id="fu-rol" name="rol_id" required>
              <option value="3">Participante</option>
              <option value="2">Organizador de torneo</option>
              <option value="1">Administrador general</option>
            </select>
          </div>
          <div class="form-group" data-solo-alta>
            <label class="form-label" for="fu-password">Contraseña</label>
            <input class="form-input" type="password" id="fu-password" name="password" maxlength="72" minlength="8"
                   pattern="(?=.*\p{Ll})(?=.*\p{Lu})(?=.*[0-9]).{8,}" title="Entre 8 y 72 caracteres, con una mayúscula, una minúscula y un número"
                   autocomplete="new-password" required />
            <span class="form-hint">Entre 8 y 72 caracteres, con una mayúscula, una minúscula y un número.</span>
          </div>
          <div class="form-group" data-solo-alta>
            <label class="form-label" for="fu-confirm">Repetir contraseña</label>
            <input class="form-input" type="password" id="fu-confirm" name="confirm" maxlength="72" autocomplete="new-password" required />
          </div>
        </div>
        <ul class="panel-form-errores" id="erroresFormUsuario" role="alert" hidden></ul>
        <div class="panel-form-acciones">
          <button type="submit" class="btn-primary">Guardar</button>
          <button type="button" class="btn" id="cancelarFormUsuario">Cancelar</button>
        </div>
      </form>

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
          <option value="cancelado">Cancelados</option>
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

    <!-- REPORTES (letra 5.1): cada uno se abre en una hoja imprimible o se descarga en CSV (reporte.php) -->
    <section class="admin-card" id="reportes" data-view="reportes">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-file-lines"></i> Reportes</h2>
      </div>
      <p class="admin-ayuda">"Ver / imprimir" abre una hoja lista para imprimir o guardar como PDF desde el navegador. "CSV" descarga una planilla que se abre en Excel. Cada reporte que generás queda en la auditoría.</p>

      <h3 class="equipo-subtitulo">Resumen general</h3>
      <form method="get" action="reporte.php" class="panel-filtros">
        <input type="hidden" name="tipo" value="resumen" />
        <button type="submit" name="formato" value="html" class="btn-primary"><i class="fa-solid fa-print"></i> Ver / imprimir</button>
        <button type="submit" name="formato" value="csv" class="btn"><i class="fa-solid fa-file-csv"></i> CSV</button>
      </form>

      <h3 class="equipo-subtitulo">Torneos</h3>
      <form method="get" action="reporte.php" class="panel-filtros">
        <input type="hidden" name="tipo" value="torneos" />
        <select name="estado" aria-label="Estado de los torneos">
          <option value="">Todos los estados</option>
          <option value="inscripciones_abiertas">Inscripciones abiertas</option>
          <option value="en_curso">En curso</option>
          <option value="finalizado">Finalizados</option>
          <option value="cancelado">Cancelados</option>
        </select>
        <button type="submit" name="formato" value="html" class="btn-primary"><i class="fa-solid fa-print"></i> Ver / imprimir</button>
        <button type="submit" name="formato" value="csv" class="btn"><i class="fa-solid fa-file-csv"></i> CSV</button>
      </form>

      <h3 class="equipo-subtitulo">Usuarios</h3>
      <form method="get" action="reporte.php" class="panel-filtros">
        <input type="hidden" name="tipo" value="usuarios" />
        <select name="rol" aria-label="Rol de los usuarios">
          <option value="">Todos los roles</option>
          <option value="1">Administradores</option>
          <option value="2">Organizadores</option>
          <option value="3">Participantes</option>
        </select>
        <select name="estado" aria-label="Estado de las cuentas">
          <option value="">Todos los estados</option>
          <option value="activo">Activos</option>
          <option value="suspendido">Suspendidos</option>
          <option value="eliminado">Dados de baja</option>
        </select>
        <button type="submit" name="formato" value="html" class="btn-primary"><i class="fa-solid fa-print"></i> Ver / imprimir</button>
        <button type="submit" name="formato" value="csv" class="btn"><i class="fa-solid fa-file-csv"></i> CSV</button>
      </form>

      <h3 class="equipo-subtitulo">Auditoría</h3>
      <form method="get" action="reporte.php" class="panel-filtros">
        <input type="hidden" name="tipo" value="auditoria" />
        <input type="date" name="desde" aria-label="Desde" />
        <input type="date" name="hasta" aria-label="Hasta" />
        <select name="accion" aria-label="Acción">
          <option value="">Todas las acciones</option>
          <?php foreach ($panel->accionesDeAuditoria() as $accionAud): ?>
            <option value="<?= escapar($accionAud) ?>"><?= escapar($accionAud) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" name="formato" value="html" class="btn-primary"><i class="fa-solid fa-print"></i> Ver / imprimir</button>
        <button type="submit" name="formato" value="csv" class="btn"><i class="fa-solid fa-file-csv"></i> CSV</button>
      </form>
      <p class="tabla-hint" style="margin: 0;">El reporte de un torneo en particular (participantes, posiciones y resultados) se pide desde la página de ese torneo.</p>
    </section>

    <!-- FORMATOS: cuántos torneos hay de cada formato de competencia (datos reales) -->
    <section class="admin-card" id="modulos" data-view="modulos">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-puzzle-piece"></i> Módulos de competencia</h2>
      </div>
      <p class="admin-ayuda">Un módulo deshabilitado deja de ofrecerse al crear torneos nuevos. Los torneos que ya existen con ese formato siguen hasta terminar. Siempre tiene que quedar al menos un módulo habilitado.</p>
      <div class="admin-modulos-lista" id="listaModulos">
        <?php
        $iconosModulo = ['liga' => 'fa-table-cells', 'eliminacion' => 'fa-sitemap', 'suizo' => 'fa-shuffle'];
        foreach ($modulos as $m): $clave = $m['formato']; ?>
          <div class="admin-modulo-fila">
            <div class="admin-modulo-info">
              <span class="admin-modulo-icono"><i class="fa-solid <?= $iconosModulo[$clave] ?? 'fa-puzzle-piece' ?>"></i></span>
              <div>
                <span class="admin-modulo-nombre"><?= escapar($m['nombre']) ?> <small class="admin-stat-sub">· <?= (int) ($porFormato[$clave] ?? 0) ?> torneo(s)</small></span>
                <span class="admin-modulo-desc"><?= escapar($m['descripcion']) ?></span>
              </div>
            </div>
            <label class="interruptor" title="Habilitar o deshabilitar <?= escapar($m['nombre']) ?>">
              <input type="checkbox" role="switch" data-modulo="<?= escapar($clave) ?>" aria-label="Módulo <?= escapar($m['nombre']) ?> habilitado" <?= (int) $m['habilitado'] === 1 ? 'checked' : '' ?> />
              <span class="interruptor-pista"></span>
              <span class="interruptor-texto"><?= (int) $m['habilitado'] === 1 ? 'Habilitado' : 'Deshabilitado' ?></span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- CONFIGURACIÓN GENERAL (letra 5.1): parámetros que el sistema usa de verdad -->
    <section class="admin-card" id="configuracion" data-view="configuracion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-gear"></i> Configuración general</h2>
      </div>
      <form id="formConfiguracion" class="panel-form">
        <div class="panel-form-grid">
          <div class="form-group">
            <label class="form-label" for="cfg-max">Cupo máximo al crear un torneo</label>
            <input class="form-input" type="number" id="cfg-max" name="max_participantes_por_torneo" min="2" max="512" required
                   value="<?= (int) ($configGeneral['max_participantes_por_torneo'] ?? 512) ?>" />
            <span class="form-hint">Entre 2 y 512. No cambia los torneos ya creados.</span>
          </div>
          <div class="form-group">
            <span class="form-label">Registro de usuarios nuevos</span>
            <label class="interruptor">
              <input type="checkbox" role="switch" id="cfg-registro" aria-label="Permitir el registro público"
                     <?= ($configGeneral['permitir_registro'] ?? '1') !== '0' ? 'checked' : '' ?> />
              <span class="interruptor-pista"></span>
              <span class="interruptor-texto"><?= ($configGeneral['permitir_registro'] ?? '1') !== '0' ? 'Permitido' : 'Solo el administrador crea cuentas' ?></span>
            </label>
            <span class="form-hint">Si se desactiva, la página de registro avisa que está cerrada y las cuentas las crea el administrador desde Usuarios.</span>
          </div>
          <div class="form-group">
            <span class="form-label">Validación de resultados</span>
            <label class="interruptor">
              <input type="checkbox" role="switch" id="cfg-validar" aria-label="Exigir validación de resultados"
                     <?= ($configGeneral['validar_resultados'] ?? '0') === '1' ? 'checked' : '' ?> />
              <span class="interruptor-pista"></span>
              <span class="interruptor-texto"><?= ($configGeneral['validar_resultados'] ?? '0') === '1' ? 'Los resultados de organizadores esperan tu validación' : 'Los resultados valen apenas se cargan' ?></span>
            </label>
            <span class="form-hint">Si se activa, lo que cargue un organizador queda pendiente: no suma a la tabla ni deja avanzar de ronda hasta que un administrador lo valide. Lo que cargues vos vale al instante.</span>
          </div>
        </div>
        <ul class="panel-form-errores" id="erroresFormConfiguracion" role="alert" hidden></ul>
        <div class="panel-form-acciones">
          <button type="submit" class="btn-primary">Guardar configuración</button>
        </div>
      </form>
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
        <a href="#reportes" class="admin-acceso">
          <i class="fa-solid fa-file-lines admin-acceso-icon"></i><span>Reportes</span>
        </a>
      </div>
    </section>

  </main>

  <script src="script.js?v=33"></script>
</body>
</html>
