<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Enfrentamiento.php';

$puedeCrearTorneo = !empty($_SESSION['usuario_id'])
    && in_array((int) ($_SESSION['usuario_rol'] ?? 0), [ROL_ADMINISTRADOR], true);

$torneoId = (int) ($_GET['id'] ?? 0);
$torneo   = $torneoId > 0 ? (new Torneo())->obtenerPorId($torneoId) : false;
if (!$torneo) {
    header('Location: busqueda.php');
    exit;
}

$rondas         = (new Enfrentamiento())->listarRondasConEnfrentamientos($torneoId);
$puedeGestionar = puedeGestionarTorneo($torneo);
$etiquetasFormato = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];
$unidad = $torneo['modalidad'] === 'individual' ? 'participantes' : 'equipos';

$rondasJugadas = 0;
$rondasEnCurso = 0;
$partidosJugados = 0;
foreach ($rondas as $r) {
    $total = 0;
    $jug = 0;
    foreach ($r['enfrentamientos'] as $e) {
        if ($e['inscripcion_visitante_id'] === null) {
            continue;
        }
        $total++;
        if ((int) $e['tiene_resultado'] === 1) {
            $jug++;
        }
    }
    $partidosJugados += $jug;
    if ($jug === $total) {
        $rondasJugadas++;
    } else {
        $rondasEnCurso++;
    }
}

$aviso = $_SESSION['flash_torneo'] ?? null;
unset($_SESSION['flash_torneo']);

$tituloPagina = 'Calendario — ' . $torneo['nombre'] . ' — PrimeCup';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <script>
    // Aplica el modo oscuro ANTES de que se pinte la página, para evitar el
    // destello blanco al cargar/cambiar de página.
    (function () {
      if (localStorage.getItem('sgdm-tema') === 'oscuro') {
        document.documentElement.classList.add('modo-oscuro');
      }
    })();
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= escapar($tituloPagina) ?></title>
  <link rel="stylesheet" href="styles.css?v=26" />
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
    <?php $mostrarComoFunciona = false; include __DIR__ . '/app/Views/partials/nav_links.php'; ?>
  </nav>

  <main class="detalle-main">

    <div class="config-encabezado">
      <a href="detalle.php?id=<?= $torneoId ?>" class="config-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al torneo
      </a>
      <h1 class="config-titulo">Calendario completo</h1>
      <p class="config-subtitulo">
        <?= escapar($torneo['nombre']) ?> · <?= escapar($torneo['deporte']) ?> · <?= escapar($etiquetasFormato[$torneo['formato']]) ?>
        · <?= (int) $torneo['cantidad_participantes'] ?> <?= $unidad ?> · <?= count($rondas) ?> ronda(s) generada(s)
      </p>
    </div>

    <?php if ($aviso): ?>
      <?php foreach ($aviso['mensajes'] as $mensaje): ?>
        <div class="detalle-aviso <?= $aviso['ok'] ? 'detalle-aviso-ok' : 'detalle-aviso-error' ?>" role="status">
          <?= escapar($mensaje) ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="perfil-stats">
      <div class="pstat">
        <div class="pstat-num"><?= $rondasJugadas ?></div>
        <div class="pstat-label">Rondas jugadas</div>
      </div>
      <div class="pstat">
        <div class="pstat-num"><?= $rondasEnCurso ?></div>
        <div class="pstat-label">Ronda en curso</div>
      </div>
      <div class="pstat">
        <div class="pstat-num"><?= count($rondas) ?></div>
        <div class="pstat-label">Rondas generadas</div>
      </div>
      <div class="pstat">
        <div class="pstat-num"><?= $partidosJugados ?></div>
        <div class="pstat-label">Partidos jugados</div>
      </div>
    </div>

    <?php if (empty($rondas)): ?>
      <p class="tabla-sin-datos">Este torneo todavía no tiene fixture — se genera cuando cierran las inscripciones.</p>
    <?php else: ?>
      <?php foreach ($rondas as $ronda): ?>
        <section class="perfil-seccion">
          <?php include __DIR__ . '/app/Views/partials/ronda.php'; ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

  </main>

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
