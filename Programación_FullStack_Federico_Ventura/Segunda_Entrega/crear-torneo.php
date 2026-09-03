<?php
session_start();
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Controllers/TorneoController.php';

// Según la letra (5.1 y 5.2): el Administrador general CREA los torneos.
// Al Organizador se le ASIGNA un torneo ya creado para que lo gestione,
// pero no puede crear uno nuevo desde cero.
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rolActual = (int) ($_SESSION['usuario_rol'] ?? 0);
if ($rolActual !== ROL_ADMINISTRADOR) {
    header('Location: index.php');
    exit;
}

$errores = [];
// Se guarda lo que el usuario ya había escrito, para no hacerle repetir
// todo el formulario si algo falló en la validación.
$valores = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controlador = new TorneoController();
    [$errores, $idTorneo] = $controlador->procesarCreacion($_POST, (int) $_SESSION['usuario_id']);

    if (empty($errores)) {
        // Va directo a asignarle un organizador, que es el paso lógico siguiente.
        header('Location: asignar-organizador.php?creado=1');
        exit;
    }
}
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
  <title>Crear torneo — SGDM</title>
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

  <main class="crear-main">
    <!-- MAIN: contenido central y especifico de esta pagina. -->

    <!-- ENCABEZADO: titulo de la pantalla y acciones principales de la pagina. -->
    <div class="config-encabezado">
      <a href="index.php" class="config-volver">
        <i class="fa-solid fa-arrow-left"></i> Volver al inicio
      </a>
      <h1 class="config-titulo">Crear torneo</h1>
      <p class="config-subtitulo">Completá el formulario para publicar tu competencia</p>
    </div>

    <!-- FORMULARIO PRINCIPAL: agrupa todos los campos necesarios para publicar un torneo. -->
    <form class="crear-form" id="crearTorneoForm" action="crear-torneo.php" method="post" enctype="multipart/form-data">

      <?php if (!empty($errores)): ?>
        <div class="form-alert form-alert-error" style="display:flex; align-items:flex-start; margin-bottom:1rem;">
          <i class="fa-solid fa-circle-exclamation"></i>
          <ul style="margin:0; padding-left:1.1rem;">
            <?php foreach ($errores as $error): ?>
              <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- STEPPER: indicador visual de progreso a través de los 6 pasos del formulario. -->
      <div class="crear-stepper" id="crearStepper">
        <div class="crear-step" data-step-target="1">
          <span class="crear-step-circulo">1</span>
          <span class="crear-step-label">Info básica</span>
        </div>
        <div class="crear-step" data-step-target="2">
          <span class="crear-step-circulo">2</span>
          <span class="crear-step-label">Configuración</span>
        </div>
        <div class="crear-step" data-step-target="3">
          <span class="crear-step-circulo">3</span>
          <span class="crear-step-label">Fechas</span>
        </div>
        <div class="crear-step" data-step-target="4">
          <span class="crear-step-circulo">4</span>
          <span class="crear-step-label">Premio</span>
        </div>
        <div class="crear-step" data-step-target="5">
          <span class="crear-step-circulo">5</span>
          <span class="crear-step-label">Reglas</span>
        </div>
        <div class="crear-step" data-step-target="6">
          <span class="crear-step-circulo">6</span>
          <span class="crear-step-label">Visibilidad</span>
        </div>
      </div>

      <!-- =====================
           SECCIÓN 1: INFO BÁSICA
           ===================== -->
      <section class="crear-seccion" data-step="1">
        <div class="seccion-header">
          <h2 class="seccion-titulo">
            <i class="fa-solid fa-circle-info"></i> Información básica
          </h2>
        </div>

        <!-- Nombre: input obligatorio para identificar el torneo. -->
        <div class="form-group">
          <label class="form-label-p" for="nombre-torneo">Nombre del torneo</label>
          <input
            type="text"
            id="nombre-torneo"
            name="nombre"
            class="form-input-p"
            placeholder="Ej: Mundialito 2026"
            minlength="3"
            maxlength="80"
            value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>"
            required
          />
        </div>

        <!-- Deporte: selector con categorias y opciones de competencia. -->
        <div class="form-group">
          <label class="form-label-p" for="deporte">Deporte o categoría</label>
          <select id="deporte" name="deporte" class="form-input-p" required>
            <option value="">Seleccioná un deporte...</option>
            <optgroup label="Deportes tradicionales">
              <option value="futbol11">Fútbol 11</option>
              <option value="futbol5">Fútbol 5</option>
              <option value="basquetbol">Básquetbol</option>
              <option value="tenis">Tenis</option>
              <option value="padel">Pádel</option>
              <option value="pingpong">Ping pong</option>
            </optgroup>
            <optgroup label="Juegos de mesa / carta">
              <option value="ajedrez">Ajedrez</option>
              <option value="truco">Truco</option>
              <option value="damas">Damas</option>
            </optgroup>
            <optgroup label="Videojuegos">
              <option value="cs2">CS2</option>
              <option value="lol">League of Legends</option>
              <option value="fortnite">Fortnite</option>
              <option value="valorant">Valorant</option>
              <option value="rocketleague">Rocket League</option>
            </optgroup>
            <optgroup label="Otro">
              <option value="otro">Otro</option>
            </optgroup>
          </select>
        </div>

        <!-- Descripcion: textarea para explicar brevemente el torneo. -->
        <div class="form-group">
          <label class="form-label-p" for="descripcion">Descripción corta</label>
          <textarea
            id="descripcion"
            name="descripcion"
            class="form-input-p form-textarea"
            placeholder="Contá de qué trata el torneo en pocas palabras..."
            maxlength="200"
            rows="3"
          ></textarea>
          <span class="form-hint-p">Máximo 200 caracteres. Se muestra en los resultados de búsqueda.</span>
        </div>
      </section>

      <!-- =====================
           SECCIÓN 2: CONFIGURACIÓN
           ===================== -->
      <section class="crear-seccion" data-step="2">
        <div class="seccion-header">
          <h2 class="seccion-titulo">
            <i class="fa-solid fa-gear"></i> Configuración del torneo
          </h2>
        </div>

        <!-- Tipo de torneo: radios para elegir liga, eliminacion directa o sistema suizo. -->
        <div class="form-group">
          <label class="form-label-p">Tipo de torneo</label>
          <div class="radio-group">

            <label class="radio-card">
              <input type="radio" name="tipo" value="liga" required />
              <div class="radio-card-inner">
                <i class="fa-solid fa-table"></i>
                <span class="radio-card-titulo">Liga</span>
                <span class="radio-card-desc">Todos contra todos. Puntos por resultado.</span>
              </div>
            </label>

            <label class="radio-card">
              <input type="radio" name="tipo" value="eliminacion" />
              <div class="radio-card-inner">
                <i class="fa-solid fa-sitemap"></i>
                <span class="radio-card-titulo">Eliminación directa</span>
                <span class="radio-card-desc">El perdedor queda eliminado. Llaves automáticas.</span>
              </div>
            </label>

            <label class="radio-card">
              <input type="radio" name="tipo" value="suizo" />
              <div class="radio-card-inner">
                <i class="fa-solid fa-shuffle"></i>
                <span class="radio-card-titulo">Sistema suizo</span>
                <span class="radio-card-desc">Emparejamiento por rendimiento acumulado.</span>
              </div>
            </label>

          </div>
        </div>

        <!-- Participantes: define cupos maximos y si se compite individualmente o por equipos. -->
        <div class="crear-fila">
          <div class="form-group">
            <label class="form-label-p" for="max-participantes">Máximo de participantes / equipos</label>
            <input
              type="number"
              id="max-participantes"
              name="max_participantes"
              class="form-input-p"
              placeholder="Ej: 16"
              min="2"
              max="512"
              required
            />
            <span class="form-hint-p">Entre 2 y 512.</span>
          </div>

          <div class="form-group">
            <label class="form-label-p">Modalidad</label>
            <div class="radio-inline">
              <label class="radio-opcion">
                <input type="radio" name="modalidad" value="individual" required />
                <span>Individual</span>
              </label>
              <label class="radio-opcion">
                <input type="radio" name="modalidad" value="equipos" />
                <span>Por equipos</span>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="radio-opcion">
              <input type="checkbox" id="restringirGenero" name="restringir_genero" />
              <span>Restringir por género</span>
            </label>
            <span class="form-hint-p">Activá esta opción solo si el torneo debe limitarse a un género en particular.</span>
          </div>

          <div class="form-group" id="grupoCategoriaGenero" style="display:none;">
            <label class="form-label-p">Categoría</label>
            <div class="radio-inline">
              <label class="radio-opcion">
                <input type="radio" name="categoria_genero" value="mixto" checked />
                <span>Mixto</span>
              </label>
              <label class="radio-opcion">
                <input type="radio" name="categoria_genero" value="masculino" />
                <span>Masculino</span>
              </label>
              <label class="radio-opcion">
                <input type="radio" name="categoria_genero" value="femenino" />
                <span>Femenino</span>
              </label>
            </div>
            <span class="form-hint-p">"Mixto" si no importa el género de los participantes.</span>
          </div>

          <!-- Solo aplica si la modalidad es "equipos"; se muestra/oculta con JS. -->
          <div class="form-group" id="grupoJugadoresPorEquipo" style="display:none;">
            <label class="form-label-p" for="jugadores_por_equipo">Jugadores por equipo</label>
            <input
              type="number"
              id="jugadores_por_equipo"
              name="jugadores_por_equipo"
              class="form-input-p"
              placeholder="ej: 11"
              min="1"
              max="50"
            />
            <span class="form-hint-p">Cantidad de jugadores que necesita cada equipo.</span>
          </div>
        </div>
      </section>

      <!-- =====================
           SECCIÓN 3: FECHAS
           ===================== -->
      <section class="crear-seccion" data-step="3">
        <div class="seccion-header">
          <h2 class="seccion-titulo">
            <i class="fa-solid fa-calendar"></i> Fechas
          </h2>
        </div>

        <div class="crear-fila">
          <div class="form-group">
            <label class="form-label-p" for="fecha-inicio-inscripcion">Inicio de inscripciones</label>
            <input
              type="date"
              id="fecha-inicio-inscripcion"
              name="fecha_inicio_inscripcion"
              class="form-input-p"
              min="2026-06-06"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label-p" for="fecha-cierre-inscripcion">Cierre de inscripciones</label>
            <input
              type="date"
              id="fecha-cierre-inscripcion"
              name="fecha_cierre_inscripcion"
              class="form-input-p"
              min="2026-06-06"
              required
            />
          </div>
        </div>

        <div class="crear-fila">
          <div class="form-group">
            <label class="form-label-p" for="fecha-inicio-torneo">Fecha de inicio del torneo</label>
            <input
              type="date"
              id="fecha-inicio-torneo"
              name="fecha_inicio_torneo"
              class="form-input-p"
              min="2026-06-06"
              value="<?= htmlspecialchars($valores['fecha_inicio_torneo'] ?? '') ?>"
              required
            />
          </div>

          <div class="form-group">
            <label class="form-label-p" for="fecha-fin-torneo">Fecha de fin del torneo</label>
            <input
              type="date"
              id="fecha-fin-torneo"
              name="fecha_fin_torneo"
              class="form-input-p"
              min="2026-06-06"
              required
            />
          </div>
        </div>

        <!-- Duracion: se calcula sola a partir de las fechas de inicio y fin de arriba. -->
        <div class="form-group">
          <input type="hidden" name="duracion_dias" id="duracionDiasReal" value="" />
          <div class="duracion-valor">Duración del torneo: <strong id="duracionTexto">Elegí ambas fechas</strong></div>
        </div>
      </section>

      <!-- =====================
           SECCIÓN 4: PREMIO
           ===================== -->
      <section class="crear-seccion" data-step="4">
        <div class="seccion-header">
          <h2 class="seccion-titulo">
            <i class="fa-solid fa-trophy"></i> Premio o incentivo
          </h2>
        </div>

        <div class="form-group">
          <label class="form-label-p">Tipo de premio</label>
          <div class="radio-inline">
            <label class="radio-opcion">
              <input type="radio" name="tipo_premio" value="ninguno" checked />
              <span>Sin premio</span>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="tipo_premio" value="monetario" />
              <span>Monetario</span>
            </label>
            <label class="radio-opcion">
              <input type="radio" name="tipo_premio" value="otro" />
              <span>Otro</span>
            </label>
          </div>
        </div>

        <!-- Premio monetario: campo opcional para indicar monto en dolares (moneda mas general que el peso uruguayo). -->
        <div class="form-group">
          <label class="form-label-p" for="monto-premio">
            Monto del premio <span class="form-hint-p">(solo si es monetario)</span>
          </label>
          <div class="input-prefix-wrap">
            <span class="input-prefix">USD</span>
            <input
              type="number"
              id="monto-premio"
              name="monto_premio"
              class="form-input-p input-with-prefix"
              placeholder="Ej: 100"
              min="0"
            />
          </div>
        </div>

        <!-- Premio otro: descripcion opcional para premios no monetarios. -->
        <div class="form-group">
          <label class="form-label-p" for="desc-premio">
            Descripción del premio <span class="form-hint-p">(solo si es otro tipo)</span>
          </label>
          <textarea
            id="desc-premio"
            name="desc_premio"
            class="form-input-p form-textarea"
            placeholder="Ej: Medalla + remera del torneo..."
            maxlength="300"
            rows="2"
          ></textarea>
        </div>

      </section>

      <!-- =====================
           SECCIÓN 5: REGLAS
           ===================== -->
      <section class="crear-seccion" data-step="5">
        <div class="seccion-header">
          <h2 class="seccion-titulo">
            <i class="fa-solid fa-book"></i> Reglas del torneo
          </h2>
        </div>

        <div class="config-aviso">
          <i class="fa-solid fa-circle-info"></i>
          Podés redactar las reglas acá abajo <strong>o</strong> subir un PDF. Si subís un PDF, se usará ese y se ignorará el texto.
        </div>

        <div class="form-group" style="margin-top: 1rem;">
          <label class="form-label-p" for="reglas-texto">Redactar reglas</label>
          <textarea
            id="reglas-texto"
            name="reglas_texto"
            class="form-input-p form-textarea"
            placeholder="Escribí las reglas del torneo acá..."
            rows="5"
          ></textarea>
        </div>

        <div class="form-group">
          <label class="form-label-p" for="reglas-pdf">O subir PDF de reglas</label>
          <div class="file-upload-wrap">
            <i class="fa-solid fa-file-pdf file-upload-icon"></i>
            <span class="file-upload-texto" id="reglasPdfTexto">Seleccioná un archivo PDF</span>
            <input
              type="file"
              id="reglas-pdf"
              name="reglas_pdf"
              accept=".pdf"
              class="file-upload-input"
            />
          </div>
          <span class="form-hint-p">Solo archivos PDF.</span>
        </div>
      </section>

      <!-- =====================
           SECCIÓN 6: VISIBILIDAD
           ===================== -->
      <section class="crear-seccion" data-step="6">
        <div class="seccion-header">
          <h2 class="seccion-titulo">
            <i class="fa-solid fa-eye"></i> Visibilidad
          </h2>
        </div>

        <div class="form-group">
          <label class="form-label-p">¿Quién puede ver este torneo?</label>
          <div class="radio-group">

            <label class="radio-card">
              <input type="radio" name="visibilidad" value="publico" required />
              <div class="radio-card-inner">
                <i class="fa-solid fa-globe"></i>
                <span class="radio-card-titulo">Público</span>
                <span class="radio-card-desc">Aparece en búsquedas. Cualquiera puede inscribirse.</span>
              </div>
            </label>

            <label class="radio-card">
              <input type="radio" name="visibilidad" value="privado" />
              <div class="radio-card-inner">
                <i class="fa-solid fa-lock"></i>
                <span class="radio-card-titulo">Privado</span>
                <span class="radio-card-desc">Solo accesible con el link directo. No aparece en búsquedas.</span>
              </div>
            </label>
          </div>
        </div>
      </section>

      <!-- BOTONES DE NAVEGACIÓN DEL WIZARD: siempre visibles, fuera de cada paso. -->
      <div class="crear-acciones">
        <button type="button" class="btn" id="btnPasoAnterior">
          <i class="fa-solid fa-arrow-left"></i> Anterior
        </button>
        <button type="button" class="btn-primary btn-lg" id="btnPasoSiguiente">
          Siguiente <i class="fa-solid fa-arrow-right"></i>
        </button>
        <button type="submit" class="btn-primary btn-lg" id="btnPublicarTorneo">
          <i class="fa-solid fa-plus"></i> Publicar torneo
        </button>
        <a href="index.php" class="btn btn-lg">Cancelar</a>
      </div>
    </form>

  </main>

  <!-- FOOTER: informacion final comun del sistema. -->
  <footer class="footer">
    <div class="footer-links">
      <a href="como-funciona.php">Cómo funciona</a>
      <a href="crear-torneo.php">Crear torneo</a>
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
