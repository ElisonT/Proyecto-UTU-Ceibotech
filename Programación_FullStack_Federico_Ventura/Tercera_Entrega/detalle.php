<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/csrf.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Enfrentamiento.php';
require_once __DIR__ . '/app/Models/TablaPosiciones.php';
require_once __DIR__ . '/app/Models/Inscripcion.php';
require_once __DIR__ . '/app/Models/Equipo.php';
require_once __DIR__ . '/app/Helpers/inscripcion_fechas.php';

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
$puedeCorregir  = puedeCorregirResultados($torneo);
$puedeVerReporte = $puedeGestionar;                 // el reporte se puede pedir también de un torneo cancelado
if ($torneo['estado'] === 'cancelado') {            // un torneo cancelado queda solo para consulta
    $puedeGestionar = false;
    $puedeCorregir  = false;
}
$esAdmin        = esAdministrador();
$pendientesValidacion = $puedeGestionar ? count((new Enfrentamiento())->listarPendientesDeValidacion($torneoId)) : 0;

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
        fn ($eq) => mb_strtolower((string) $eq['deporte']) === mb_strtolower((string) $torneo['deporte'])   // solo equipos del deporte del torneo
    ))
    : [];
// Equipos que quien gestiona el torneo puede inscribir (letra 5.2).
$puedeInscribirOrg  = $puedeGestionar && $torneo['estado'] === 'inscripciones_abiertas';
$equiposInscribibles = ($puedeInscribirOrg && $torneo['modalidad'] === 'equipo')
    ? (new Equipo())->listarInscribiblesEnTorneo($torneo)
    : [];
$ventana            = ventanaDeInscripcion($torneo);
$textoFechas        = $ventana['cierra'] !== null ? ' Hasta el ' . formatearFecha($ventana['cierra']) . '.' : '';
$textoCupos         = $cupos !== null ? ' Quedan ' . max(0, $cupos - $cantidad) . ' de ' . $cupos . ' cupos.' : '';

$tituloPagina = $torneo['nombre'] . ' — PrimeCup';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <script src="tema.js?v=1"></script>
  <?= metaCsrf() ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= escapar($tituloPagina) ?></title>
  <link rel="stylesheet" href="styles.css?v=32" />
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
            <span><i class="fa-solid fa-calendar"></i> <?= escapar(formatearFecha($torneo['fecha_inicio'])) ?><?= !empty($torneo['fecha_fin']) ? ' al ' . escapar(formatearFecha($torneo['fecha_fin'])) : '' ?></span>
            <span><i class="fa-solid fa-users"></i> <?= $cantidad ?><?= $cupos !== null ? ' / ' . $cupos : '' ?> <?= $unidad ?></span>
            <?php if (textoPremio($torneo) !== null): ?>
              <span><i class="fa-solid fa-gift"></i> Premio: <strong><?= escapar(textoPremio($torneo)) ?></strong></span>
            <?php endif; ?>
            <?php if ($torneo['visibilidad'] === 'privado'): ?>
              <span title="No aparece en la portada ni en las búsquedas: se llega con el link directo"><i class="fa-solid fa-lock"></i> Torneo privado</span>
            <?php endif; ?>
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
          <?php elseif ($torneo['estado'] === 'cancelado'): ?>
            <span class="badge estado-gris" style="margin-top:0; font-size: 13px; padding: 5px 14px;">
              <i class="fa-solid fa-ban"></i> Cancelado
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

        <?php if ($ventana['estado'] !== 'abierta' && !$miInscripcion): ?>
          <div class="detalle-inscripcion-texto">
            <i class="fa-solid fa-lock"></i> <?= escapar($ventana['mensaje']) ?>
            <?= $ventana['estado'] === 'cerrada' ? 'El organizador va a armar el fixture en cualquier momento.' : '' ?>
          </div>

        <?php elseif (!$haySesion): ?>
          <div class="detalle-inscripcion-texto">
            <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos . $textoFechas) ?> Iniciá sesión para anotarte.
          </div>
          <div class="detalle-inscripcion-acciones">
            <a href="login.php" class="btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</a>
          </div>

        <?php elseif (!$esParticipante): ?>
          <div class="detalle-inscripcion-texto">
            <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos . $textoFechas) ?> Solo las cuentas de participante pueden inscribirse.
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
                    data-confirmar="<?= escapar($miInscripcion['equipo_id'] === null ? '¿Seguro que querés darte de baja de este torneo?' : '¿Retirar al equipo de este torneo?') ?>"> <?= campoCsrf() ?>
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
              <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos . $textoFechas) ?>
            </div>
            <div class="detalle-inscripcion-acciones">
              <form method="post" action="inscribirse.php"
                    data-confirmar="¿Confirmás tu inscripción a este torneo?"> <?= campoCsrf() ?>
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
              <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos . $textoFechas) ?>
              Este torneo es por equipos: el capitán inscribe al equipo. Armá uno de <?= escapar($torneo['deporte']) ?> en "Mis equipos"<?= $jugadoresPorEquipo !== null ? ' (equipos de ' . $jugadoresPorEquipo . ' jugadores)' : '' ?> e invitá a tus compañeros.
            </div>
            <div class="detalle-inscripcion-acciones">
              <a href="mis-equipos.php" class="btn-primary"><i class="fa-solid fa-people-group"></i> Armar mi equipo</a>
            </div>
          <?php else: ?>
            <?php
              $hayEquipoApto = false;
              foreach ($equiposComoCapitan as $eqc) {
                  if ((int) $eqc['miembros'] === (int) $eqc['tamano']                      // plantel completo
                      && ($jugadoresPorEquipo === null || (int) $eqc['tamano'] === $jugadoresPorEquipo)) {
                      $hayEquipoApto = true;
                  }
              }
            ?>
            <div class="detalle-inscripcion-texto">
              <i class="fa-solid fa-circle-info"></i> Inscripciones abiertas.<?= escapar($textoCupos . $textoFechas) ?>
              <?= $jugadoresPorEquipo !== null ? escapar($torneo['deporte']) . ' se juega en equipos de ' . $jugadoresPorEquipo . ': el plantel tiene que estar completo.' : '' ?>
            </div>
            <div class="detalle-inscripcion-acciones">
              <form method="post" action="inscribirse.php" class="equipo-fila-acciones"
                    data-confirmar="¿Inscribir a este equipo al torneo?"> <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="inscribir_equipo" />
                <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
                <select name="equipo_id" class="form-input-p" required aria-label="Equipo a inscribir">
                  <option value="">Elegí uno de tus equipos...</option>
                  <?php foreach ($equiposComoCapitan as $eqc):
                      $apto = (int) $eqc['miembros'] === (int) $eqc['tamano']
                          && ($jugadoresPorEquipo === null || (int) $eqc['tamano'] === $jugadoresPorEquipo); ?>
                    <option value="<?= (int) $eqc['id'] ?>" <?= $apto ? '' : 'disabled' ?>>
                      <?= escapar($eqc['nombre_equipo']) ?> (<?= (int) $eqc['miembros'] ?>/<?= (int) $eqc['tamano'] ?> integrantes)<?= $apto ? '' : (($jugadoresPorEquipo !== null && (int) $eqc['tamano'] !== $jugadoresPorEquipo) ? ' — el torneo pide equipos de ' . $jugadoresPorEquipo : ' — incompleto') ?>
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
                  data-confirmar="Se cierran las inscripciones y se genera el fixture con <?= $cantidad ?> <?= $unidad ?>. ¿Seguimos?">
              <input type="hidden" name="accion" value="generar_fixture" />
              <?= campoCsrf() ?>
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-primary" <?= $cantidad < 2 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-shuffle"></i> Cerrar inscripciones y generar fixture
              </button>
            </form>
            <?php if ($cantidad < 2): ?>
              <span class="tabla-hint">Hacen falta al menos 2 inscriptos.</span>
            <?php elseif ($ventana['estado'] === 'abierta' && $ventana['cierra'] !== null): ?>
              <span class="tabla-hint">Las inscripciones siguen abiertas hasta el <?= escapar(formatearFecha($ventana['cierra'])) ?>: si generás el fixture ahora, se cierran antes.</span>
            <?php elseif ($ventana['estado'] === 'no_abrio'): ?>
              <span class="tabla-hint"><?= escapar($ventana['mensaje']) ?> Si generás el fixture ahora, no se van a poder inscribir más.</span>
            <?php endif; ?>
          <?php elseif ($formato === 'liga'): ?>
            <span class="tabla-hint">Cargá los resultados en cada partido. La liga se cierra sola al cargar el último.</span>
          <?php else: ?>
            <form method="post" action="gestionar-torneo.php">
              <input type="hidden" name="accion" value="avanzar_ronda" />
              <?= campoCsrf() ?>
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-primary" <?= $partidosPendientes > 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-forward-step"></i>
                <?= $esUltimaRonda ? 'Cerrar torneo' : 'Avanzar a la siguiente ronda' ?>
              </button>
            </form>
            <?php if ($partidosPendientes > 0): ?>
              <span class="tabla-hint">Faltan <b data-stat="pendientes"><?= $partidosPendientes ?></b> resultado(s) de la ronda actual.</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($pendientesValidacion > 0): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-hourglass-half"></i> Resultados por validar</h2>
        </div>
        <div class="gestion-acciones">
          <span class="badge estado-naranja" style="margin-top:0;"><?= $pendientesValidacion ?> pendiente<?= $pendientesValidacion === 1 ? '' : 's' ?></span>
          <?php if ($esAdmin): ?>
            <form method="post" action="gestionar-torneo.php"> <?= campoCsrf() ?>
              <input type="hidden" name="accion" value="validar_pendientes" />
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-primary"><i class="fa-solid fa-circle-check"></i> Validar todos</button>
            </form>
          <?php endif; ?>
          <span class="tabla-hint">Mientras no se validen no suman a la tabla ni dejan avanzar de ronda.<?= $esAdmin ? '' : ' Los valida el administrador.' ?></span>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($esAdmin): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-user-check"></i> Corrección de resultados</h2>
        </div>
        <div class="gestion-acciones">
          <?php if (empty($torneo['organizador_asignado_id'])): ?>
            <span class="tabla-hint">Este torneo no tiene organizador asignado. Vos (administrador) siempre podés corregir resultados.</span>
          <?php elseif ((int) $torneo['correccion_autorizada'] === 1): ?>
            <span class="badge estado-verde" style="margin-top:0;">Organizador autorizado</span>
            <form method="post" action="gestionar-torneo.php"
                  data-confirmar="El organizador ya no va a poder corregir resultados de este torneo. ¿Seguimos?">
              <input type="hidden" name="accion" value="revocar_correccion" />
              <?= campoCsrf() ?>
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-danger"><i class="fa-solid fa-user-xmark"></i> Quitar autorización</button>
            </form>
            <span class="tabla-hint"><?= escapar($torneo['nombre_organizador']) ?> puede cargar y corregir resultados.</span>
          <?php else: ?>
            <span class="badge estado-gris" style="margin-top:0;">Organizador sin autorización</span>
            <form method="post" action="gestionar-torneo.php">
              <input type="hidden" name="accion" value="autorizar_correccion" />
              <?= campoCsrf() ?>
              <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
              <button type="submit" class="btn-primary"><i class="fa-solid fa-user-check"></i> Autorizar corrección</button>
            </form>
            <span class="tabla-hint"><?= escapar($torneo['nombre_organizador']) ?> puede cargar resultados, pero no corregir los ya cargados.</span>
          <?php endif; ?>
        </div>
      </section>
    <?php elseif ($puedeGestionar && !$puedeCorregir): ?>
      <p class="tabla-hint" style="margin: 0 0 1rem;">Podés cargar resultados. Para corregir uno ya cargado necesitás la autorización del administrador.</p>
    <?php endif; ?>

    <?php if (!empty($torneo['tiene_reglas_pdf']) || !empty($torneo['reglas_texto'])): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-book"></i> Reglas del torneo</h2>
        </div>
        <?php if (!empty($torneo['tiene_reglas_pdf'])): ?>
          <a href="reglas.php?id=<?= $torneoId ?>" target="_blank" rel="noopener" class="btn-primary"><i class="fa-solid fa-file-pdf"></i> Ver las reglas (PDF)</a>
        <?php else: ?>
          <p style="margin: 0; white-space: pre-wrap; overflow-wrap: anywhere;"><?= escapar($torneo['reglas_texto']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <div class="perfil-stats">
      <div class="pstat">
        <div class="pstat-num"><?= $cantidad ?></div>
        <div class="pstat-label"><?= $unidad === 'equipos' ? 'Equipos' : 'Participantes' ?></div>
      </div>
      <div class="pstat">
        <div class="pstat-num" data-stat="jugados"><?= $partidosJugados ?></div>
        <div class="pstat-label">Partidos jugados</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" data-stat="pendientes"><?= $partidosPendientes ?></div>
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
        <div id="tablaPosicionesContenedor">
          <?php include __DIR__ . '/app/Views/partials/tabla_posiciones.php'; ?>
        </div>
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

    <?php if ($puedeGestionar && in_array($torneo['estado'], ['inscripciones_abiertas', 'en_curso'], true)): ?>
      <?php $abiertoEdicion = $torneo['estado'] === 'inscripciones_abiertas'; ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-pen-to-square"></i> Editar o cancelar el torneo</h2>
        </div>
        <details class="partido-corregir">
          <summary><i class="fa-solid fa-pen"></i> Editar datos del torneo</summary>
          <form method="post" action="gestionar-torneo.php" class="panel-form"> <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="editar_torneo" />
            <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
            <div class="panel-form-grid">
              <div class="form-group">
                <label class="form-label" for="ed-nombre">Nombre</label>
                <input class="form-input" type="text" id="ed-nombre" name="nombre" minlength="3" maxlength="120" required value="<?= escapar($torneo['nombre']) ?>" />
              </div>
              <?php if ($abiertoEdicion): ?>
                <div class="form-group">
                  <label class="form-label" for="ed-cupos">Máximo de <?= $unidad ?></label>
                  <input class="form-input" type="number" id="ed-cupos" name="max_participantes" min="<?= max(2, $cantidad) ?>"
                         value="<?= $torneo['cupos'] === null ? '' : (int) $torneo['cupos'] ?>" <?= $torneo['cupos'] === null ? '' : 'required' ?> />
                  <span class="form-hint">No puede ser menor que los <?= $cantidad ?> ya inscriptos.</span>
                </div>
                <div class="form-group">
                  <label class="form-label" for="ed-apertura">Inicio de las inscripciones</label>
                  <input class="form-input" type="date" id="ed-apertura" name="fecha_inicio_inscripcion" required value="<?= escapar((string) $torneo['fecha_apertura_inscripcion']) ?>" />
                </div>
                <div class="form-group">
                  <label class="form-label" for="ed-cierre">Cierre de las inscripciones</label>
                  <input class="form-input" type="date" id="ed-cierre" name="fecha_cierre_inscripcion" required value="<?= escapar((string) $torneo['fecha_cierre_inscripcion']) ?>" />
                </div>
              <?php endif; ?>
              <div class="form-group">
                <label class="form-label" for="ed-inicio">Inicio del torneo</label>
                <input class="form-input" type="date" id="ed-inicio" name="fecha_inicio_torneo" required value="<?= escapar((string) $torneo['fecha_inicio']) ?>" />
              </div>
              <div class="form-group">
                <label class="form-label" for="ed-fin">Fin del torneo</label>
                <input class="form-input" type="date" id="ed-fin" name="fecha_fin_torneo" required value="<?= escapar((string) $torneo['fecha_fin']) ?>" />
              </div>
              <div class="form-group">
                <label class="form-label" for="ed-visib">Visibilidad</label>
                <select class="form-input" id="ed-visib" name="visibilidad">
                  <option value="publico" <?= $torneo['visibilidad'] === 'publico' ? 'selected' : '' ?>>Público (aparece en búsquedas)</option>
                  <option value="privado" <?= $torneo['visibilidad'] === 'privado' ? 'selected' : '' ?>>Privado (solo con el link)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="ed-premio">Premio</label>
                <select class="form-input" id="ed-premio" name="tipo_premio">
                  <option value="ninguno" <?= $torneo['tipo_premio'] === 'ninguno' ? 'selected' : '' ?>>Sin premio</option>
                  <option value="monetario" <?= $torneo['tipo_premio'] === 'monetario' ? 'selected' : '' ?>>Monetario</option>
                  <option value="otro" <?= $torneo['tipo_premio'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="ed-monto">Monto del premio (USD)</label>
                <input class="form-input" type="number" id="ed-monto" name="monto_premio" min="0" step="0.01"
                       value="<?= $torneo['monto_premio'] === null ? '' : escapar((string) (float) $torneo['monto_premio']) ?>" />
                <span class="form-hint">Solo si el premio es monetario.</span>
              </div>
              <div class="form-group">
                <label class="form-label" for="ed-descp">Descripción del premio</label>
                <input class="form-input" type="text" id="ed-descp" name="desc_premio" maxlength="300" value="<?= escapar((string) $torneo['desc_premio']) ?>" />
                <span class="form-hint">Solo si el premio es "Otro".</span>
              </div>
              <?php if (empty($torneo['tiene_reglas_pdf'])): ?>
                <div class="form-group">
                  <label class="form-label" for="ed-reglas">Reglas</label>
                  <textarea class="form-input" id="ed-reglas" name="reglas_texto" rows="4" maxlength="5000"><?= escapar((string) $torneo['reglas_texto']) ?></textarea>
                </div>
              <?php endif; ?>
            </div>
            <p class="tabla-hint" style="margin: 0 0 0.75rem;">El deporte, el formato y la modalidad no se pueden cambiar.<?= $abiertoEdicion ? '' : ' Con el torneo en curso tampoco se tocan las inscripciones ni el cupo.' ?></p>
            <div class="panel-form-acciones"><button type="submit" class="btn-primary">Guardar cambios</button></div>
          </form>
        </details>
        <details class="partido-corregir">
          <summary><i class="fa-solid fa-ban"></i> Cancelar el torneo</summary>
          <form method="post" action="gestionar-torneo.php" class="panel-form"
                data-confirmar="El torneo queda cancelado y ya no admite inscripciones ni resultados. Esto no se puede deshacer. ¿Seguimos?"> <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="cancelar_torneo" />
            <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
            <div class="form-group">
              <label class="form-label" for="can-motivo">Motivo (opcional)</label>
              <input class="form-input" type="text" id="can-motivo" name="motivo" maxlength="200" placeholder="Ej.: no se llegó al mínimo de equipos" />
              <span class="form-hint">Los resultados y las inscripciones quedan guardados como historial. Los equipos inscriptos se liberan.</span>
            </div>
            <div class="panel-form-acciones"><button type="submit" class="btn-danger"><i class="fa-solid fa-ban"></i> Cancelar torneo</button></div>
          </form>
        </details>
      </section>
    <?php endif; ?>

    <?php if ($puedeVerReporte): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-file-lines"></i> Reporte del torneo</h2>
        </div>
        <div class="gestion-acciones">
          <a href="reporte-torneo.php?id=<?= $torneoId ?>" class="btn-primary"><i class="fa-solid fa-print"></i> Ver / imprimir</a>
          <a href="reporte-torneo.php?id=<?= $torneoId ?>&amp;formato=csv" class="btn"><i class="fa-solid fa-file-csv"></i> Descargar CSV</a>
          <span class="tabla-hint">Participantes, tabla de posiciones y resultados.</span>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($puedeInscribirOrg): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-user-plus"></i> Inscribir <?= $unidad === 'equipos' ? 'un equipo' : 'a un participante' ?></h2>
        </div>
        <?php if ($torneo['modalidad'] === 'individual'): ?>
          <form method="post" action="gestionar-torneo.php" class="equipo-fila-acciones"> <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="inscribir_persona" />
            <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
            <input type="text" name="nombre_usuario" class="form-input-p" placeholder="Nombre de usuario (ej: juanperez)"
                   maxlength="30" required aria-label="Nombre de usuario a inscribir" />
            <button type="submit" class="btn-primary" <?= $hayCupo ? '' : 'disabled' ?>><i class="fa-solid fa-user-plus"></i> Inscribir</button>
          </form>
          <p class="tabla-hint" style="margin: 0.5rem 0 0;">Se puede inscribir a cualquier participante activo hasta que generes el fixture, aunque haya pasado la fecha de cierre.<?= $hayCupo ? '' : ' No quedan cupos.' ?></p>
        <?php elseif (empty($equiposInscribibles)): ?>
          <p class="tabla-hint" style="margin: 0;">No hay equipos para inscribir: tienen que ser de <?= escapar($torneo['deporte']) ?>, de <?= (int) $jugadoresPorEquipo ?> integrantes con el plantel completo y no estar ya inscriptos.</p>
        <?php else: ?>
          <form method="post" action="gestionar-torneo.php" class="equipo-fila-acciones"> <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="inscribir_equipo_org" />
            <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
            <select name="equipo_id" class="form-input-p" required aria-label="Equipo a inscribir">
              <option value="">Elegí un equipo…</option>
              <?php foreach ($equiposInscribibles as $eq): ?>
                <option value="<?= (int) $eq['id'] ?>"><?= escapar($eq['nombre_equipo']) ?><?= $eq['capitan'] ? ' (capitán: ' . escapar($eq['capitan']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-primary" <?= $hayCupo ? '' : 'disabled' ?>><i class="fa-solid fa-user-plus"></i> Inscribir equipo</button>
          </form>
          <?php if (!$hayCupo): ?><p class="tabla-hint" style="margin: 0.5rem 0 0;">No quedan cupos.</p><?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>

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
              <?php if ($puedeInscribirOrg): ?>
                <form method="post" action="gestionar-torneo.php" style="margin: 0 0 0 auto;"
                      data-confirmar="¿Retirar a <?= escapar($i['nombre']) ?> del torneo?"> <?= campoCsrf() ?>
                  <input type="hidden" name="accion" value="retirar_inscripcion" />
                  <input type="hidden" name="torneo_id" value="<?= $torneoId ?>" />
                  <input type="hidden" name="inscripcion_id" value="<?= (int) $i['inscripcion_id'] ?>" />
                  <button type="submit" class="btn-danger" style="padding: 2px 8px; font-size: 12px;" aria-label="Retirar inscripción"><i class="fa-solid fa-xmark"></i></button>
                </form>
              <?php endif; ?>
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

<script src="script.js?v=33"></script>
</body>
</html>
