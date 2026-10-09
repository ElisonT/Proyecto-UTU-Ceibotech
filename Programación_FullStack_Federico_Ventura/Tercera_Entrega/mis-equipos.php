<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Models/Equipo.php';
require_once __DIR__ . '/app/Models/Deporte.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
if ((int) ($_SESSION['usuario_rol'] ?? 0) !== ROL_PARTICIPANTE) {
    header('Location: index.php');
    exit;
}

$puedeCrearTorneo = false; // solo el administrador crea torneos; acá siempre es participante
$usuarioId = (int) $_SESSION['usuario_id'];
$modelo    = new Equipo();

$equipos = $modelo->listarDeUsuario($usuarioId);
foreach ($equipos as &$equipo) {
    $equipo['lista_miembros'] = $modelo->listarMiembros((int) $equipo['id']);
    $equipo['invitaciones']   = ((int) $equipo['es_capitan'] === 1)
        ? $modelo->listarInvitacionesDeEquipo((int) $equipo['id'])
        : [];
}
unset($equipo);

$recibidas = $modelo->listarInvitacionesRecibidas($usuarioId);
$deportesConEquipo = (new Deporte())->listarParaEquipos();

$aviso = $_SESSION['flash_equipos'] ?? null;
unset($_SESSION['flash_equipos']);

$tituloPagina = 'Mis equipos — PrimeCup';
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
      <a href="perfil.php" class="config-volver"><i class="fa-solid fa-arrow-left"></i> Volver a mi perfil</a>
      <h1 class="config-titulo">Mis equipos</h1>
      <p class="config-subtitulo">
        Armá tu equipo acá, antes de anotarte a un torneo. Vos sos el capitán: invitás a tus compañeros por su
        nombre de usuario y cada uno tiene que aceptar. Después inscribís al equipo desde la página del torneo.
      </p>
    </div>

    <?php if ($aviso): ?>
      <?php foreach ($aviso['mensajes'] as $mensaje): ?>
        <div class="detalle-aviso <?= $aviso['ok'] ? 'detalle-aviso-ok' : 'detalle-aviso-error' ?>" role="status">
          <?= escapar($mensaje) ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($recibidas)): ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-envelope-open-text"></i> Invitaciones recibidas</h2>
          <span class="detalle-cupos-label"><?= count($recibidas) ?> pendiente(s)</span>
        </div>
        <?php foreach ($recibidas as $inv): ?>
          <div class="equipo-fila">
            <div>
              <strong><?= escapar($inv['nombre_equipo']) ?></strong>
              <span class="tabla-hint"> · te invitó <?= escapar($inv['nombre_capitan']) ?> · <?= (int) $inv['miembros'] ?> integrante(s) hoy</span>
            </div>
            <form method="post" action="gestionar-equipo.php" class="equipo-fila-acciones">
              <input type="hidden" name="accion" value="responder_invitacion" />
              <input type="hidden" name="invitacion_id" value="<?= (int) $inv['id'] ?>" />
              <button type="submit" name="respuesta" value="aceptar" class="btn-primary"><i class="fa-solid fa-check"></i> Aceptar</button>
              <button type="submit" name="respuesta" value="rechazar" class="btn"><i class="fa-solid fa-xmark"></i> Rechazar</button>
            </form>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <section class="perfil-seccion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-plus"></i> Crear un equipo</h2>
      </div>
      <form method="post" action="gestionar-equipo.php" class="equipo-fila-acciones">
        <input type="hidden" name="accion" value="crear_equipo" />
        <input type="text" name="nombre_equipo" class="form-input-p" placeholder="Nombre del equipo (ej: Los Tigres)"
               minlength="3" maxlength="100" required aria-label="Nombre del equipo" />
        <select name="deporte_id" class="form-input-p" required aria-label="Deporte del equipo">
          <option value="">¿De qué deporte o juego?</option>
          <?php foreach ($deportesConEquipo as $dep): ?>
            <option value="<?= (int) $dep['id'] ?>"><?= escapar($dep['nombre']) ?> (equipos de <?= (int) $dep['jugadores_por_equipo'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Crear equipo</button>
      </form>
      <span class="form-hint-p">El tamaño del equipo lo define el deporte (por ejemplo, Fútbol 11 son 11 y League of Legends son 5). Un equipo solo puede inscribirse a torneos de su deporte. Ajedrez, Tenis y otros juegos individuales no llevan equipos.</span>
    </section>

    <?php if (empty($equipos)): ?>
      <p class="tabla-sin-datos">Todavía no sos parte de ningún equipo. Creá uno o esperá una invitación.</p>
    <?php endif; ?>

    <?php foreach ($equipos as $equipo):
      $esCapitan  = (int) $equipo['es_capitan'] === 1;
      $tamano     = (int) ($equipo['tamano'] ?? 0);
      $integrantes = (int) $equipo['miembros'];
      $pendientes = count($equipo['invitaciones']);
      $completo   = $tamano > 0 && $integrantes >= $tamano;
      $sinLugar   = $tamano === 0 || ($integrantes + $pendientes) >= $tamano;
    ?>
      <section class="perfil-seccion">
        <div class="seccion-header">
          <h2 class="seccion-titulo"><i class="fa-solid fa-people-group"></i> <?= escapar($equipo['nombre_equipo']) ?></h2>
          <span class="badge <?= $esCapitan ? 'estado-verde' : 'estado-naranja' ?>" style="margin-top:0;">
            <?= $esCapitan ? 'Sos el capitán' : 'Capitán: ' . escapar($equipo['nombre_capitan']) ?>
          </span>
        </div>

        <p class="tabla-hint" style="margin: 0 0 0.75rem;">
          <?php if ($equipo['deporte'] === null): ?>
            Este equipo no tiene deporte asignado. Disolvelo y creá uno nuevo.
          <?php else: ?>
            <?= escapar($equipo['deporte']) ?> · <?= $integrantes ?> de <?= $tamano ?> integrantes
            <?= $completo ? '· <strong>equipo completo</strong>, listo para inscribirse' : '· faltan ' . ($tamano - $integrantes) ?>
          <?php endif; ?>
        </p>

        <?php if ($equipo['torneo_bloqueante']): ?>
          <div class="detalle-aviso detalle-aviso-ok">
            <i class="fa-solid fa-lock"></i> Inscripto en <strong><?= escapar($equipo['torneo_bloqueante']) ?></strong>:
            la formación queda bloqueada hasta que termine el torneo.
          </div>
        <?php endif; ?>

        <div class="equipo-miembros">
          <?php foreach ($equipo['lista_miembros'] as $m): ?>
            <div class="equipo-fila">
              <div>
                <i class="fa-solid <?= (int) $m['es_capitan'] === 1 ? 'fa-crown' : 'fa-user' ?>"></i>
                <?= escapar($m['nombre_completo']) ?>
                <span class="tabla-hint">@<?= escapar($m['nombre_usuario']) ?></span>
              </div>
              <?php if ($esCapitan && (int) $m['es_capitan'] !== 1 && !$equipo['torneo_bloqueante']): ?>
                <form method="post" action="gestionar-equipo.php" class="equipo-fila-acciones"
                      onsubmit="return confirm('¿Sacar a esta persona del equipo?');">
                  <input type="hidden" name="accion" value="quitar_miembro" />
                  <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>" />
                  <input type="hidden" name="usuario_id" value="<?= (int) $m['usuario_id'] ?>" />
                  <button type="submit" class="btn"><i class="fa-solid fa-user-minus"></i> Sacar</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($esCapitan && !empty($equipo['invitaciones'])): ?>
          <h3 class="equipo-subtitulo">Invitaciones enviadas (esperando respuesta)</h3>
          <?php foreach ($equipo['invitaciones'] as $inv): ?>
            <div class="equipo-fila">
              <div>
                <i class="fa-solid fa-hourglass-half"></i> <?= escapar($inv['nombre_completo']) ?>
                <span class="tabla-hint">@<?= escapar($inv['nombre_usuario']) ?></span>
              </div>
              <form method="post" action="gestionar-equipo.php" class="equipo-fila-acciones">
                <input type="hidden" name="accion" value="cancelar_invitacion" />
                <input type="hidden" name="invitacion_id" value="<?= (int) $inv['id'] ?>" />
                <button type="submit" class="btn"><i class="fa-solid fa-ban"></i> Cancelar</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($esCapitan && !$equipo['torneo_bloqueante'] && $sinLugar && $tamano > 0): ?>
          <p class="tabla-hint" style="margin-top: 1rem;">
            No se puede invitar a más gente: entre integrantes e invitaciones pendientes ya se llega a los <?= $tamano ?> que lleva <?= escapar($equipo['deporte']) ?>.
          </p>
        <?php endif; ?>

        <?php if ($esCapitan && !$equipo['torneo_bloqueante'] && !$sinLugar): ?>
          <h3 class="equipo-subtitulo">Invitar a alguien</h3>
          <form method="post" action="gestionar-equipo.php" class="equipo-fila-acciones">
            <input type="hidden" name="accion" value="invitar" />
            <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>" />
            <input type="text" name="nombre_usuario" class="form-input-p" placeholder="Nombre de usuario (ej: juanperez)"
                   maxlength="30" required aria-label="Nombre de usuario a invitar" />
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar invitación</button>
          </form>
        <?php endif; ?>

        <div class="equipo-fila-acciones" style="margin-top: 1rem;">
          <?php if ($esCapitan): ?>
            <form method="post" action="gestionar-equipo.php"
                  onsubmit="return confirm('¿Disolver el equipo? No se puede deshacer.');">
              <input type="hidden" name="accion" value="disolver_equipo" />
              <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>" />
              <button type="submit" class="btn"><i class="fa-solid fa-trash"></i> Disolver equipo</button>
            </form>
          <?php else: ?>
            <form method="post" action="gestionar-equipo.php"
                  onsubmit="return confirm('¿Seguro que querés abandonar este equipo?');">
              <input type="hidden" name="accion" value="salir_equipo" />
              <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>" />
              <button type="submit" class="btn"><i class="fa-solid fa-right-from-bracket"></i> Abandonar equipo</button>
            </form>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>

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
