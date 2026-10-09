<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Enfrentamiento.php';
require_once __DIR__ . '/app/Models/TablaPosiciones.php';
require_once __DIR__ . '/app/Models/Inscripcion.php';
require_once __DIR__ . '/app/Models/Equipo.php';

$puedeCrearTorneo = !empty($_SESSION['usuario_id'])
    && in_array((int) ($_SESSION['usuario_rol'] ?? 0), [ROL_ADMINISTRADOR], true);

$torneoId     = (int) ($_GET['id'] ?? 0);
$modeloTorneo = new Torneo();
$torneo       = $torneoId > 0 ? $modeloTorneo->obtenerPorId($torneoId) : false;
if (!$torneo) {
    header('Location: busqueda.php');
    exit;
}

$rondas         = (new Enfrentamiento())->listarRondasConEnfrentamientos($torneoId);
$inscriptos     = $modeloTorneo->listarInscriptos($torneoId);
$formato        = $torneo['formato'];
$tabla          = ($formato !== 'eliminacion' && count($rondas) > 0)
    ? (new TablaPosiciones())->listarPorTorneo($torneoId)
    : [];
$puedeGestionar = puedeGestionarTorneo($torneo);

// Aviso de la última acción de gestión (lo deja gestionar-torneo.php).
$aviso = $_SESSION['flash_torneo'] ?? null;
unset($_SESSION['flash_torneo']);

$etiquetasFormato = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];
$descripcionesFormato = [
    'liga'        => 'Todos contra todos. Victoria 3 puntos, empate 1. El que más puntos suma al final se lleva el título.',
    'eliminacion' => 'Eliminación directa: el que pierde queda afuera, y siguen los ganadores hasta la final.',
    'suizo'       => 'Sistema suizo: rondas fijas donde se enfrentan participantes con puntaje parecido, sin repetir rival.',
];

$cantidad       = (int) $torneo['cantidad_participantes'];
$unidad         = $torneo['modalidad'] === 'individual' ? 'participantes' : 'equipos';
$totalRondas    = $formato === 'liga'
    ? ($cantidad % 2 === 0 ? $cantidad - 1 : $cantidad)
    : max(1, (int) ceil(log(max($cantidad, 2), 2)));
$rondaActual    = count($rondas);

$partidosJugados   = 0;
$partidosPendientes = 0;
foreach ($rondas as $r) {
    foreach ($r['enfrentamientos'] as $e) {
        if ($e['inscripcion_visitante_id'] === null) {
            continue;
        }
        if ((int) $e['tiene_resultado'] === 1) {
            $partidosJugados++;
        } else {
            $partidosPendientes++;
        }
    }
}

$ultimaRonda   = $rondas ? end($rondas) : null;
$esUltimaRonda = $ultimaRonda !== null && (
    ($formato === 'suizo' && $rondaActual >= $totalRondas)
    || ($formato === 'eliminacion' && count($ultimaRonda['enfrentamientos']) === 1)
);

$campeon = null;
if ($torneo['estado'] === 'finalizado' && !empty($torneo['ganador_inscripcion_id'])) {
    foreach ($inscriptos as $i) {
        if ((int) $i['inscripcion_id'] === (int) $torneo['ganador_inscripcion_id']) {
            $campeon = $i['nombre'];
        }
    }
}

// --- Inscripción: cupos y situación del usuario que mira la página ---
$modeloInscripcion  = new Inscripcion();
$cupos              = isset($torneo['cupos']) ? (int) $torneo['cupos'] : null; // NULL = sin límite
$jugadoresPorEquipo = isset($torneo['jugadores_por_equipo']) ? (int) $torneo['jugadores_por_equipo'] : null;
$hayCupo            = $cupos === null || $cantidad < $cupos;
$haySesion          = !empty($_SESSION['usuario_id']);
$esParticipante     = $haySesion && (int) ($_SESSION['usuario_rol'] ?? 0) === ROL_PARTICIPANTE;
$miInscripcion      = $esParticipante
    ? $modeloInscripcion->obtenerActivaDeUsuario($torneoId, (int) $_SESSION['usuario_id'])
    : false;
// Equipos que el usuario capitanea (los únicos que puede inscribir a este torneo).
$equiposComoCapitan = ($esParticipante && !$miInscripcion
        && $torneo['modalidad'] === 'equipo' && $torneo['estado'] === 'inscripciones_abiertas')
    ? array_values(array_filter(
        (new Equipo())->listarComoCapitan((int) $_SESSION['usuario_id']),
        fn ($eq) => $eq['deporte'] === $torneo['deporte']   // solo equipos del deporte del torneo
    ))
    : [];
$textoCupos         = $cupos !== null ? ' Quedan ' . max(0, $cupos - $cantidad) . ' de ' . $cupos . ' cupos.' : '';

$tituloPagina = $torneo['nombre'] . ' — PrimeCup';
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

  <!-- CABECERA DEL TORNEO: datos reales leídos de la base. -->
  <div class="detalle-hero">
    <div class="detalle-hero-inner">

      <a href="busqueda.php" class="config-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver a torneos
      </a>

      <div class="detalle-hero-content">

        <div class="detalle-hero-img" data-deporte="<?= escapar($torneo['deporte']) ?>">
          <i class="fa-solid fa-trophy"></i>
        </div>

        <div class="detalle-hero-info">
          <div class="detalle-categoria"><?= escapar($torneo['deporte']) ?> · <?= escapar($etiquetasFormato[$formato]) ?></div>
          <h1 class="detalle-titulo"><?= escapar($torneo['nombre']) ?></h1>
          <p class="detalle-desc"><?= escapar($descripcionesFormato[$formato]) ?></p>
          <div class="detalle-meta">
            <span><i class="fa-solid fa-user"></i>
              <?php if ($torneo['nombre_organizador']): ?>
                Organizado por <strong><?= escapar($torneo['nombre_organizador']) ?></strong>
              <?php else: ?>
                Sin organizador asignado
              <?php endif; ?>
            </span>
            <span><i class="fa-solid fa-calendar"></i> <?= escapar(formatearFecha($torneo['fecha_inicio'])) ?></span>
            <span><i class="fa-solid fa-users"></i> <?= $cantidad ?><?= $cupos !== null ? ' / ' . $cupos : '' ?> <?= $unidad ?></span>
          </div>
        </div>

        <div class="detalle-hero-accion">
          <?php if ($torneo['estado'] === 'inscripciones_abiertas'): ?>
            <span class="badge estado-verde" style="margin-top:0; font-size: 13px; padding: 5px 14px;">
              <i class="fa-solid fa-circle-check"></i> Inscripciones abiertas
            </span>
          <?php elseif ($torneo['estado'] === 'en_curso'): ?>
            <span class="badge estado-naranja" style="margin-top:0; font-size: 13px; padding: 5px 14px;">
              <i class="fa-solid fa-circle-play"></i> En curso — Ronda <?= $rondaActual ?><?= $formato !== 'eliminacion' ? ' de ' . $totalRondas : '' ?>
            </span>
          <?php else: ?>
            <span class="badge estado-rojo" style="margin-top:0; font-size: 13px; padding: 5px 14px;">
              <i class="fa-solid fa-flag-checkered"></i> Finalizado
            </span>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <!-- INSCRIPCIÓN: formularios reales (los procesa inscribirse.php). -->
  <div class="detalle-inscripcion-wrap">
    <?php if ($torneo['estado'] === 'inscripciones_abiertas'): ?>
      <div class="detalle-inscripcion<?= $miInscripcion ? ' inscripto' : '' ?>">

        <?php if (!$haySesion): ?>
          <div class="detalle-inscripcion-texto">
            <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos) ?> Iniciá sesión para anotarte.
          </div>
          <div class="detalle-inscripcion-acciones">
            <a href="login.php" class="btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</a>
          </div>

        <?php elseif (!$esParticipante): ?>
          <div class="detalle-inscripcion-texto">
            <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos) ?> Solo las cuentas de participante pueden inscribirse.
          </div>

        <?php elseif ($miInscripcion): ?>
          <?php $puedeRetirar = $miInscripcion['equipo_id'] === null
              || (int) $miInscripcion['capitan_id'] === (int) $_SESSION['usuario_id']; ?>
          <div class="detalle-inscripcion-texto">
            <i class="fa-solid fa-circle-check"></i>
            Ya estás inscripto<?= $miInscripcion['nombre_equipo'] ? ' con el equipo <strong>' . escapar($miInscripcion['nombre_equipo']) . '</strong>' : '' ?>.
            <?= !$puedeRetirar ? 'Solo el capitán puede retirar al equipo del torneo.' : '' ?>
          </div>
          <?php if ($puedeRetirar): ?>
            <div class="detalle-inscripcion-acciones">
              <form method="post" action="inscribirse.php"
                    onsubmit="return confirm('<?= $miInscripcion['equipo_id'] === null ? '¿Seguro que querés darte de baja de este torneo?' : '¿Retirar al equipo de este torneo?' ?>');">
                <input type="hidden" name="accion" value="darme_de_baja" />
                <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
                <button type="submit" class="btn">
                  <i class="fa-solid fa-user-minus"></i> <?= $miInscripcion['equipo_id'] === null ? 'Darme de baja' : 'Retirar al equipo' ?>
                </button>
              </form>
            </div>
          <?php endif; ?>

        <?php elseif ($torneo['modalidad'] === 'individual'): ?>
          <?php if (!$hayCupo): ?>
            <div class="detalle-inscripcion-texto"><i class="fa-solid fa-triangle-exclamation"></i> No quedan cupos disponibles.</div>
          <?php else: ?>
            <div class="detalle-inscripcion-texto">
              <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos) ?>
            </div>
            <div class="detalle-inscripcion-acciones">
              <form method="post" action="inscribirse.php"
                    onsubmit="return confirm('¿Confirmás tu inscripción a este torneo?');">
                <input type="hidden" name="accion" value="inscribirme" />
                <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
                <button type="submit" class="btn-primary"><i class="fa-solid fa-user-plus"></i> Inscribirme</button>
              </form>
            </div>
          <?php endif; ?>

        <?php else: ?>
          <?php if (!$hayCupo): ?>
            <div class="detalle-inscripcion-texto"><i class="fa-solid fa-triangle-exclamation"></i> No quedan cupos disponibles.</div>
          <?php elseif (empty($equiposComoCapitan)): ?>
            <div class="detalle-inscripcion-texto">
              <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos) ?>
              Este torneo es por equipos: el capitán inscribe al equipo. Armá uno de <?= escapar($torneo['deporte']) ?> en "Mis equipos"<?= $jugadoresPorEquipo !== null ? ' (equipos de ' . $jugadoresPorEquipo . ' jugadores)' : '' ?> e invitá a tus compañeros.
            </div>
            <div class="detalle-inscripcion-acciones">
              <a href="mis-equipos.php" class="btn-primary"><i class="fa-solid fa-people-group"></i> Armar mi equipo</a>
            </div>
          <?php else: ?>
            <?php
              $hayEquipoApto = false;
              foreach ($equiposComoCapitan as $eqc) {
                  if ((int) $eqc['miembros'] === (int) $eqc['tamano']) {   // plantel completo
                      $hayEquipoApto = true;
                  }
              }
            ?>
            <div class="detalle-inscripcion-texto">
              <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos) ?>
              <?= $jugadoresPorEquipo !== null ? escapar($torneo['deporte']) . ' se juega en equipos de ' . $jugadoresPorEquipo . ': el plantel tiene que estar completo.' : '' ?>
            </div>
            <div class="detalle-inscripcion-acciones">
              <form method="post" action="inscribirse.php" class="equipo-fila-acciones"
                    onsubmit="return confirm('¿Inscribir a este equipo al torneo?');">
                <input type="hidden" name="accion" value="inscribir_equipo" />
                <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
                <select name="equipo_id" class="form-input-p" required aria-label="Equipo a inscribir">
                  <option value="">Elegí uno de tus equipos...</option>
                  <?php foreach ($equiposComoCapitan as $eqc):
                      $apto = (int) $eqc['miembros'] === (int) $eqc['tamano']; ?>
                    <option value="<?= (int) $eqc['id'] ?>" <?= $apto ? '' : 'disabled' ?>>
                      <?= escapar($eqc['nombre_equipo']) ?> (<?= (int) $eqc['miembros'] ?>/<?= (int) $eqc['tamano'] ?> integrantes)<?= $apto ? '' : ' — incompleto' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary" <?= $hayEquipoApto ? '' : 'disabled' ?>>
                  <i class="fa-solid fa-user-plus"></i> Inscribir equipo
                </button>
                <a href="mis-equipos.php" class="btn"><i class="fa-solid fa-people-group"></i> Mis equipos</a>
              </form>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      </div>
    <?php elseif ($torneo['estado'] === 'en_curso'): ?>
      <div class="detalle-inscripcion cerrado">
        <div class="detalle-inscripcion-texto">
          <i class="fa-solid fa-lock"></i> Las inscripciones para este torneo ya cerraron.<?= $miInscripcion ? ' Estás participando.' : '' ?>
        </div>
      </div>
    <?php else: ?>
      <div class="detalle-inscripcion cerrado">
        <div class="detalle-inscripcion-texto">
          <i class="fa-solid fa-flag-checkered"></i> Este torneo ya finalizó<?= $campeon ? '. Campeón: <strong>' . escapar($campeon) . '</strong>' : '' ?>.
        </div>
      </div>
    <?php endif; ?>
  </div>

  <main class="detalle-main">

    <?php if ($aviso): ?>
      <?php foreach ($aviso['mensajes'] as $mensaje): ?>
        <div class="detalle-aviso <?= $aviso['ok'] ? 'detalle-aviso-ok' : 'detalle-aviso-error' ?>" role="status">
          <?= escapar($mensaje) ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($puedeGestionar && $torneo['estado'] !== 'finalizado'): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-screwdriver-wrench"></i> Gestión del torneo</h2>
        </div>
        <div class="gestion-acciones">
          <?php if ($torneo['estado'] === 'inscripciones_abiertas'): ?>
            <form method="post" action="gestionar-torneo.php"
                  onsubmit="return confirm('Se cierran las inscripciones y se genera el fixture con <?= $cantidad ?> <?= $unidad ?>. ¿Seguimos?');">
              <input type="hidden" name="accion" value="generar_fixture" />
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-primary" <?= $cantidad < 2 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-shuffle"></i> Cerrar inscripciones y generar fixture
              </button>
            </form>
            <?php if ($cantidad < 2): ?>
              <span class="tabla-hint">Hacen falta al menos 2 inscriptos.</span>
            <?php endif; ?>
          <?php elseif ($formato === 'liga'): ?>
            <span class="tabla-hint">Cargá los resultados en cada partido. La liga se cierra sola al cargar el último.</span>
          <?php else: ?>
            <form method="post" action="gestionar-torneo.php">
              <input type="hidden" name="accion" value="avanzar_ronda" />
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-primary" <?= $partidosPendientes > 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-forward-step"></i>
                <?= $esUltimaRonda ? 'Cerrar torneo' : 'Avanzar a la siguiente ronda' ?>
              </button>
            </form>
            <?php if ($partidosPendientes > 0): ?>
              <span class="tabla-hint">Faltan <?= $partidosPendientes ?> resultado(s) de la ronda actual.</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <div class="perfil-stats">
      <div class="pstat">
        <div class="pstat-num"><?= $cantidad ?></div>
        <div class="pstat-label"><?= $unidad === 'equipos' ? 'Equipos' : 'Participantes' ?></div>
      </div>
      <div class="pstat">
        <div class="pstat-num"><?= $partidosJugados ?></div>
        <div class="pstat-label">Partidos jugados</div>
      </div>
      <div class="pstat">
        <div class="pstat-num"><?= $partidosPendientes ?></div>
        <div class="pstat-label">Partidos pendientes</div>
      </div>
      <div class="pstat">
        <div class="pstat-num"><?= $rondaActual ?></div>
        <div class="pstat-label">Ronda actual</div>
      </div>
    </div>

    <?php if ($formato !== 'eliminacion'): ?>
      <section class="perfil-seccion" id="seccionTablaPosiciones">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-table"></i> Tabla de posiciones</h2>
        </div>
        <?php if (empty($tabla)): ?>
          <p class="tabla-sin-datos">Este torneo todavía no comenzó — la tabla se arma cuando se juegue la primera ronda.</p>
        <?php else: ?>
          <div class="tabla-wrap">
            <table class="tabla-posiciones">
              <thead>
                <tr>
                  <th class="tabla-pos">#</th>
                  <th class="tabla-equipo"><?= $unidad === 'equipos' ? 'Equipo' : 'Participante' ?></th>
                  <th>PJ</th>
                  <th>G</th>
                  <th>E</th>
                  <th>P</th>
                  <th class="tabla-pts">PTS</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tabla as $indice => $fila): $pos = $indice + 1; ?>
                  <tr class="<?= $pos === 1 ? 'tabla-lider' : '' ?>">
                    <td class="tabla-pos"><span class="pos-num<?= $pos <= 3 ? ' pos-' . $pos : '' ?>"><?= $pos ?></span></td>
                    <td class="tabla-equipo"><?= escapar($fila['nombre']) ?></td>
                    <td><?= (int) $fila['partidos_jugados'] ?></td>
                    <td><?= (int) $fila['partidos_ganados'] ?></td>
                    <td><?= (int) $fila['partidos_empatados'] ?></td>
                    <td><?= (int) $fila['partidos_perdidos'] ?></td>
                    <td class="tabla-pts"><strong><?= (int) $fila['puntos'] ?></strong></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="tabla-leyenda">
            <span class="leyenda-item"><span class="leyenda-color leyenda-lider"></span> Líder</span>
            <span class="leyenda-sep">·</span>
            <span class="tabla-hint">PJ jugados · G ganados · E empatados · P perdidos · PTS puntos</span>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="perfil-seccion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-calendar-days"></i> <?= $formato === 'eliminacion' ? 'Cuadro' : 'Últimas rondas' ?></h2>
        <?php if (!empty($rondas)): ?>
          <a href="fechas.php?id=<?= $torneoId ?>" class="section-link">Ver todas las rondas <i class="fa-solid fa-arrow-right"></i></a>
        <?php endif; ?>
      </div>

      <?php if (empty($rondas)): ?>
        <p class="tabla-sin-datos">Todavía no se generó el fixture. Se arma cuando cierran las inscripciones.</p>
      <?php else: ?>
        <?php foreach (array_slice($rondas, -2) as $ronda): ?>
          <?php include __DIR__ . '/app/Views/partials/ronda.php'; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="perfil-seccion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-users"></i> <?= $unidad === 'equipos' ? 'Equipos participantes' : 'Participantes' ?></h2>
        <span class="detalle-cupos-label"><?= $cantidad ?> inscriptos</span>
      </div>
      <div class="participantes-grid">
        <?php if (empty($inscriptos)): ?>
          <p class="tabla-sin-datos">Todavía no hay inscriptos.</p>
        <?php else: ?>
          <?php foreach ($inscriptos as $i): ?>
            <div class="participante-item">
              <i class="fa-solid <?= $unidad === 'equipos' ? 'fa-people-group' : 'fa-user' ?>"></i> <?= escapar($i['nombre']) ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

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
