<?php
session_start();
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Models/Torneo.php';

// Ver "mis torneos" es una acción del Organizador de torneo (ver letra 5.2):
// gestiona los torneos que se le asignaron, no crea torneos nuevos.
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rolActual = (int) ($_SESSION['usuario_rol'] ?? 0);
if ($rolActual !== ROL_ORGANIZADOR) {
    header('Location: index.php');
    exit;
}

$torneosAsignados = (new Torneo())->listarAsignados((int) $_SESSION['usuario_id']);

// Mismos mapeos de formato/estado que ya se usan en perfil.php, para que
// las tarjetas se vean igual en todo el sitio.
$etiquetasFormato = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];
$etiquetasEstado  = [
    'inscripciones_abiertas' => ['texto' => 'Inscripciones abiertas', 'clase' => 'estado-verde'],
    'en_curso'               => ['texto' => 'En curso',               'clase' => 'estado-naranja'],
    'finalizado'             => ['texto' => 'Finalizado',             'clase' => 'estado-rojo'],
];

$puedeCrearTorneo = in_array($rolActual, [ROL_ADMINISTRADOR], true);
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
  <title>Torneos que organizo — SGDM</title>
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
        <h2 class="seccion-titulo"><i class="fa-solid fa-user-tie"></i> Torneos que organizo</h2>
      </div>

      <p class="form-hint-p" style="margin-bottom:1rem;">
        Estos son los torneos que un administrador te asignó para gestionar.
        Próximamente vas a poder inscribir participantes, generar rondas y cargar resultados desde acá.
      </p>

      <?php if (empty($torneosAsignados)): ?>
        <p class="form-hint-p">
          Todavía no tenés torneos asignados. Un administrador te va a asignar uno cuando corresponda.
        </p>
      <?php else: ?>
        <div class="cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:14px;">
          <?php foreach ($torneosAsignados as $torneo):
            $estadoInfo = $etiquetasEstado[$torneo['estado']] ?? $etiquetasEstado['inscripciones_abiertas'];
            $fechaTexto = $torneo['fecha_inicio']
                ? (new DateTime($torneo['fecha_inicio']))->format('d/m/Y')
                : 'A confirmar';
          ?>
            <a href="detalle.php?id=<?= (int) $torneo['id'] ?>" class="card card-link">
              <div class="card-sport" data-deporte="<?= htmlspecialchars($torneo['deporte']) ?>">
                <i class="fa-solid fa-trophy"></i> <?= htmlspecialchars($torneo['deporte']) ?>
              </div>
              <div class="card-name"><?= htmlspecialchars($torneo['nombre']) ?></div>
              <div class="card-meta">
                <div class="card-row"><i class="fa-solid fa-users"></i> <?= (int) $torneo['cantidad_participantes'] ?> participantes</div>
                <div class="card-row"><i class="fa-solid fa-calendar"></i> <?= htmlspecialchars($fechaTexto) ?></div>
                <div class="card-row"><i class="fa-solid fa-chart-bar"></i> <?= htmlspecialchars($etiquetasFormato[$torneo['formato']] ?? $torneo['formato']) ?></div>
              </div>
              <span class="badge <?= $estadoInfo['clase'] ?>"><?= htmlspecialchars($estadoInfo['texto']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

<script src="script.js?v=25"></script>
</body>
</html>
