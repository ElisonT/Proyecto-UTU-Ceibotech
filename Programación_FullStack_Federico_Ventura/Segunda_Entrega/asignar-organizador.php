<?php
session_start();
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Usuario.php';

// Asignar un organizador a un torneo es una tarea del Administrador general
// (ver letra 5.1): el organizador gestiona el torneo, pero no lo crea ni
// decide quién lo gestiona; eso lo hace el admin.
$esAdmin = !empty($_SESSION['usuario_id']) && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_ADMINISTRADOR;
if (!$esAdmin) {
    header('Location: index.php');
    exit;
}

$modeloTorneo  = new Torneo();
$modeloUsuario = new Usuario();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $torneoId      = (int) ($_POST['torneo_id'] ?? 0);
    $organizadorId = ($_POST['organizador_id'] ?? '') !== '' ? (int) $_POST['organizador_id'] : null;

    if ($torneoId > 0) {
        $modeloTorneo->asignarOrganizador($torneoId, $organizadorId);
    }
    header('Location: asignar-organizador.php?actualizado=1');
    exit;
}

$actualizado    = isset($_GET['actualizado']);
$recienCreado   = isset($_GET['creado']);
$torneos        = $modeloTorneo->listarTodos();
$organizadores  = $modeloUsuario->listarPorRol(ROL_ORGANIZADOR);
$mostrarComoFunciona = false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <script>
    (function () {
      if (localStorage.getItem('sgdm-tema') === 'oscuro') {
        document.documentElement.classList.add('modo-oscuro');
      }
    })();
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Asignar organizador — SGDM</title>
  <link rel="stylesheet" href="styles.css?v=25" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>

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
    <?php include __DIR__ . '/app/Views/partials/nav_links.php'; ?>
  </nav>

  <main class="perfil-main">
    <section class="perfil-seccion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-user-tie"></i> Asignar organizador a torneos</h2>
        <a href="admin.php" class="section-link"><i class="fa-solid fa-arrow-left"></i> Volver al panel</a>
      </div>

      <p class="form-hint-p" style="margin-bottom:1rem;">
        El Administrador general crea los torneos; acá se le puede asignar un Organizador
        para que lo gestione (inscribir participantes, generar rondas, cargar resultados).
      </p>

      <?php if ($recienCreado): ?>
        <div class="form-alert form-alert-success" style="display:flex;">
          <i class="fa-solid fa-circle-check"></i>
          <span>Torneo creado correctamente. Ahora podés asignarle un organizador.</span>
        </div>
      <?php elseif ($actualizado): ?>
        <div class="form-alert form-alert-success" style="display:flex;">
          <i class="fa-solid fa-circle-check"></i>
          <span>Asignación guardada correctamente.</span>
        </div>
      <?php endif; ?>

      <?php if (empty($torneos)): ?>
        <p class="form-hint-p">Todavía no hay torneos creados.</p>
      <?php else: ?>
        <div class="tabla-wrap">
          <table class="tabla-posiciones">
            <thead>
              <tr>
                <th>Torneo</th>
                <th>Creado por</th>
                <th colspan="2">Organizador asignado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($torneos as $torneo): ?>
                <tr>
                  <td><?= htmlspecialchars($torneo['nombre']) ?></td>
                  <td><?= htmlspecialchars($torneo['nombre_creador']) ?></td>
                  <td colspan="2">
                    <form method="post" action="asignar-organizador.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <input type="hidden" name="torneo_id" value="<?= (int) $torneo['id'] ?>" />
                      <select name="organizador_id" class="form-input-p" style="padding:6px 10px; font-size:13px; width:auto;">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($organizadores as $organizador): ?>
                          <option value="<?= (int) $organizador['id'] ?>"
                            <?= (int) $torneo['organizador_asignado_id'] === (int) $organizador['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($organizador['nombre_completo']) ?> (@<?= htmlspecialchars($organizador['nombre_usuario']) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn btn-editar">Guardar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (empty($organizadores)): ?>
          <p class="form-hint-p" style="margin-top:0.75rem;">
            Todavía no hay ningún usuario con rol de Organizador de torneo.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>

<script src="script.js?v=25"></script>
</body>
</html>
