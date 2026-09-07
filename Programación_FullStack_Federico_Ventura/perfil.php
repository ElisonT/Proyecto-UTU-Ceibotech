<?php
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Models/Usuario.php';
require_once __DIR__ . '/app/Controllers/UsuarioController.php';
require_once __DIR__ . '/app/Helpers/texto.php';
require_once __DIR__ . '/app/Helpers/roles.php';

// Esta página muestra datos personales: si no hay sesión, no se puede entrar.
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario = (new Usuario())->buscarPorId((int) $_SESSION['usuario_id']);
if (!$usuario) {
    // La sesión apunta a un usuario que ya no existe (por ejemplo, si un
    // admin lo borró mientras estaba logueado). Se cierra la sesión y listo.
    header('Location: logout.php');
    exit;
}

$erroresEdicion  = [];
$erroresContacto = [];
$erroresPassword = [];
$erroresEliminar = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador = new UsuarioController();

    // Antes que nada: ¿es un pedido de eliminar la cuenta? Si sale bien, la
    // cuenta deja de existir, así que no tiene sentido seguir procesando nada más.
    if (isset($_POST['pass_eliminar'])) {
        $erroresEliminar = $controlador->procesarEliminacionCuenta((int) $usuario['id'], $_POST['pass_eliminar']);

        if (empty($erroresEliminar)) {
            session_unset();
            session_destroy();
            header('Location: index.php?cuenta_eliminada=1');
            exit;
        }
    } else {
        // El mini-formulario de la foto (se manda solo al elegir un archivo)...
        if (isset($_FILES['foto'])) {
            $erroresEdicion = array_merge(
                $erroresEdicion,
                $controlador->procesarFoto((int) $usuario['id'], $_FILES['foto'])
            );
        }

        // ...el formulario principal de "Editar perfil" (nombre, usuario, género)...
        if (isset($_POST['nombre'])) {
            $erroresEdicion = array_merge(
                $erroresEdicion,
                $controlador->procesarEdicionPerfil((int) $usuario['id'], $_POST)
            );
        }

        // ...los datos de contacto (correo, celular)...
        if (isset($_POST['correo'])) {
            $erroresContacto = $controlador->procesarContacto((int) $usuario['id'], $_POST);
        }

        // ...o el cambio de contraseña.
        if (isset($_POST['pass_actual'])) {
            $erroresPassword = $controlador->procesarCambioContrasena((int) $usuario['id'], $_POST);
        }

        $huboError = !empty($erroresEdicion) || !empty($erroresContacto) || !empty($erroresPassword);

        if (!$huboError) {
            // Puede haber cambiado el nombre, la foto, el contacto o la
            // contraseña: se refresca todo de una y se actualiza la sesión,
            // para que el nav se vea al día ya mismo.
            $usuario = (new Usuario())->buscarPorId((int) $usuario['id']);
            $_SESSION['usuario_nombre'] = $usuario['nombre_completo'];
            $_SESSION['usuario_foto']   = $usuario['foto'];

            $vieneDelModal = isset($_POST['correo']) || isset($_POST['pass_actual']);
            $destino = 'perfil.php?actualizado=1' . ($vieneDelModal ? '&panel=config' : '');
            header('Location: ' . $destino);
            exit;
        }

        // Si algo falló y vino del formulario principal, mostramos lo que el
        // usuario tipeó (no lo que sigue guardado en la base), para que no
        // pierda lo escrito.
        if (isset($_POST['nombre'])) {
            $usuario['nombre_completo'] = $_POST['nombre']   ?? $usuario['nombre_completo'];
            $usuario['nombre_usuario']  = $_POST['usuario']  ?? $usuario['nombre_usuario'];
            $usuario['genero']          = $_POST['genero']   ?? $usuario['genero'];
        }
        if (isset($_POST['correo'])) {
            $usuario['email']   = $_POST['correo']  ?? $usuario['email'];
            $usuario['celular'] = $_POST['celular'] ?? $usuario['celular'];
        }
    }
}

$edicionExitosa = isset($_GET['actualizado']);

$abrirModalConfiguracion = !empty($erroresContacto) || !empty($erroresPassword) || !empty($erroresEliminar)
    || isset($_GET['panel']);

$puedeCrearTorneo = in_array((int) ($_SESSION['usuario_rol'] ?? 0), [ROL_ADMINISTRADOR], true);

// ---- Torneos reales del usuario (para las estadísticas y el carrusel) ----
require_once __DIR__ . '/app/Models/Torneo.php';
$modeloTorneo       = new Torneo();
$torneosJugados     = $modeloTorneo->contarJugados((int) $usuario['id']);
$torneosActivos     = $modeloTorneo->contarActivos((int) $usuario['id']);
$torneosGanados     = $modeloTorneo->contarGanados((int) $usuario['id']);
$porcentajeVictorias = $torneosJugados > 0 ? round(($torneosGanados / $torneosJugados) * 100) : 0;
$torneosParticipando = $modeloTorneo->listarParticipando((int) $usuario['id']);

// Mapeos para mostrar el formato/estado de cada torneo con textos y colores prolijos
$etiquetasFormato = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];
$etiquetasEstado  = [
    'inscripciones_abiertas' => ['texto' => 'Inscripciones abiertas', 'clase' => 'estado-verde'],
    'en_curso'               => ['texto' => 'En curso',               'clase' => 'estado-naranja'],
    'finalizado'             => ['texto' => 'Finalizado',             'clase' => 'estado-rojo'],
];

$iniciales = obtenerIniciales($usuario['nombre_completo']);

$etiquetasGenero = [
    'masculino' => 'Masculino',
    'femenino'  => 'Femenino',
    'otro'      => 'Otro / Prefiero no decirlo',
];
$generoActual = $usuario['genero'] ?: 'otro';
$generoTexto  = $etiquetasGenero[$generoActual] ?? $etiquetasGenero['otro'];

// Nombre del mes de registro, en español (ej: "junio 2026")
$meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
          7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
$fechaRegistro = new DateTime($usuario['fecha_registro']);
$miembroDesde  = $meses[(int) $fechaRegistro->format('n')] . ' ' . $fechaRegistro->format('Y');
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
  <title>Perfil — SGDM</title>
  <!-- styles.css guarda todos los estilos visuales del sitio. -->
  <link rel="stylesheet" href="styles.css?v=25" />
  <!-- Font Awesome aporta los iconos usados en botones, tarjetas y menus. -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <!-- BODY: contiene todo lo visible de la pagina: navegacion, contenido principal y pie. -->

  <!-- NAVBAR: menu principal para moverse entre las pantallas del mockup. -->
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

  <main class="perfil-main">
    <!-- MAIN: contenido central y especifico de esta pagina. -->

    <!-- =====================
       CABECERA DEL PERFIL: muestra avatar, nombre, usuario y datos generales.
       Tambien contiene accesos para editar perfil o ir a configuracion.
       ===================== -->
    <section class="perfil-header">

      <!-- AVATAR: circulo con iniciales del usuario y opcion visual para foto. -->
      <div class="perfil-avatar-wrap">
        <div class="perfil-avatar" id="perfilAvatarCirculo"<?php if (!empty($usuario['foto'])): ?> style="background-image:url('<?= htmlspecialchars($usuario['foto']) ?>'); background-size:cover; background-position:center;"<?php endif; ?>><?= empty($usuario['foto']) ? htmlspecialchars($iniciales) : '' ?></div>
        <!-- Este mini-formulario se manda solo (ver script.js) apenas se elige un archivo,
             sin esperar a que se toque "Guardar cambios" del formulario de abajo. -->
        <form id="fotoPerfilForm" method="post" action="perfil.php" enctype="multipart/form-data" style="display:contents;">
          <label class="btn-foto" title="Cambiar foto" aria-label="Cambiar foto de perfil">
            <i class="fa-solid fa-camera" aria-hidden="true"></i>
            <input type="file" id="perfilFotoInput" name="foto" accept="image/jpeg,image/png,image/webp" style="display:none;" />
          </label>
        </form>
      </div>

      <!-- INFO: datos principales del usuario mostrados en su perfil. -->
      <div class="perfil-info">
        <h1 class="perfil-nombre"><?= htmlspecialchars($usuario['nombre_completo']) ?></h1>
        <span class="perfil-usuario">@<?= htmlspecialchars($usuario['nombre_usuario']) ?></span>
        <!-- "Sobre mí" todavía es un texto fijo: la tabla usuarios no tiene
             columna para esto todavía. Se agrega en una próxima entrega. -->
        <p class="perfil-sobre">Apasionado por los torneos de fútbol y ajedrez. Siempre compitiendo.</p>
        <div class="perfil-meta">
          <span><i class="fa-solid fa-calendar"></i> Miembro desde <?= htmlspecialchars($miembroDesde) ?></span>
          <span><i class="fa-solid fa-venus-mars"></i> <?= htmlspecialchars($generoTexto) ?></span>
        </div>
      </div>

      <!-- ACCIONES: enlaces para editar el perfil o abrir configuracion. -->
      <div class="perfil-acciones">
        <a href="#editar-perfil" class="btn btn-editar">
          <i class="fa-solid fa-pen"></i> Editar perfil
        </a>
        <button type="button" class="btn btn-editar" id="btnAbrirConfiguracion">
          <i class="fa-solid fa-gear"></i> Configuración
        </button>
      </div>

    </section>

    <!-- FORMULARIO DE EDICION: campos para modificar datos visibles del perfil. -->
    <section class="perfil-edicion" id="editar-perfil">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-pen"></i> Editar perfil</h2>
      </div>

      <?php if ($edicionExitosa): ?>
        <div class="form-alert form-alert-success" style="display:flex;">
          <i class="fa-solid fa-circle-check"></i>
          <span>Perfil actualizado correctamente.</span>
        </div>
      <?php endif; ?>

      <?php if (!empty($erroresEdicion)): ?>
        <div class="form-alert form-alert-error" style="display:flex; align-items:flex-start;">
          <i class="fa-solid fa-circle-exclamation"></i>
          <ul style="margin:0; padding-left:1.1rem;">
            <?php foreach ($erroresEdicion as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form class="editar-form" id="editarPerfilForm" method="post" action="perfil.php">
        <div class="editar-fila">
          <div class="form-group">
            <label class="form-label-p" for="perfilNombreInput">Nombre completo</label>
            <input type="text" id="perfilNombreInput" name="nombre" class="form-input-p" value="<?= htmlspecialchars($usuario['nombre_completo']) ?>"
              pattern="[A-Za-záéíóúÁÉÍÓÚñÑ]+ [A-Za-záéíóúÁÉÍÓÚñÑ].*"
              title="Ingresá tu nombre y apellido" required />
          </div>
          <div class="form-group">
            <label class="form-label-p" for="perfilUsuarioInput">Nombre de usuario</label>
            <input type="text" id="perfilUsuarioInput" name="usuario" class="form-input-p" value="<?= htmlspecialchars($usuario['nombre_usuario']) ?>"
              pattern="[a-zA-Z0-9_]+"
              title="Solo letras, números y guion bajo" required />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label-p">Género</label>
          <div class="radio-inline">
            <label class="radio-opcion">
              <input type="radio" name="genero" value="masculino" <?= $generoActual === 'masculino' ? 'checked' : '' ?> />
              <span>Masculino</span>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="genero" value="femenino" <?= $generoActual === 'femenino' ? 'checked' : '' ?> />
              <span>Femenino</span>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="genero" value="otro" <?= $generoActual === 'otro' ? 'checked' : '' ?> />
              <span>Otro / Prefiero no decirlo</span>
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label-p" for="perfilBioInput">Sobre mí</label>
          <textarea id="perfilBioInput" name="bio" class="form-input-p form-textarea" rows="3" maxlength="200"
            placeholder="Contá algo sobre vos...">Apasionado por los torneos de fútbol y ajedrez. Siempre compitiendo.</textarea>
          <span class="form-hint-p" id="perfilBioContador">200 caracteres restantes.</span>
        </div>
        <div class="editar-acciones">
          <button type="submit" class="btn-primary">Guardar cambios</button>
          <a href="perfil.php" class="btn">Cancelar</a>
        </div>
      </form>
    </section>

    <!-- =====================
       ESTADISTICAS: resumen visual con numeros importantes del sistema.
       Ayuda a mostrar actividad en el mockup.
       ===================== -->
    <section class="perfil-stats">
      <div class="pstat">
        <div class="pstat-num" data-target="<?= $torneosJugados ?>"><?= $torneosJugados ?></div>
        <div class="pstat-label">Torneos jugados</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" data-target="<?= $torneosActivos ?>"><?= $torneosActivos ?></div>
        <div class="pstat-label">Torneos activos</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" data-target="<?= $torneosGanados ?>"><?= $torneosGanados ?></div>
        <div class="pstat-label">Torneos ganados</div>
      </div>
      <div class="pstat">
        <div class="pstat-num" data-target="<?= $porcentajeVictorias ?>" data-formato="%"><?= $porcentajeVictorias ?>%</div>
        <div class="pstat-label">Victorias</div>
      </div>
    </section>

    <!-- =====================
       TORNEOS ACTIVOS: tarjetas de torneos destacados o en curso.
       Cada tarjeta funciona como acceso al detalle del torneo.
       ===================== -->
    <section class="perfil-seccion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-trophy"></i> Torneos en los que participa</h2>
        <a href="busqueda.php" class="section-link">Ver todos <i class="fa-solid fa-arrow-right"></i></a>
      </div>
      <?php if (empty($torneosParticipando)): ?>
        <p class="form-hint" style="margin-top:0.5rem;">
          Todavía no estás anotado en ningún torneo.
          <a href="busqueda.php">Buscá uno para sumarte</a>.
        </p>
      <?php else: ?>
        <div class="carrusel-wrap">
          <button class="carrusel-flecha" data-carrusel-dir="-1" aria-label="Ver torneos anteriores">
            <i class="fa-solid fa-chevron-left"></i>
          </button>
          <div class="tarjetas-carrusel">

            <?php foreach ($torneosParticipando as $torneo):
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
          <button class="carrusel-flecha" data-carrusel-dir="1" aria-label="Ver más torneos">
            <i class="fa-solid fa-chevron-right"></i>
          </button>
        </div>
      <?php endif; ?>
    </section>

    <!-- =====================
       HISTORIAL: tabla con torneos ya jugados y resultados obtenidos.
       Sirve para mostrar antecedentes del usuario dentro del sistema.
       ===================== -->
    <section class="perfil-seccion">
      <div class="seccion-header">
        <h2 class="seccion-titulo"><i class="fa-solid fa-clock-rotate-left"></i> Historial de resultados</h2>
      </div>
      <div class="historial-tabla">
        <div class="historial-header">
          <span>Torneo</span>
          <span>Formato</span>
          <span>Posición</span>
          <span>Resultado</span>
        </div>

        <div class="historial-fila">
          <div class="historial-torneo">
            <span class="historial-nombre">Liga Escolar 2025</span>
            <span class="historial-deporte"><i class="fa-solid fa-futbol"></i> Fútbol</span>
          </div>
          <span class="historial-formato">Liga</span>
          <span class="historial-pos">1°</span>
          <span class="badge estado-verde">Campeón</span>
        </div>

        <div class="historial-fila">
          <div class="historial-torneo">
            <span class="historial-nombre">Open Ajedrez UTU</span>
            <span class="historial-deporte"><i class="fa-solid fa-chess"></i> Ajedrez</span>
          </div>
          <span class="historial-formato">Sistema suizo</span>
          <span class="historial-pos">3°</span>
          <span class="badge estado-naranja">Semifinal</span>
        </div>

        <div class="historial-fila">
          <div class="historial-torneo">
            <span class="historial-nombre">Gaming Cup 2025</span>
            <span class="historial-deporte"><i class="fa-solid fa-gamepad"></i> Videojuegos</span>
          </div>
          <span class="historial-formato">Eliminación directa</span>
          <span class="historial-pos">2°</span>
          <span class="badge estado-naranja">Finalista</span>
        </div>

        <div class="historial-fila">
          <div class="historial-torneo">
            <span class="historial-nombre">Torneo Relámpago</span>
            <span class="historial-deporte"><i class="fa-solid fa-futbol"></i> Fútbol</span>
          </div>
          <span class="historial-formato">Eliminación directa</span>
          <span class="historial-pos">5°</span>
          <span class="badge estado-rojo">Eliminado</span>
        </div>

        <div class="historial-fila">
          <div class="historial-torneo">
            <span class="historial-nombre">Copa Otoño 2025</span>
            <span class="historial-deporte"><i class="fa-solid fa-futbol"></i> Fútbol</span>
          </div>
          <span class="historial-formato">Liga</span>
          <span class="historial-pos">1°</span>
          <span class="badge estado-verde">Campeón</span>
        </div>

      </div>
    </section>

    <!-- =====================
         MODAL DE CONFIGURACIÓN: se abre encima de esta misma página,
         no navega a otro lado (ver script.js para el toggle).
         ===================== -->
    <div class="modal-overlay" id="modalConfiguracion" style="display:<?= $abrirModalConfiguracion ? 'flex' : 'none' ?>;">
      <div class="modal-panel">
        <div class="modal-panel-header">
          <h2><i class="fa-solid fa-gear"></i> Configuración de cuenta</h2>
          <button type="button" class="modal-cerrar" id="btnCerrarConfiguracion" aria-label="Cerrar">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="modal-panel-body">

          <?php if ($edicionExitosa && isset($_GET['panel'])): ?>
            <div class="form-alert form-alert-success" style="display:flex;">
              <i class="fa-solid fa-circle-check"></i>
              <span>Cambios guardados correctamente.</span>
            </div>
          <?php endif; ?>

          <!-- DATOS DE CONTACTO -->
          <section class="perfil-seccion">
            <div class="seccion-header">
              <h2 class="seccion-titulo"><i class="fa-solid fa-address-card"></i> Datos de contacto</h2>
            </div>

            <?php if (!empty($erroresContacto)): ?>
              <div class="form-alert form-alert-error" style="display:flex; align-items:flex-start;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <ul style="margin:0; padding-left:1.1rem;">
                  <?php foreach ($erroresContacto as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form class="editar-form" method="post" action="perfil.php">
              <div class="editar-fila">
                <div class="form-group">
                  <label class="form-label-p" for="correo">Correo electrónico</label>
                  <input
                    type="email" id="correo" name="correo" class="form-input-p"
                    value="<?= htmlspecialchars($usuario['email']) ?>"
                    placeholder="ejemplo@correo.com" required
                  />
                </div>
                <div class="form-group">
                  <label class="form-label-p" for="celular">Número de celular</label>
                  <input
                    type="tel" id="celular" name="celular" class="form-input-p"
                    value="<?= htmlspecialchars($usuario['celular'] ?? '') ?>"
                    placeholder="+598 09X XXX XXX" pattern="[0-9+\s]+" title="Solo números, sin letras"
                  />
                </div>
              </div>
              <div class="config-aviso">
                <i class="fa-solid fa-circle-info"></i>
                Tu correo y celular son datos privados, no se muestran públicamente.
              </div>
              <div class="editar-acciones">
                <button type="submit" class="btn-primary">Guardar cambios</button>
              </div>
            </form>
          </section>

          <!-- CAMBIAR CONTRASEÑA -->
          <section class="perfil-seccion">
            <div class="seccion-header">
              <h2 class="seccion-titulo"><i class="fa-solid fa-key"></i> Cambiar contraseña</h2>
            </div>

            <?php if (!empty($erroresPassword)): ?>
              <div class="form-alert form-alert-error" style="display:flex; align-items:flex-start;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <ul style="margin:0; padding-left:1.1rem;">
                  <?php foreach ($erroresPassword as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form class="editar-form" method="post" action="perfil.php">
              <div class="form-group">
                <label class="form-label-p" for="pass-actual">Contraseña actual</label>
                <input type="password" id="pass-actual" name="pass_actual" class="form-input-p" placeholder="Tu contraseña actual" required />
              </div>
              <div class="editar-fila">
                <div class="form-group">
                  <label class="form-label-p" for="pass-nueva">Nueva contraseña</label>
                  <input
                    type="password" id="pass-nueva" name="pass_nueva" class="form-input-p"
                    placeholder="Mínimo 8 caracteres" minlength="8"
                    pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}"
                    title="Mínimo 8 caracteres, una mayúscula y un número" required
                  />
                </div>
                <div class="form-group">
                  <label class="form-label-p" for="pass-confirmar">Confirmar nueva contraseña</label>
                  <input type="password" id="pass-confirmar" name="pass_confirmar" class="form-input-p" placeholder="Repetí la nueva contraseña" minlength="8" required />
                </div>
              </div>
              <span class="form-hint-p" style="padding-left: 4px;">
                <i class="fa-solid fa-circle-info"></i>
                La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.
              </span>
              <div class="editar-acciones">
                <button type="submit" class="btn-primary">Cambiar contraseña</button>
              </div>
            </form>
          </section>

          <!-- ZONA DE PELIGRO -->
          <section class="perfil-seccion config-peligro">
            <div class="seccion-header">
              <h2 class="seccion-titulo seccion-titulo-rojo">
                <i class="fa-solid fa-triangle-exclamation"></i> Zona de peligro
              </h2>
            </div>

            <?php if (!empty($erroresEliminar)): ?>
              <div class="form-alert form-alert-error" style="display:flex; align-items:flex-start;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <ul style="margin:0; padding-left:1.1rem;">
                  <?php foreach ($erroresEliminar as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="post" action="perfil.php" onsubmit="return confirm('¿Seguro que querés eliminar tu cuenta? Esta acción no se puede deshacer.');">
              <div class="peligro-contenido">
                <div class="peligro-info">
                  <p class="peligro-titulo">Eliminar cuenta</p>
                  <p class="peligro-desc">Esta acción es permanente y no se puede deshacer. Se borran tu nombre, correo, celular y foto; ya no vas a poder iniciar sesión. Si organizaste o ganaste algún torneo, esos registros quedan como "Usuario eliminado" en vez de desaparecer.</p>
                  <div class="form-group" style="margin-top:10px; max-width:280px;">
                    <label class="form-label-p" for="pass-eliminar">Confirmá tu contraseña</label>
                    <input type="password" id="pass-eliminar" name="pass_eliminar" class="form-input-p" required />
                  </div>
                </div>
                <button type="submit" class="btn-danger">
                  <i class="fa-solid fa-trash"></i> Eliminar mi cuenta
                </button>
              </div>
            </form>
          </section>

        </div>
      </div>
    </div>

  </main>

  <!-- FOOTER: informacion final comun del sistema. -->
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
<script src="script.js?v=25"></script>
</body>
</html>
