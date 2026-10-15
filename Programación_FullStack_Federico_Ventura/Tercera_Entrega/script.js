// script.js
// Lógica de interacción del sitio (JavaScript del lado del cliente).
// Se espera a que el DOM esté completamente cargado antes de buscar elementos.
document.addEventListener('DOMContentLoaded', () => {

  // ===== MODO OSCURO =====
  // Se guarda la preferencia en localStorage para que se mantenga entre páginas y visitas.
  (function inicializarModoOscuro() {
    const CLAVE_TEMA = 'sgdm-tema';
    const botonesTema = document.querySelectorAll('.theme-toggle');
    if (!botonesTema.length) return;

    function actualizarIconos(esOscuro) {
      botonesTema.forEach((boton) => {
        const icono = boton.querySelector('i');
        if (icono) icono.className = esOscuro ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        boton.setAttribute('aria-label', esOscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
      });
    }

    // Aplica el tema guardado (si existe) apenas carga la página.
    const temaGuardado = localStorage.getItem(CLAVE_TEMA);
    const esOscuro = temaGuardado === 'oscuro';
    document.documentElement.classList.toggle('modo-oscuro', esOscuro);
    actualizarIconos(esOscuro);

    botonesTema.forEach((boton) => {
      boton.addEventListener('click', () => {
        const activo = document.documentElement.classList.toggle('modo-oscuro');
        localStorage.setItem(CLAVE_TEMA, activo ? 'oscuro' : 'claro');
        actualizarIconos(activo);
      });
    });
  })();

  // Atajo para no repetir la consulta de "el usuario prefiere menos movimiento" en cada bloque.
  const prefiereMenosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ===== BADGES "EN VIVO" =====
  // Cualquier badge cuyo texto empiece con "En curso" recibe el puntito pulsante,
  // sin importar en qué página ni con qué color de estado esté (naranja, verde, etc).
  (function marcarTorneosEnVivo() {
    document.querySelectorAll('.badge').forEach((badge) => {
      if (badge.textContent.trim().toLowerCase().startsWith('en curso')) {
        badge.classList.add('en-vivo');
      }
    });
  })();

  // ===== NÚMEROS QUE CUENTAN AL ENTRAR EN PANTALLA =====
  // Aplica a los números que muestran contadores con data-target (por ahora, perfil.html).
  (function inicializarContadores() {
    const contadores = document.querySelectorAll('.stat-num[data-target], .pstat-num[data-target]');
    if (!contadores.length) return;

    function formatear(valor, formato) {
      if (formato === 'k') {
        return valor >= 1000 ? `${(valor / 1000).toFixed(1)}k` : `${Math.round(valor)}`;
      }
      if (formato === '%') {
        return `${Math.round(valor)}%`;
      }
      return `${Math.round(valor)}`;
    }

    function animarContador(el) {
      const destino = Number(el.dataset.target);
      const formato = el.dataset.formato || '';

      if (prefiereMenosMovimiento || !destino) {
        el.textContent = formatear(destino, formato);
        return;
      }

      const duracion = 1100;
      const inicio = performance.now();

      function paso(ahora) {
        const t = Math.min((ahora - inicio) / duracion, 1);
        const suavizado = 1 - Math.pow(1 - t, 3); // easeOutCubic: arranca rápido y frena suave
        el.textContent = formatear(destino * suavizado, formato);
        if (t < 1) requestAnimationFrame(paso);
      }
      requestAnimationFrame(paso);
    }

    const observador = new IntersectionObserver((entradas) => {
      entradas.forEach((entrada) => {
        if (entrada.isIntersecting) {
          animarContador(entrada.target);
          observador.unobserve(entrada.target);
        }
      });
    }, { threshold: 0.5 });

    contadores.forEach((el) => observador.observe(el));
  })();

  // ===== MINI-ANIMACIONES DE "FORMATOS DE COMPETENCIA" =====
  // Se disparan una sola vez, cuando cada tarjeta de formato entra en pantalla.
  (function inicializarFormatosAnimados() {
    const tarjetasFormato = document.querySelectorAll('.tipo');
    if (!tarjetasFormato.length) return;

    const observador = new IntersectionObserver((entradas) => {
      entradas.forEach((entrada) => {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('en-vista');
          observador.unobserve(entrada.target);
        }
      });
    }, { threshold: 0.4 });

    tarjetasFormato.forEach((tarjeta) => observador.observe(tarjeta));
  })();

  // ===== CARRUSEL DE TORNEOS =====
  // Se usa en "Torneos activos" (index) y "Torneos en los que participa" (perfil).
  // Funciona con las flechas, con el dedo (scroll táctil nativo) y arrastrando con el mouse.
  document.querySelectorAll('.carrusel-wrap').forEach((wrap) => {
    const pista = wrap.querySelector('.tarjetas-carrusel');
    const flechaIzq = wrap.querySelector('[data-carrusel-dir="-1"]');
    const flechaDer = wrap.querySelector('[data-carrusel-dir="1"]');
    if (!pista) return;

    function actualizarFlechas() {
      const maxScroll = pista.scrollWidth - pista.clientWidth;
      const hayDesborde = maxScroll > 4;

      // Si todas las tarjetas entran sin necesidad de deslizar, se centran en vez
      // de quedar pegadas a la izquierda con un hueco vacío al lado.
      pista.classList.toggle('sin-desborde', !hayDesborde);

      if (flechaIzq) flechaIzq.disabled = !hayDesborde || pista.scrollLeft <= 4;
      if (flechaDer) flechaDer.disabled = !hayDesborde || pista.scrollLeft >= maxScroll - 4;
    }

    function desplazar(direccion) {
      const primeraTarjeta = pista.firstElementChild;
      const salto = primeraTarjeta ? primeraTarjeta.getBoundingClientRect().width + 14 : pista.clientWidth * 0.8;
      pista.scrollBy({ left: salto * direccion, behavior: 'smooth' });
    }

    if (flechaIzq) flechaIzq.addEventListener('click', () => desplazar(-1));
    if (flechaDer) flechaDer.addEventListener('click', () => desplazar(1));
    pista.addEventListener('scroll', actualizarFlechas);
    window.addEventListener('resize', actualizarFlechas);
    actualizarFlechas();

    // Arrastrar con el mouse (en celular el scroll táctil nativo ya funciona solo).
    let arrastrando = false;
    let inicioX = 0;
    let scrollInicial = 0;
    let movimiento = 0;

    pista.addEventListener('pointerdown', (e) => {
      if (e.pointerType !== 'mouse') return;
      arrastrando = true;
      movimiento = 0;
      pista.classList.add('arrastrando');
      inicioX = e.clientX;
      scrollInicial = pista.scrollLeft;
      pista.setPointerCapture(e.pointerId);
    });
    pista.addEventListener('pointermove', (e) => {
      if (!arrastrando) return;
      const delta = e.clientX - inicioX;
      movimiento = Math.max(movimiento, Math.abs(delta));
      pista.scrollLeft = scrollInicial - delta;
    });
    function soltar() {
      arrastrando = false;
      pista.classList.remove('arrastrando');
    }
    pista.addEventListener('pointerup', soltar);
    pista.addEventListener('pointercancel', soltar);
    pista.addEventListener('pointerleave', soltar);

    // Si hubo un arrastre real, evita que se dispare el click del link de la tarjeta.
    pista.addEventListener('click', (e) => {
      if (movimiento > 6) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);
  });

  // ===== PRECARGAR FILTROS DE BÚSQUEDA DESDE LA URL (si venís del buscador del hero) =====
  // Esto corre ANTES de que se arme el dropdown personalizado más abajo, para que
  // la etiqueta visible ya arranque mostrando el valor correcto (no solo el <select> oculto).
  (function precargarFiltrosDesdeUrl() {
    const parametros = new URLSearchParams(window.location.search);
    const texto = parametros.get('q');
    const deporte = parametros.get('deporte');
    const tipo = parametros.get('tipo');

    const inputTexto = document.querySelector('#busquedaTexto');
    if (inputTexto && texto) inputTexto.value = texto;

    const selectDeporte = document.querySelector('#filtroDeporte');
    if (selectDeporte && deporte) selectDeporte.value = deporte;

    const selectTipo = document.querySelector('#filtroTipo');
    if (selectTipo && tipo) selectTipo.value = tipo;
  })();

  // ===== BUSCADOR DEL HERO (index.html) → lleva lo elegido a busqueda.html =====
  const DEPORTE_CODIGO_A_NOMBRE = {
    futbol11: 'Fútbol 11', futbol5: 'Fútbol 5', basquetbol: 'Básquetbol', tenis: 'Tenis', padel: 'Pádel',
    pingpong: 'Ping pong', ajedrez: 'Ajedrez', truco: 'Truco', damas: 'Damas', cs2: 'CS2',
    lol: 'League of Legends', fortnite: 'Fortnite', valorant: 'Valorant', rocketleague: 'Rocket League', otro: 'Otro',
  };

  const btnBuscarHero = document.querySelector('#btnBuscarHero');
  if (btnBuscarHero) {
    btnBuscarHero.addEventListener('click', (evento) => {
      evento.preventDefault();

      const texto = document.querySelector('#heroBusquedaTexto').value.trim();
      const codigoDeporte = document.querySelector('#heroSelectDeporte').value;
      const tipo = document.querySelector('#heroSelectTipo').value;

      const parametros = new URLSearchParams();
      if (texto) parametros.set('q', texto);
      if (codigoDeporte) parametros.set('deporte', DEPORTE_CODIGO_A_NOMBRE[codigoDeporte] || '');
      if (tipo && tipo !== 'Todos los tipos') parametros.set('tipo', tipo);

      const query = parametros.toString();
      window.location.href = 'busqueda.php' + (query ? '?' + query : '');
    });
  }

  // ===== MENÚ HAMBURGUESA =====
  const navToggle = document.querySelector('#navToggle');
  const navLinks = document.querySelector('#navLinks');

  // Chequeamos que ambos elementos existan antes de operar sobre ellos.
  // (En admin.html, por ejemplo, no existen, y así evitamos errores en consola)
  if (navToggle && navLinks) {

    // Overlay oscuro de fondo: se crea una sola vez y se agrega al final del <body>
    const overlay = document.createElement('div');
    overlay.className = 'nav-overlay';
    document.body.appendChild(overlay);

    function cerrarMenu() {
      navLinks.classList.remove('active');
      overlay.classList.remove('active');
      navToggle.setAttribute('aria-expanded', 'false');
      navToggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }

    function abrirMenu() {
      navLinks.classList.add('active');
      overlay.classList.add('active');
      navToggle.setAttribute('aria-expanded', 'true');
      navToggle.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    }

    navToggle.addEventListener('click', () => {
      const estaAbierto = navLinks.classList.contains('active');
      estaAbierto ? cerrarMenu() : abrirMenu();
    });

    // Tocar el fondo oscuro también cierra el menú
    overlay.addEventListener('click', cerrarMenu);

    // Escape cierra el menú si está abierto
    document.addEventListener('keydown', (evento) => {
      if (evento.key === 'Escape' && navLinks.classList.contains('active')) {
        cerrarMenu();
        navToggle.focus();
      }
    });
  }

  // ===== DROPDOWNS PERSONALIZADOS (reemplazan visualmente a los <select> nativos) =====
  const customSelects = document.querySelectorAll('.custom-select');

  customSelects.forEach((wrapper) => {
    const nativeSelect = wrapper.querySelector('select');
    if (!nativeSelect) return; // si no hay select adentro, no hacemos nada

    // Arma un <li> de opción a partir de un <option> real del select
    function crearOpcion(option, trigger, optionsList) {
      const li = document.createElement('li');
      li.textContent = option.textContent;
      li.dataset.value = option.value;
      li.tabIndex = -1; // enfocable por JS/flechas, pero no con Tab normal
      if (option.selected) li.classList.add('selected');

      li.addEventListener('click', () => {
        // Sincronizamos el select real (por si algo más adelante lee su valor)
        nativeSelect.value = option.value;
        // Avisamos al resto de la página que el valor cambió (igual que haría un <select> normal)
        nativeSelect.dispatchEvent(new Event('change'));
        // Actualizamos el texto visible del botón
        trigger.querySelector('.custom-select-value').textContent = option.textContent;
        // Marcamos cuál quedó seleccionada visualmente
        optionsList.querySelectorAll('li').forEach((el) => el.classList.remove('selected'));
        li.classList.add('selected');
        // Cerramos el menú al elegir
        wrapper.classList.remove('open');
      });

      return li;
    }

    // Botón visible que reemplaza al select
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'custom-select-trigger';
    const textoInicial = nativeSelect.options[nativeSelect.selectedIndex].textContent;
    trigger.innerHTML =
      '<span class="custom-select-value">' + textoInicial + '</span>' +
      '<i class="fa-solid fa-chevron-down custom-select-arrow"></i>';

    // Lista de opciones, armada recorriendo los <option>/<optgroup> del select real
    const optionsList = document.createElement('ul');
    optionsList.className = 'custom-select-options';

    Array.from(nativeSelect.children).forEach((child) => {
      if (child.tagName === 'OPTGROUP') {
        const label = document.createElement('li');
        label.className = 'custom-select-group-label';
        label.textContent = child.label;
        optionsList.appendChild(label);

        Array.from(child.children).forEach((option) => {
          optionsList.appendChild(crearOpcion(option, trigger, optionsList));
        });
      } else if (child.tagName === 'OPTION') {
        optionsList.appendChild(crearOpcion(child, trigger, optionsList));
      }
    });

    wrapper.appendChild(trigger);
    wrapper.appendChild(optionsList);

    // Abre/cierra este dropdown y cierra cualquier otro que haya quedado abierto
    trigger.addEventListener('click', () => {
      const yaEstabaAbierto = wrapper.classList.contains('open');
      customSelects.forEach((w) => w.classList.remove('open'));
      if (!yaEstabaAbierto) {
        wrapper.classList.add('open');
      }
    });

    // Opciones reales (sin contar los <li> que son solo etiquetas de grupo)
    function opcionesNavegables() {
      return Array.from(optionsList.querySelectorAll('li:not(.custom-select-group-label)'));
    }

    // Teclado sobre el botón: abrir con flecha abajo y enfocar la primera opción
    trigger.addEventListener('keydown', (evento) => {
      if (evento.key === 'ArrowDown' && !wrapper.classList.contains('open')) {
        evento.preventDefault();
        customSelects.forEach((w) => w.classList.remove('open'));
        wrapper.classList.add('open');
        const primera = opcionesNavegables()[0];
        if (primera) primera.focus();
      }
    });

    // Teclado dentro de la lista de opciones: flechas para moverse, Enter/Espacio para elegir, Escape para salir
    optionsList.addEventListener('keydown', (evento) => {
      const opciones = opcionesNavegables();
      const indiceActual = opciones.indexOf(document.activeElement);

      if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        const siguiente = opciones[indiceActual + 1] || opciones[0];
        siguiente.focus();
      } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        const anterior = opciones[indiceActual - 1] || opciones[opciones.length - 1];
        anterior.focus();
      } else if (evento.key === 'Enter' || evento.key === ' ') {
        evento.preventDefault();
        if (document.activeElement) document.activeElement.click();
      } else if (evento.key === 'Escape') {
        evento.preventDefault();
        wrapper.classList.remove('open');
        trigger.focus();
      }
    });
  });

  // Cierra cualquier dropdown abierto si se hace click afuera de él
  document.addEventListener('click', (evento) => {
    customSelects.forEach((wrapper) => {
      if (!wrapper.contains(evento.target)) {
        wrapper.classList.remove('open');
      }
    });
  });

  // ===== SIDEBAR DE ADMIN (drawer deslizante en mobile) =====
  const adminToggle = document.querySelector('#adminSidebarToggle');
  const adminSidebar = document.querySelector('#adminSidebar');

  if (adminToggle && adminSidebar) {
    // Reutilizamos el mismo overlay oscuro que el menú principal (si no existe todavía, lo creamos)
    let overlay = document.querySelector('.nav-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'nav-overlay';
      document.body.appendChild(overlay);
    }

    function cerrarSidebar() {
      adminSidebar.classList.remove('open');
      overlay.classList.remove('active');
      adminToggle.setAttribute('aria-expanded', 'false');
    }
    function abrirSidebar() {
      adminSidebar.classList.add('open');
      overlay.classList.add('active');
      adminToggle.setAttribute('aria-expanded', 'true');
    }

    adminToggle.addEventListener('click', () => {
      adminSidebar.classList.contains('open') ? cerrarSidebar() : abrirSidebar();
    });
    overlay.addEventListener('click', () => {
      if (adminSidebar.classList.contains('open')) cerrarSidebar();
    });

    // Escape cierra el sidebar si está abierto
    document.addEventListener('keydown', (evento) => {
      if (evento.key === 'Escape' && adminSidebar.classList.contains('open')) {
        cerrarSidebar();
        adminToggle.focus();
      }
    });
  }

  // ===== VISTAS DEL PANEL ADMIN (Dashboard / Usuarios / Torneos, sin recargar la página) =====
  let alMostrarVista = null; // lo define el panel admin más abajo (carga las listas con fetch)
  const VISTAS_VALIDAS = ['dashboard', 'usuarios', 'torneos', 'auditoria', 'modulos'];
  const TITULOS_VISTA = { dashboard: 'Dashboard', usuarios: 'Usuarios', torneos: 'Torneos', auditoria: 'Auditoría', modulos: 'Formatos de competencia' };
  const tituloPagina = document.querySelector('.admin-page-titulo');

  function mostrarVista(vista, hacerScroll) {
    // Muestra solo los bloques marcados con data-view="...vista..." y oculta el resto
    document.querySelectorAll('[data-view]').forEach((bloque) => {
      const vistasDelBloque = bloque.dataset.view.split(' ');
      bloque.style.display = vistasDelBloque.includes(vista) ? '' : 'none';
    });

    if (tituloPagina && TITULOS_VISTA[vista]) {
      tituloPagina.textContent = TITULOS_VISTA[vista];
    }

    // Las listas del panel (usuarios, torneos, auditoría) se piden al servidor la primera vez que se abren
    if (alMostrarVista) alMostrarVista(vista);

    // Resalta el link correspondiente en el sidebar
    document.querySelectorAll('.admin-nav-link').forEach((link) => {
      link.classList.toggle('admin-nav-active', link.getAttribute('href') === '#' + vista);
    });

    // Al cambiar de vista por click volvemos al principio del contenido (no en la carga inicial)
    if (hacerScroll) {
      document.querySelector('.admin-main').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  // ===== NAVEGACIÓN DEL SIDEBAR Y ENLACES RELACIONADOS =====
  // Cualquier link del panel que apunte a #dashboard, #usuarios o #torneos cambia de vista;
  // #modulos/#reportes/#exportar todavía no tienen sección propia, así que avisamos en vez de fallar en silencio.
  document.querySelectorAll('.admin-nav-link, .section-link, .admin-acceso').forEach((link) => {
    const destino = link.getAttribute('href');
    if (!destino || !destino.startsWith('#')) return; // links a otras páginas reales (Configuración, Crear torneo) siguen de largo

    const vista = destino.slice(1);

    link.addEventListener('click', (evento) => {
      evento.preventDefault();

      if (VISTAS_VALIDAS.includes(vista)) {
        mostrarVista(vista, true);
      } else {
        mostrarToast('Sección en desarrollo');
      }

      // En mobile, navegar cierra el drawer para ver la sección
      if (adminSidebar && adminSidebar.classList.contains('open')) {
        adminSidebar.classList.remove('open');
        const overlay = document.querySelector('.nav-overlay');
        if (overlay) overlay.classList.remove('active');
        if (adminToggle) adminToggle.setAttribute('aria-expanded', 'false');
      }
    });
  });

  // Estado inicial: arrancamos en Dashboard (si estamos en admin.html)
  if (document.querySelector('.admin-nav-link')) {
    mostrarVista('dashboard', false);
  }

  // ===== AVISO FLOTANTE ("TOAST") =====
  // Función compartida para confirmar acciones del panel admin sin usar alert()
  let toastTimeout;
  function mostrarToast(mensaje) {
    let toast = document.querySelector('.toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'toast';
      document.body.appendChild(toast);
    }
    toast.textContent = mensaje;
    toast.classList.add('visible');

    clearTimeout(toastTimeout);
    // Los avisos largos (por ejemplo una explicación del servidor) necesitan más tiempo para leerse
    toastTimeout = setTimeout(() => {
      toast.classList.remove('visible');
    }, Math.max(2500, mensaje.length * 70));
  }

  // ===== COMUNICACIÓN ASINCRÓNICA (fetch) =====
  // Todo lo que sigue habla con el servidor sin recargar la página: se manda el pedido con
  // fetch, el servidor (api/*.php) responde JSON y acá se actualiza solo la parte que cambió.
  // El token CSRF sale de un <meta> que deja el PHP en la página.
  const tokenCsrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  async function pedirJson(url, opciones = {}) {
    try {
      const respuesta = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-CSRF-Token': tokenCsrf },
        ...opciones,
      });
      const datos = await respuesta.json().catch(() => null);
      if (!datos) return { ok: false, mensajes: ['El servidor respondió algo inesperado. Probá de nuevo.'] };
      return datos;
    } catch (error) {
      return { ok: false, mensajes: ['No se pudo conectar con el servidor. Revisá tu conexión.'] };
    }
  }

  // Crea un elemento con clase y texto (textContent: nunca interpreta HTML, evita XSS)
  function crear(etiqueta, clase, texto) {
    const nodo = document.createElement(etiqueta);
    if (clase) nodo.className = clase;
    if (texto !== undefined) nodo.textContent = texto;
    return nodo;
  }

  // Si la página se recargó después de una acción asincrónica, se muestra el aviso pendiente
  const avisoPendiente = sessionStorage.getItem('sgdm-aviso');
  if (avisoPendiente) {
    sessionStorage.removeItem('sgdm-aviso');
    mostrarToast(avisoPendiente);
  }

  // ----- Cargar y corregir resultados (detalle.php y fechas.php) -----
  // Los formularios .js-resultado-form funcionan solos (POST normal); acá se mandan por fetch
  // y el servidor devuelve la ronda y la tabla ya redibujadas.
  document.addEventListener('submit', async (evento) => {
    const form = evento.target.closest('.js-resultado-form');
    if (!form) return;
    evento.preventDefault();

    const boton = form.querySelector('button[type="submit"]');
    boton.disabled = true;

    const datos = new FormData(form);
    datos.set('accion', form.dataset.accion); // 'cargar' o 'corregir'
    const respuesta = await pedirJson('api/resultado.php', { method: 'POST', body: datos });
    boton.disabled = false;

    if (!respuesta.ok) {
      mostrarToast(respuesta.mensajes.join(' '));
      return;
    }

    // Cambió el estado del torneo o ya no quedan pendientes: se recarga para mostrar los botones correctos
    if (respuesta.recargar) {
      sessionStorage.setItem('sgdm-aviso', respuesta.mensajes[0]);
      window.location.reload();
      return;
    }

    const ronda = document.getElementById('ronda-' + respuesta.rondaId);
    if (ronda) ronda.outerHTML = respuesta.rondaHtml;

    const tabla = document.getElementById('tablaPosicionesContenedor');
    if (tabla && respuesta.tablaHtml !== null) tabla.innerHTML = respuesta.tablaHtml;

    document.querySelectorAll('[data-stat="jugados"]').forEach((n) => { n.textContent = respuesta.stats.jugados; });
    document.querySelectorAll('[data-stat="pendientes"]').forEach((n) => { n.textContent = respuesta.stats.pendientes; });

    mostrarToast(respuesta.mensajes[0]);
  });

  // ----- Panel de administración: listas reales con filtros y paginación -----
  const panelAdmin = document.querySelector('.admin-main');

  if (panelAdmin) {
    const miId = panelAdmin.dataset.yo;
    const ETIQUETA_ESTADO_TORNEO = { inscripciones_abiertas: 'Abierto', en_curso: 'En curso', finalizado: 'Finalizado' };
    const CLASE_ESTADO_TORNEO = { inscripciones_abiertas: 'estado-verde', en_curso: 'estado-naranja', finalizado: 'estado-azul' };
    const ETIQUETA_FORMATO = { liga: 'Liga', eliminacion: 'Eliminación', suizo: 'Suizo' };

    function formatearFechaHora(texto) {
      // 'AAAA-MM-DD HH:MM:SS' -> 'DD/MM/AAAA HH:MM'
      const [fecha, hora = ''] = String(texto).split(' ');
      const [a, m, d] = fecha.split('-');
      return d + '/' + m + '/' + a + ' ' + hora.slice(0, 5);
    }

    function insignia(clase, texto) {
      const nodo = crear('span', 'badge ' + clase, texto);
      nodo.style.marginTop = '0';
      return nodo;
    }

    // Cómo se dibuja cada fila de cada lista
    function filaUsuario(u) {
      const fila = crear('div', 'panel-fila');
      const principal = crear('div', 'panel-fila-main');
      principal.append(crear('span', 'admin-user-nombre', u.nombre_completo),
                       crear('span', 'admin-user-email', '@' + u.nombre_usuario + ' · ' + u.email));
      const rol = crear('span', 'admin-rol ' + (Number(u.rol_id) === 2 ? 'admin-rol-organizador' : 'admin-rol-participante'), u.rol);
      let estado;
      if (Number(u.eliminado) === 1) estado = insignia('estado-gris', 'Baja');
      else if (Number(u.activo) === 1) estado = insignia('estado-verde', 'Activo');
      else estado = insignia('estado-rojo', 'Suspendido');
      fila.append(principal, rol, estado);

      // Se puede suspender/reactivar a cualquiera menos a uno mismo, a otro admin o a una cuenta dada de baja
      const esAdmin = Number(u.rol_id) === 1;
      if (Number(u.eliminado) !== 1 && !esAdmin && String(u.id) !== miId) {
        const activo = Number(u.activo) === 1;
        const boton = crear('button', 'admin-btn-icono' + (activo ? ' admin-btn-danger' : ''));
        boton.type = 'button';
        boton.title = activo ? 'Suspender' : 'Reactivar';
        boton.setAttribute('aria-label', boton.title + ' a ' + u.nombre_completo);
        boton.innerHTML = activo ? '<i class="fa-solid fa-ban"></i>' : '<i class="fa-solid fa-rotate-left"></i>';
        boton.addEventListener('click', () => cambiarEstadoUsuario(u, !activo, boton));
        fila.append(boton);
      }
      return fila;
    }

    function filaTorneo(t) {
      const fila = crear('div', 'panel-fila');
      const principal = crear('div', 'panel-fila-main');
      const enlace = crear('a', 'admin-user-nombre', t.nombre);
      enlace.href = 'detalle.php?id=' + encodeURIComponent(t.id);
      principal.append(enlace, crear('span', 'admin-user-email',
        t.deporte + ' · ' + (ETIQUETA_FORMATO[t.formato] || t.formato) + ' · ' + t.inscriptos + ' inscriptos · ' +
        t.partidos_jugados + ' jugados / ' + t.partidos_pendientes + ' pendientes · ' +
        (t.nombre_organizador ? 'Organiza: ' + t.nombre_organizador : 'Sin organizador')));
      fila.append(principal, insignia(CLASE_ESTADO_TORNEO[t.estado] || '', ETIQUETA_ESTADO_TORNEO[t.estado] || t.estado));
      return fila;
    }

    function filaAuditoria(a) {
      const fila = crear('div', 'panel-fila');
      const principal = crear('div', 'panel-fila-main');
      principal.append(crear('span', 'admin-user-nombre', a.detalle || '(sin detalle)'),
        crear('span', 'admin-user-email',
          formatearFechaHora(a.fecha) + ' · ' + (a.nombre_usuario ? '@' + a.nombre_usuario : 'sistema') + (a.ip ? ' · ' + a.ip : '')));
      fila.append(principal, insignia('estado-azul', a.accion));
      return fila;
    }

    // Una lista de cada tipo: dónde se dibuja, a qué acción del servidor se pide y cómo se arma cada fila
    const PANELES = {
      usuarios:  { lista: '#listaUsuarios',  dibujar: filaUsuario,   vacio: 'No hay usuarios con ese filtro.' },
      torneos:   { lista: '#listaTorneos',   dibujar: filaTorneo,    vacio: 'No hay torneos con ese filtro.' },
      auditoria: { lista: '#listaAuditoria', dibujar: filaAuditoria, vacio: 'No hay registros con ese filtro.' },
    };
    const estadoPaneles = {};

    async function cargarPanel(nombre, pagina) {
      const config = PANELES[nombre];
      const lista = document.querySelector(config.lista);
      const barra = document.querySelector('[data-panel="' + nombre + '"]');
      const pie = document.querySelector('[data-paginacion="' + nombre + '"]');
      const estado = estadoPaneles[nombre] = estadoPaneles[nombre] || { pagina: 1 };
      estado.pagina = pagina || estado.pagina;

      const parametros = new URLSearchParams({ accion: nombre, pagina: estado.pagina });
      barra.querySelectorAll('[data-filtro]').forEach((campo) => parametros.set(campo.dataset.filtro, campo.value.trim()));

      lista.replaceChildren(crear('p', 'tabla-sin-datos', 'Cargando…'));
      const respuesta = await pedirJson('api/admin.php?' + parametros.toString());

      if (!respuesta.ok) {
        const error = crear('p', 'tabla-sin-datos', respuesta.mensajes.join(' ') + ' ');
        const reintentar = crear('button', 'btn', 'Reintentar');
        reintentar.type = 'button';
        reintentar.addEventListener('click', () => cargarPanel(nombre));
        error.append(reintentar);
        lista.replaceChildren(error);
        pie.replaceChildren();
        return;
      }

      estado.pagina = respuesta.pagina;
      lista.replaceChildren(...(respuesta.filas.length
        ? respuesta.filas.map(config.dibujar)
        : [crear('p', 'tabla-sin-datos', config.vacio)]));

      const anterior = crear('button', 'btn', '← Anterior');
      const siguiente = crear('button', 'btn', 'Siguiente →');
      anterior.type = siguiente.type = 'button';
      anterior.disabled = respuesta.pagina <= 1;
      siguiente.disabled = respuesta.pagina >= respuesta.paginas;
      anterior.addEventListener('click', () => cargarPanel(nombre, respuesta.pagina - 1));
      siguiente.addEventListener('click', () => cargarPanel(nombre, respuesta.pagina + 1));
      pie.replaceChildren(anterior,
        crear('span', 'tabla-hint', 'Página ' + respuesta.pagina + ' de ' + respuesta.paginas + ' · ' + respuesta.total + ' en total'),
        siguiente);
    }

    // Filtros: el texto busca mientras se escribe (con una pequeña espera), los selectores al cambiar
    Object.keys(PANELES).forEach((nombre) => {
      const barra = document.querySelector('[data-panel="' + nombre + '"]');
      if (!barra) return;
      let espera;
      barra.querySelectorAll('[data-filtro]').forEach((campo) => {
        campo.addEventListener(campo.tagName === 'SELECT' ? 'change' : 'input', () => {
          clearTimeout(espera);
          espera = setTimeout(() => cargarPanel(nombre, 1), campo.tagName === 'SELECT' ? 0 : 300);
        });
      });
    });

    // Se llama desde mostrarVista(): la primera vez que se abre una lista, se pide al servidor
    alMostrarVista = (vista) => {
      if (PANELES[vista] && !estadoPaneles[vista]) cargarPanel(vista, 1);
    };

    async function cambiarEstadoUsuario(usuario, activar, boton) {
      const pregunta = activar
        ? '¿Reactivar a ' + usuario.nombre_completo + '?'
        : '¿Suspender a ' + usuario.nombre_completo + '? No va a poder iniciar sesión hasta que lo reactives.';
      if (!confirm(pregunta)) return;

      boton.disabled = true;
      const datos = new FormData();
      datos.set('accion', 'estado_usuario');
      datos.set('usuario_id', usuario.id);
      datos.set('activo', activar ? '1' : '0');
      datos.set('csrf', tokenCsrf);
      const respuesta = await pedirJson('api/admin.php', { method: 'POST', body: datos });
      boton.disabled = false;

      mostrarToast(respuesta.mensajes.join(' '));
      if (!respuesta.ok) return;

      // Actualiza el número del dashboard y vuelve a pedir la lista (el estado quedó guardado en el servidor)
      Object.entries(respuesta.resumen).forEach(([campo, valor]) => {
        document.querySelectorAll('[data-resumen="' + campo + '"]').forEach((n) => { n.textContent = valor; });
      });
      cargarPanel('usuarios');
      if (estadoPaneles.auditoria) estadoPaneles.auditoria = null; // la próxima vez que se abra se recarga con la acción nueva
    }
  }

  // ===== FILTRADO Y PAGINACIÓN DE TORNEOS (busqueda.html) =====
  const filtroDeporte = document.querySelector('#filtroDeporte');
  const filtroTipo = document.querySelector('#filtroTipo');
  const filtroEstado = document.querySelector('#filtroEstado');
  const busquedaTexto = document.querySelector('#busquedaTexto');
  const btnBuscarTexto = document.querySelector('#btnBuscarTexto');
  const tarjetas = document.querySelectorAll('.resultado-card');
  const contador = document.querySelector('.busqueda-count span');

  const TAMANIO_PAGINA = 3; // cuántos torneos se muestran por página
  const btnPaginaAnterior = document.querySelector('#paginaAnterior');
  const btnPaginaSiguiente = document.querySelector('#paginaSiguiente');
  const numerosPagina = document.querySelector('#paginacionNumeros');
  let paginaActual = 1;

  if (filtroDeporte && filtroTipo && filtroEstado) {

    // Devuelve solo las tarjetas que coinciden con los filtros actuales
    function obtenerCoincidentes() {
      const deporte = filtroDeporte.value;
      const tipo = filtroTipo.value;
      const estado = filtroEstado.value;
      const texto = busquedaTexto ? busquedaTexto.value.trim().toLowerCase() : '';

      return Array.from(tarjetas).filter((tarjeta) => {
        // value vacío ("Todos los...") significa que ese filtro no restringe nada
        const coincideDeporte = deporte === '' || tarjeta.dataset.deporte === deporte;
        const coincideTipo = tipo === '' || tarjeta.dataset.tipo === tipo;
        const coincideEstado = estado === '' || tarjeta.dataset.estado === estado;
        const nombre = tarjeta.querySelector('.resultado-nombre');
        const coincideTexto = texto === '' || (nombre && nombre.textContent.toLowerCase().includes(texto));
        return coincideDeporte && coincideTipo && coincideEstado && coincideTexto;
      });
    }

    // Muestra solo las tarjetas de la página actual, arma los botones de número
    // y actualiza el contador y el estado de las flechas
    function mostrarResultados() {
      const coincidentes = obtenerCoincidentes();
      const totalPaginas = Math.max(1, Math.ceil(coincidentes.length / TAMANIO_PAGINA));
      if (paginaActual > totalPaginas) paginaActual = totalPaginas;

      tarjetas.forEach((tarjeta) => { tarjeta.style.display = 'none'; });

      const inicio = (paginaActual - 1) * TAMANIO_PAGINA;
      const paginaDeCoincidentes = coincidentes.slice(inicio, inicio + TAMANIO_PAGINA);
      paginaDeCoincidentes.forEach((tarjeta) => { tarjeta.style.display = ''; });

      if (contador) {
        contador.textContent = coincidentes.length === 1
          ? '1 torneo encontrado'
          : coincidentes.length + ' torneos encontrados';
      }

      // Sin resultados: mostramos el aviso y ocultamos la paginación (no tiene sentido paginar 0 torneos)
      const busquedaVacio = document.querySelector('#busquedaVacio');
      const paginacionNav = document.querySelector('#paginacion');
      if (busquedaVacio) busquedaVacio.style.display = coincidentes.length === 0 ? '' : 'none';
      if (paginacionNav) paginacionNav.style.display = coincidentes.length === 0 ? 'none' : '';

      // Números de página (1, 2, 3...)
      if (numerosPagina) {
        numerosPagina.innerHTML = '';
        for (let pagina = 1; pagina <= totalPaginas; pagina++) {
          const boton = document.createElement('button');
          boton.type = 'button';
          boton.className = 'pagination-num' + (pagina === paginaActual ? ' pagination-num-active' : '');
          boton.textContent = pagina;
          boton.addEventListener('click', () => {
            paginaActual = pagina;
            mostrarResultados();
          });
          numerosPagina.appendChild(boton);
        }
      }

      if (btnPaginaAnterior) btnPaginaAnterior.disabled = paginaActual === 1;
      if (btnPaginaSiguiente) btnPaginaSiguiente.disabled = paginaActual === totalPaginas;
    }

    // Cambiar cualquier filtro vuelve a la página 1
    function aplicarFiltros() {
      paginaActual = 1;
      mostrarResultados();
    }

    filtroDeporte.addEventListener('change', aplicarFiltros);
    filtroTipo.addEventListener('change', aplicarFiltros);
    filtroEstado.addEventListener('change', aplicarFiltros);

    if (busquedaTexto) {
      busquedaTexto.addEventListener('input', aplicarFiltros); // busca en vivo mientras escribís
      busquedaTexto.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
          evento.preventDefault();
          aplicarFiltros();
        }
      });
    }
    if (btnBuscarTexto) {
      btnBuscarTexto.addEventListener('click', aplicarFiltros);
    }

    // Si venimos del buscador del hero con filtros ya cargados, mostramos los resultados
    // filtrados de una, en vez de esperar a que el usuario toque algo.
    if (new URLSearchParams(window.location.search).toString()) {
      aplicarFiltros();
    }

    if (btnPaginaAnterior) {
      btnPaginaAnterior.addEventListener('click', () => {
        if (paginaActual > 1) {
          paginaActual--;
          mostrarResultados();
        }
      });
    }
    if (btnPaginaSiguiente) {
      btnPaginaSiguiente.addEventListener('click', () => {
        paginaActual++;
        mostrarResultados();
      });
    }

    mostrarResultados(); // estado inicial, sin filtros aplicados
  }

  // ===== VALIDACIÓN DE FORMULARIOS =====

  // Marca un campo como válido o inválido y muestra/oculta su mensaje de error.
  // esValido es una función que recibe el valor del campo y devuelve true/false.
  // Devuelve true/false para poder combinarlo con la validación de los demás campos.
  function validarCampo(input, errorId, esValido, mensaje) {
    if (!input) return true;
    const grupo = input.closest('.form-group');
    const errorSpan = document.getElementById(errorId);
    const valor = input.value.trim();
    const valido = esValido(valor);

    if (grupo) {
      grupo.classList.toggle('has-error', !valido);
      grupo.classList.toggle('has-success', valido && valor !== '');
    }
    if (errorSpan) {
      errorSpan.textContent = valido ? '' : mensaje;
    }
    return valido;
  }

  // ===== MODAL DE CONFIGURACIÓN (perfil.php) =====
  const modalConfiguracion = document.querySelector('#modalConfiguracion');
  const btnAbrirConfiguracion = document.querySelector('#btnAbrirConfiguracion');
  const btnCerrarConfiguracion = document.querySelector('#btnCerrarConfiguracion');

  if (modalConfiguracion) {
    function abrirModalConfiguracion() {
      modalConfiguracion.style.display = 'flex';
    }
    function cerrarModalConfiguracion() {
      modalConfiguracion.style.display = 'none';
    }

    if (btnAbrirConfiguracion) {
      btnAbrirConfiguracion.addEventListener('click', abrirModalConfiguracion);
    }
    if (btnCerrarConfiguracion) {
      btnCerrarConfiguracion.addEventListener('click', cerrarModalConfiguracion);
    }
    // Cerrar clickeando afuera del panel (sobre el fondo oscuro)
    modalConfiguracion.addEventListener('click', (evento) => {
      if (evento.target === modalConfiguracion) cerrarModalConfiguracion();
    });
    // Cerrar con la tecla Escape
    document.addEventListener('keydown', (evento) => {
      if (evento.key === 'Escape' && modalConfiguracion.style.display === 'flex') {
        cerrarModalConfiguracion();
      }
    });
  }

  // ---- Formulario de login ----
  const loginForm = document.querySelector('#loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', (evento) => {
      const loginOk = validarCampo(
        document.querySelector('#login'), 'error-login',
        (v) => v !== '', 'Ingresá tu usuario o correo.'
      );
      const passwordOk = validarCampo(
        document.querySelector('#password'), 'error-password',
        (v) => v !== '', 'Ingresá tu contraseña.'
      );

      // Si algo está mal, no lo dejamos llegar al servidor. Si está todo bien,
      // NO llamamos a preventDefault: el formulario se manda solo a login.php.
      if (!(loginOk && passwordOk)) {
        evento.preventDefault();
      }
    });
  }

  const linkOlvideContrasena = document.querySelector('#linkOlvideContrasena');
  if (linkOlvideContrasena) {
    linkOlvideContrasena.addEventListener('click', (evento) => {
      evento.preventDefault();
      mostrarToast('Recuperar contraseña: función en desarrollo.');
    });
  }

  // ---- Formulario de registro ----
  const registerForm = document.querySelector('#registerForm');
  if (registerForm) {

    // Medidor de fortaleza de contraseña, en vivo mientras el usuario escribe
    const passwordInput = document.querySelector('#registerForm #password');
    const strengthBox = document.querySelector('#passwordStrength');
    const strengthFill = document.querySelector('#strengthFill');
    const strengthLabel = document.querySelector('#strengthLabel');

    function calcularFortaleza(valor) {
      let puntos = 0;
      if (valor.length >= 8) puntos++;
      if (/[a-z]/.test(valor) && /[A-Z]/.test(valor)) puntos++;
      if (/[0-9]/.test(valor)) puntos++;
      if (/[^a-zA-Z0-9]/.test(valor)) puntos++;
      return puntos;
    }

    if (passwordInput && strengthBox) {
      const niveles = [
        { ancho: '20%',  color: '#e53935', texto: 'Muy débil' },
        { ancho: '40%',  color: '#e53935', texto: 'Débil' },
        { ancho: '60%',  color: '#f9a825', texto: 'Media' },
        { ancho: '80%',  color: '#43A047', texto: 'Fuerte' },
        { ancho: '100%', color: '#2e7d32', texto: 'Muy fuerte' },
      ];

      passwordInput.addEventListener('input', () => {
        const valor = passwordInput.value;
        if (valor === '') {
          strengthBox.style.display = 'none';
          return;
        }
        strengthBox.style.display = 'flex';
        const nivel = niveles[calcularFortaleza(valor)];
        strengthFill.style.width = nivel.ancho;
        strengthFill.style.background = nivel.color;
        strengthLabel.textContent = nivel.texto;
        strengthLabel.style.color = nivel.color;
      });
    }

    registerForm.addEventListener('submit', (evento) => {
      const password = document.querySelector('#registerForm #password');

      let ok = true;
      ok = validarCampo(
        document.querySelector('#usuario'), 'error-usuario',
        (v) => /^[a-zA-Z0-9_]+$/.test(v), 'Solo letras, números y guion bajo, sin espacios.'
      ) && ok;

      ok = validarCampo(
        document.querySelector('#nombre'), 'error-nombre',
        (v) => /^[A-Za-zÀ-ÿ]+\s[A-Za-zÀ-ÿ]+/.test(v), 'Ingresá tu nombre y apellido.'
      ) && ok;

      ok = validarCampo(
        document.querySelector('#email'), 'error-email',
        (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Ingresá un correo válido.'
      ) && ok;

      // El celular es opcional: solo lo validamos si el usuario escribió algo
      ok = validarCampo(
        document.querySelector('#celular'), 'error-celular',
        (v) => v === '' || /^[0-9+\s]+$/.test(v), 'Ingresá solo números, espacios y "+".'
      ) && ok;

      ok = validarCampo(
        password, 'error-password',
        (v) => v.length >= 8, 'La contraseña debe tener al menos 8 caracteres.'
      ) && ok;

      ok = validarCampo(
        document.querySelector('#confirm'), 'error-confirm',
        (v) => v !== '' && v === password.value, 'Las contraseñas no coinciden.'
      ) && ok;

      // Si algo está mal, no lo dejamos llegar al servidor. Si está todo bien,
      // NO llamamos a preventDefault: el formulario se manda solo a register.php,
      // que es quien hace la validación que realmente importa y crea el usuario.
      if (!ok) {
        evento.preventDefault();
      }
    });
  }

  // ---- Formulario de crear torneo ----

  // Duración del torneo: se calcula sola a partir de las fechas de inicio y fin,
  // en vez de pedirla aparte (evita que el usuario cargue datos que no coinciden).
  const inicioTorneoInput = document.querySelector('#fecha-inicio-torneo');
  const finTorneoInput = document.querySelector('#fecha-fin-torneo');
  const duracionTexto = document.querySelector('#duracionTexto');
  const duracionDiasReal = document.querySelector('#duracionDiasReal');

  if (inicioTorneoInput && finTorneoInput && duracionTexto && duracionDiasReal) {
    function actualizarDuracion() {
      const valorInicio = inicioTorneoInput.value;
      const valorFin = finTorneoInput.value;

      // La fecha de fin no puede ser anterior a la de inicio.
      finTorneoInput.min = valorInicio || finTorneoInput.getAttribute('min');

      if (!valorInicio || !valorFin) {
        duracionTexto.textContent = 'Elegí ambas fechas';
        duracionDiasReal.value = '';
        return;
      }

      // "T00:00:00" evita que el cambio de huso horario corra la fecha un día.
      const inicio = new Date(`${valorInicio}T00:00:00`);
      const fin = new Date(`${valorFin}T00:00:00`);

      if (fin < inicio) {
        duracionTexto.textContent = 'La fecha de fin no puede ser anterior al inicio';
        duracionDiasReal.value = '';
        return;
      }

      // +1 porque el propio día de inicio ya cuenta como parte del torneo.
      const dias = Math.round((fin - inicio) / 86400000) + 1;
      duracionTexto.textContent = dias === 1 ? '1 día' : `${dias} días`;
      duracionDiasReal.value = dias;
    }

    inicioTorneoInput.addEventListener('change', actualizarDuracion);
    finTorneoInput.addEventListener('change', actualizarDuracion);
    actualizarDuracion(); // estado inicial
  }

  // Muestra el nombre del PDF elegido en vez de dejar el texto genérico para siempre
  const inputReglasPdf = document.querySelector('#reglas-pdf');
  const textoReglasPdf = document.querySelector('#reglasPdfTexto');
  if (inputReglasPdf && textoReglasPdf) {
    inputReglasPdf.addEventListener('change', () => {
      const archivo = inputReglasPdf.files[0];
      textoReglasPdf.textContent = archivo ? archivo.name : 'Seleccioná un archivo PDF';
    });
  }

  // El monto y la descripción del premio solo tienen sentido según el tipo elegido
  const radiosPremio = document.querySelectorAll('input[name="tipo_premio"]');
  const inputMontoPremio = document.querySelector('#monto-premio');
  const inputDescPremio = document.querySelector('#desc-premio');

  if (radiosPremio.length && inputMontoPremio && inputDescPremio) {
    const grupoMontoPremio = inputMontoPremio.closest('.form-group');
    const grupoDescPremio = inputDescPremio.closest('.form-group');

    function actualizarCamposPremio() {
      const seleccionado = document.querySelector('input[name="tipo_premio"]:checked').value;
      grupoMontoPremio.style.display = seleccionado === 'monetario' ? '' : 'none';
      grupoDescPremio.style.display = seleccionado === 'otro' ? '' : 'none';
    }

    radiosPremio.forEach((radio) => radio.addEventListener('change', actualizarCamposPremio));
    actualizarCamposPremio(); // estado inicial, según lo que venga marcado por defecto
  }

  // "Categoría" (género) solo aplica si el creador activa "Restringir por género"
  const checkboxRestringirGenero = document.querySelector('#restringirGenero');
  const grupoCategoriaGenero = document.querySelector('#grupoCategoriaGenero');

  if (checkboxRestringirGenero && grupoCategoriaGenero) {
    function actualizarCategoriaGenero() {
      grupoCategoriaGenero.style.display = checkboxRestringirGenero.checked ? '' : 'none';
      // Si no se restringe por género, el torneo queda "mixto" por defecto
      if (!checkboxRestringirGenero.checked) {
        const radioMixto = document.querySelector('input[name="categoria_genero"][value="mixto"]');
        if (radioMixto) radioMixto.checked = true;
      }
    }

    checkboxRestringirGenero.addEventListener('change', actualizarCategoriaGenero);
    actualizarCategoriaGenero(); // estado inicial
  }

  // Fechas de crear torneo: apertura -> cierre de inscripciones -> inicio -> fin. Cada una
  // no puede ser anterior a la anterior (el servidor lo vuelve a controlar).
  const cadenaFechas = ['#fecha-inicio-inscripcion', '#fecha-cierre-inscripcion', '#fecha-inicio-torneo', '#fecha-fin-torneo']
    .map((selector) => document.querySelector(selector));

  if (cadenaFechas.every(Boolean)) {
    const minimoOriginal = cadenaFechas.map((campo) => campo.min);

    function encadenarFechas() {
      cadenaFechas.forEach((campo, i) => {
        if (i === 0) return;
        const anterior = cadenaFechas[i - 1].value;
        campo.min = anterior && anterior > minimoOriginal[i] ? anterior : minimoOriginal[i];
        if (campo.value && campo.value < campo.min) campo.value = '';
      });
    }

    cadenaFechas.forEach((campo) => campo.addEventListener('change', encadenarFechas));
    encadenarFechas();
  }

  // La modalidad y el tamaño de los equipos salen del deporte elegido (cada <option> trae
  // data-jugadores desde la tabla deportes). Con "Otro" (data-libre="1") se pide el nombre
  // del juego y el tamaño a mano.
  const selectDeporte = document.querySelector('#deporte');
  const infoModalidad = document.querySelector('#infoModalidad');

  if (selectDeporte && infoModalidad) {
    const grupoOtro   = document.querySelector('#grupoDeporteOtro');
    const inputOtro   = document.querySelector('#deporte_otro');
    const inputJugOtro = document.querySelector('#jugadores_otro');

    function actualizarInfoModalidad() {
      const opcion = selectDeporte.options[selectDeporte.selectedIndex];
      const esLibre = !!opcion && opcion.dataset.libre === '1';

      if (grupoOtro) {
        grupoOtro.style.display = esLibre ? '' : 'none';
        if (inputOtro) inputOtro.required = esLibre;
        if (inputJugOtro) inputJugOtro.required = esLibre;
      }

      let jugadores = opcion ? parseInt(opcion.dataset.jugadores, 10) : NaN;
      let nombre = opcion ? opcion.textContent.trim() : '';

      if (esLibre) {
        jugadores = inputJugOtro ? parseInt(inputJugOtro.value, 10) : NaN;
        nombre = (inputOtro && inputOtro.value.trim()) || 'El juego';
        if (!jugadores) {
          infoModalidad.textContent = 'Escribí el nombre del juego e indicá cuántos jugadores lleva cada equipo.';
          return;
        }
      }

      if (!jugadores) {
        infoModalidad.textContent = 'Se define según el deporte que elijas.';
        return;
      }
      infoModalidad.textContent = jugadores === 1
        ? 'Individual: ' + nombre + ' se juega de a uno.'
        : 'Por equipos: ' + nombre + ' se juega en equipos de ' + jugadores + ' jugadores.';
    }

    selectDeporte.addEventListener('change', actualizarInfoModalidad);
    if (inputOtro) inputOtro.addEventListener('input', actualizarInfoModalidad);
    if (inputJugOtro) inputJugOtro.addEventListener('input', actualizarInfoModalidad);
    actualizarInfoModalidad(); // estado inicial
  }

  // Mis equipos: si el deporte elegido es "Otro" (tamaño libre), se piden el nombre del juego y el tamaño.
  const selectDeporteEquipo = document.querySelector('#deporteEquipo');
  const grupoOtroEquipo = document.querySelector('#grupoOtroEquipo');

  if (selectDeporteEquipo && grupoOtroEquipo) {
    const camposOtroEquipo = grupoOtroEquipo.querySelectorAll('input');

    function actualizarOtroEquipo() {
      const opcion = selectDeporteEquipo.options[selectDeporteEquipo.selectedIndex];
      const esLibre = !!opcion && opcion.dataset.libre === '1';
      grupoOtroEquipo.style.display = esLibre ? '' : 'none';
      camposOtroEquipo.forEach((campo) => { campo.required = esLibre; });
    }

    selectDeporteEquipo.addEventListener('change', actualizarOtroEquipo);
    actualizarOtroEquipo(); // estado inicial
  }

  // Formulario grande, con validación HTML5 (required, pattern) ya puesta en el markup.
  // En vez de repetir esas reglas a mano en JS, usamos checkValidity()/reportValidity(),
  // que el propio navegador resuelve leyendo esos atributos.
  const crearTorneoForm = document.querySelector('#crearTorneoForm');

  // ---- Wizard: navegación entre los 6 pasos del formulario ----
  if (crearTorneoForm) {
    const pasos = Array.from(crearTorneoForm.querySelectorAll('.crear-seccion'));
    const stepperItems = Array.from(document.querySelectorAll('.crear-step'));
    const btnPasoAnterior = document.querySelector('#btnPasoAnterior');
    const btnPasoSiguiente = document.querySelector('#btnPasoSiguiente');
    const btnPublicarTorneo = document.querySelector('#btnPublicarTorneo');
    const totalPasos = pasos.length;
    let pasoActual = 1;

    function mostrarPaso(numero) {
      pasos.forEach((seccion) => {
        seccion.style.display = Number(seccion.dataset.step) === numero ? '' : 'none';
      });

      stepperItems.forEach((item) => {
        const destino = Number(item.dataset.stepTarget);
        item.classList.toggle('activo', destino === numero);
        item.classList.toggle('completado', destino < numero);
      });

      if (btnPasoAnterior) btnPasoAnterior.style.display = numero === 1 ? 'none' : '';
      if (btnPasoSiguiente) btnPasoSiguiente.style.display = numero === totalPasos ? 'none' : '';
      if (btnPublicarTorneo) btnPublicarTorneo.style.display = numero === totalPasos ? '' : 'none';

      pasoActual = numero;
      crearTorneoForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Valida solo los campos del paso actual (no todo el formulario todavía)
    function pasoActualEsValido() {
      const campos = pasos[pasoActual - 1].querySelectorAll('input, select, textarea');
      for (const campo of campos) {
        if (!campo.checkValidity()) {
          campo.reportValidity();
          return false;
        }
      }
      return true;
    }

    if (btnPasoSiguiente) {
      btnPasoSiguiente.addEventListener('click', () => {
        if (pasoActualEsValido() && pasoActual < totalPasos) {
          mostrarPaso(pasoActual + 1);
        }
      });
    }

    if (btnPasoAnterior) {
      btnPasoAnterior.addEventListener('click', () => {
        if (pasoActual > 1) mostrarPaso(pasoActual - 1);
      });
    }

    // Desde el stepper solo se puede saltar a un paso ya completado (hacia atrás), no adelantarse sin validar
    stepperItems.forEach((item) => {
      item.addEventListener('click', () => {
        const destino = Number(item.dataset.stepTarget);
        if (destino < pasoActual) mostrarPaso(destino);
      });
    });

    // Enter en un campo de texto avanza de paso en vez de enviar el formulario antes de tiempo
    crearTorneoForm.addEventListener('keydown', (evento) => {
      const esTextarea = evento.target.tagName === 'TEXTAREA';
      if (evento.key === 'Enter' && !esTextarea && pasoActual < totalPasos) {
        evento.preventDefault();
        btnPasoSiguiente.click();
      }
    });

    mostrarPaso(1); // arrancamos siempre en el primer paso
  }

  if (crearTorneoForm) {
    crearTorneoForm.addEventListener('submit', (evento) => {
      if (!crearTorneoForm.checkValidity()) {
        evento.preventDefault();
        crearTorneoForm.reportValidity(); // resalta el primer campo inválido
      }
      // Si es válido, no llamamos a preventDefault: el formulario se manda
      // solo a crear-torneo.php (mismo archivo), que ya sabe procesar el POST.
    });
  }

  // ---- Formulario de editar perfil ----
  // Contador de caracteres en vivo para la bio (tiene maxlength=200 en el HTML)
  const perfilBioInput = document.querySelector('#perfilBioInput');
  const perfilBioContador = document.querySelector('#perfilBioContador');
  if (perfilBioInput && perfilBioContador) {
    function actualizarContadorBio() {
      const restantes = 200 - perfilBioInput.value.length;
      perfilBioContador.textContent = restantes + ' caracteres restantes.';
    }
    perfilBioInput.addEventListener('input', actualizarContadorBio);
    actualizarContadorBio(); // estado inicial, según el texto que ya tenga cargado
  }

  const editarPerfilForm = document.querySelector('#editarPerfilForm');
  if (editarPerfilForm) {
    editarPerfilForm.addEventListener('submit', (evento) => {
      if (!editarPerfilForm.checkValidity()) {
        evento.preventDefault();
        editarPerfilForm.reportValidity();
      }
      // Si es válido, no llamamos a preventDefault: se manda solo a perfil.php,
      // que ya sabe guardar los cambios en la base de datos.
    });
  }

  // Vista previa de la foto de perfil elegida, y envío automático al formulario
  // dedicado (fotoPerfilForm) para que quede guardada sin tocar "Guardar cambios".
  const perfilFotoInput = document.querySelector('#perfilFotoInput');
  const perfilAvatarCirculo = document.querySelector('#perfilAvatarCirculo');
  const fotoPerfilForm = document.querySelector('#fotoPerfilForm');
  if (perfilFotoInput && perfilAvatarCirculo) {
    perfilFotoInput.addEventListener('change', () => {
      const archivo = perfilFotoInput.files[0];
      if (!archivo) return;

      const lector = new FileReader();
      lector.onload = () => {
        perfilAvatarCirculo.style.backgroundImage = 'url(' + lector.result + ')';
        perfilAvatarCirculo.style.backgroundSize = 'cover';
        perfilAvatarCirculo.style.backgroundPosition = 'center';
        perfilAvatarCirculo.textContent = ''; // ocultamos las iniciales mientras se ve la foto
      };
      lector.readAsDataURL(archivo);

      if (fotoPerfilForm) fotoPerfilForm.submit();
    });
  }

  // ---- Formularios de configuración ----
  const formContacto = document.querySelector('#formContacto');
  if (formContacto) {
    formContacto.addEventListener('submit', (evento) => {
      evento.preventDefault();
      if (!formContacto.checkValidity()) {
        formContacto.reportValidity();
        return;
      }
      // TODO (backend PHP): enviar correo/celular actualizados al servidor.
      mostrarToast('Datos de contacto actualizados. (Conexión con el servidor pendiente)');
    });
  }

  const formPassword = document.querySelector('#formPassword');
  if (formPassword) {
    formPassword.addEventListener('submit', (evento) => {
      evento.preventDefault();

      if (!formPassword.checkValidity()) {
        formPassword.reportValidity();
        return;
      }

      // El navegador no puede comparar dos campos entre sí con "pattern"; lo validamos a mano.
      const nueva = document.querySelector('#pass-nueva');
      const confirmar = document.querySelector('#pass-confirmar');
      if (nueva.value !== confirmar.value) {
        confirmar.setCustomValidity('Las contraseñas no coinciden.');
        confirmar.reportValidity();
        confirmar.addEventListener('input', () => confirmar.setCustomValidity(''), { once: true });
        return;
      }

      // TODO (backend PHP): validar la contraseña actual contra la base y actualizar el hash.
      mostrarToast('Contraseña actualizada. (Conexión con el servidor pendiente)');
      formPassword.reset();
    });
  }

  // ===== TEMA VISUAL POR DEPORTE (ícono + color) =====
  // Un solo lugar con la "personalidad" de cada deporte. Cualquier elemento
  // con data-deporte="..." se tematiza solo, sin tener que craftear una
  // clase CSS a mano por cada torneo nuevo.
  const TEMAS_DEPORTE = {
    'Fútbol 11':         { icono: 'fa-futbol',                   color: '#1a6b2e' },
    'Fútbol 5':          { icono: 'fa-futbol',                   color: '#2f8f3e' },
    'Básquetbol':        { icono: 'fa-basketball',               color: '#b84a00' },
    'Tenis':             { icono: 'fa-table-tennis-paddle-ball', color: '#3a6b1a' },
    'Tenis dobles':      { icono: 'fa-table-tennis-paddle-ball', color: '#4a7d22' },
    'Pádel':             { icono: 'fa-table-tennis-paddle-ball', color: '#1f7a5c' },
    'Ping pong':         { icono: 'fa-table-tennis-paddle-ball', color: '#0d6e6e' },
    'Ajedrez':           { icono: 'fa-chess',                    color: '#2c2c2c' },
    'Truco':             { icono: 'fa-heart',                    color: '#7a2e2e' },
    'Damas':             { icono: 'fa-chess-board',              color: '#4a4a4a' },
    'CS2':               { icono: 'fa-gamepad',                  color: '#1a3a5c' },
    'League of Legends': { icono: 'fa-dragon',                   color: '#5b3a99' },
    'Fortnite':          { icono: 'fa-explosion',                color: '#6b3fa0' },
    'Valorant':          { icono: 'fa-crosshairs',                color: '#8b0000' },
    'Rocket League':     { icono: 'fa-rocket',                   color: '#003057' },
    'Otro':              { icono: 'fa-shapes',                   color: '#6b6b68' },
  };

  function aplicarTemaDeporte() {
    document.querySelectorAll('[data-deporte]').forEach((elemento) => {
      const tema = TEMAS_DEPORTE[elemento.dataset.deporte] || TEMAS_DEPORTE['Otro'];

      // El ícono puede estar en el propio elemento (detalle.html), en un hijo
      // .resultado-img (tarjetas de busqueda.html) o en un .chip-deporte-icono
      // (carrusel de "Deportes y disciplinas" de index.html).
      const caja = elemento.matches('.resultado-img, .detalle-hero-img, .chip-deporte-icono')
        ? elemento
        : elemento.querySelector('.resultado-img, .detalle-hero-img, .chip-deporte-icono');

      if (!caja) return;

      caja.style.background = tema.color;
      const icono = caja.querySelector('i');
      if (icono) icono.className = 'fa-solid ' + tema.icono;
    });
  }

  aplicarTemaDeporte();

});
