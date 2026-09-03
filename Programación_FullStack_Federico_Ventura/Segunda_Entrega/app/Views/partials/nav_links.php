<?php
/**
 * app/Views/partials/nav_links.php
 *
 * Se incluye desde adentro de <nav class="nav">...</nav> en cada página.
 * Asume que la página que lo incluye ya llamó a session_start() antes.
 *
 * Variable opcional que la página puede definir antes del include:
 *   $mostrarComoFunciona = true;  -> agrega el link "Cómo funciona"
 *
 * Roles (ver bd/sgdm_schema.sql): 1 = Administrador general,
 * 2 = Organizador de torneo, 3 = Participante.
 */

require_once __DIR__ . '/../../Helpers/texto.php';
require_once __DIR__ . '/../../Helpers/roles.php';

$haySesion = !empty($_SESSION['usuario_id']);
$esAdmin   = $haySesion && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ADMINISTRADOR;
$esOrganizador = $haySesion && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ORGANIZADOR;
$puedeCrearTorneo = $haySesion && in_array((int) ($_SESSION['usuario_rol'] ?? 0), [ROL_ADMINISTRADOR], true);
?>
<div class="nav-links" id="navLinks">
  <a href="busqueda.php"><i class="fa-solid fa-trophy nav-link-icon"></i>Torneos</a>

  <?php if ($puedeCrearTorneo): ?>
    <a href="crear-torneo.php"><i class="fa-solid fa-plus nav-link-icon"></i>Crear torneo</a>
  <?php endif; ?>

  <?php if ($esOrganizador): ?>
    <a href="mis-torneos.php"><i class="fa-solid fa-user-tie nav-link-icon"></i>Torneos que organizo</a>
  <?php endif; ?>

  <?php if (!empty($mostrarComoFunciona)): ?>
    <a href="como-funciona.php"><i class="fa-solid fa-circle-info nav-link-icon"></i>Cómo funciona</a>
  <?php endif; ?>

  <?php if (!$haySesion): ?>
    <a href="login.php" class="nav-link-visible"><i class="fa-solid fa-right-to-bracket nav-link-icon"></i>Iniciar sesión</a>
    <a href="register.php" class="btn-primary"><i class="fa-solid fa-user-plus nav-link-icon"></i>Registrarse</a>
  <?php else: ?>
    <a href="perfil.php" class="nav-avatar" title="Mi perfil">
      <?php if (!empty($_SESSION['usuario_foto'])): ?>
        <span class="nav-avatar-circle" style="background-image:url('<?= htmlspecialchars($_SESSION['usuario_foto']) ?>'); background-size:cover; background-position:center;"></span>
      <?php else: ?>
        <span class="nav-avatar-circle"><?= htmlspecialchars(obtenerIniciales($_SESSION['usuario_nombre'] ?? '')) ?></span>
      <?php endif; ?>
      <span class="nav-link-label">Mi perfil</span>
    </a>

    <?php if ($esAdmin): ?>
      <a href="admin.php" class="nav-admin" title="Panel de administración">
        <span class="nav-admin-badge"><i class="fa-solid fa-shield"></i></span>
        <span class="nav-link-label">Administración</span>
      </a>
    <?php endif; ?>

    <a href="logout.php" class="nav-link-visible" title="Cerrar sesión">
      <i class="fa-solid fa-right-from-bracket nav-link-icon"></i>Cerrar sesión
    </a>
  <?php endif; ?>
</div>
