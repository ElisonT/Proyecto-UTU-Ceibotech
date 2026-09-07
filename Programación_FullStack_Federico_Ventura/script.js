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
  const VISTAS_VALIDAS = ['dashboard', 'usuarios', 'torneos', 'modulos'];
  const TITULOS_VISTA = { dashboard: 'Dashboard', usuarios: 'Usuarios', torneos: 'Torneos', modulos: 'Módulos de competencia' };
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
    toastTimeout = setTimeout(() => {
      toast.classList.remove('visible');
    }, 2500);
  }

  // Resta 1 al número mostrado en una tarjeta de estadística del dashboard
  function restarStat(selectorLabel) {
    const tarjetas = document.querySelectorAll('.admin-stat-card');
    tarjetas.forEach((tarjeta) => {
      const label = tarjeta.querySelector('.admin-stat-label');
      if (label && label.textContent.trim() === selectorLabel) {
        const numSpan = tarjeta.querySelector('.admin-stat-num');
        const valorActual = parseFloat(numSpan.textContent.replace('k', '')) || 0;
        const esMiles = numSpan.textContent.includes('k');
        const nuevoValor = Math.max(0, valorActual - (esMiles ? 0.1 : 1));
        numSpan.textContent = esMiles ? nuevoValor.toFixed(1) + 'k' : Math.round(nuevoValor);
      }
    });
  }

  // Quita una fila de la tabla con una pequeña animación de salida
  function eliminarFila(fila) {
    fila.classList.add('saliendo');
    setTimeout(() => fila.remove(), 200);
  }

  // ===== TABLA DE USUARIOS (editar / suspender / eliminar) =====
  document.querySelectorAll('[data-action="editar-usuario"]').forEach((boton) => {
    boton.addEventListener('click', () => {
      const fila = boton.closest('.admin-tabla-fila');
      const nombreSpan = fila.querySelector('.admin-user-nombre');
      const nuevoNombre = prompt('Editar nombre de usuario:', nombreSpan.textContent);
      if (nuevoNombre && nuevoNombre.trim() !== '') {
        nombreSpan.textContent = nuevoNombre.trim();
        mostrarToast('Usuario actualizado');
      }
    });
  });

  document.querySelectorAll('[data-action="suspender-usuario"]').forEach((boton) => {
    boton.addEventListener('click', () => {
      const fila = boton.closest('.admin-tabla-fila');
      const nombre = fila.querySelector('.admin-user-nombre').textContent;
      if (!confirm('¿Suspender a ' + nombre + '?')) return;

      const badge = fila.querySelector('.badge');
      badge.textContent = 'Suspendido';
      badge.classList.remove('estado-verde');
      badge.classList.add('estado-rojo');

      // Una vez suspendido, la única acción posible pasa a ser eliminar (igual que en el mockup original)
      boton.dataset.action = 'eliminar-usuario';
      boton.title = 'Eliminar';
      boton.setAttribute('aria-label', 'Eliminar');
      boton.innerHTML = '<i class="fa-solid fa-trash"></i>';
      boton.addEventListener('click', manejarEliminarUsuario);

      mostrarToast('Usuario suspendido');
    });
  });

  function manejarEliminarUsuario(evento) {
    const boton = evento.currentTarget;
    const fila = boton.closest('.admin-tabla-fila');
    const nombre = fila.querySelector('.admin-user-nombre').textContent;
    if (!confirm('¿Eliminar a ' + nombre + ' definitivamente? Esta acción no se puede deshacer.')) return;

    eliminarFila(fila);
    restarStat('Usuarios registrados');
    mostrarToast('Usuario eliminado');
  }
  document.querySelectorAll('[data-action="eliminar-usuario"]').forEach((boton) => {
    boton.addEventListener('click', manejarEliminarUsuario);
  });

  // ===== TABLA DE TORNEOS (ver / eliminar) =====
  document.querySelectorAll('[data-action="ver-torneo"]').forEach((boton) => {
    boton.addEventListener('click', () => {
      window.location.href = 'detalle.php';
    });
  });

  document.querySelectorAll('[data-action="eliminar-torneo"]').forEach((boton) => {
    boton.addEventListener('click', () => {
      const fila = boton.closest('.admin-tabla-fila');
      const nombre = fila.querySelector('.admin-user-nombre').textContent;
      if (!confirm('¿Eliminar el torneo "' + nombre + '"? Esta acción no se puede deshacer.')) return;

      eliminarFila(fila);
      restarStat('Torneos activos');
      mostrarToast('Torneo eliminado');
    });
  });

  // ===== MÓDULOS DE COMPETENCIA (habilitar / deshabilitar) =====
  document.querySelectorAll('.admin-toggle input[data-modulo]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
      const nombreModulo = toggle.dataset.modulo;
      mostrarToast('Módulo "' + nombreModulo + '" ' + (toggle.checked ? 'activado' : 'desactivado'));
    });
  });

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

  // "Jugadores por equipo" solo aplica si la modalidad es "equipos"
  const radiosModalidad = document.querySelectorAll('input[name="modalidad"]');
  const grupoJugadoresPorEquipo = document.querySelector('#grupoJugadoresPorEquipo');

  if (radiosModalidad.length && grupoJugadoresPorEquipo) {
    const inputJugadoresPorEquipo = document.querySelector('#jugadores_por_equipo');

    function actualizarCamposModalidad() {
      const seleccionado = document.querySelector('input[name="modalidad"]:checked');
      const esEquipos = seleccionado && seleccionado.value === 'equipos';
      grupoJugadoresPorEquipo.style.display = esEquipos ? '' : 'none';
      // Si no es por equipos, sacamos el required para que no bloquee el envío de un campo oculto
      if (inputJugadoresPorEquipo) inputJugadoresPorEquipo.required = esEquipos;
    }

    radiosModalidad.forEach((radio) => radio.addEventListener('change', actualizarCamposModalidad));
    actualizarCamposModalidad(); // estado inicial
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

  // ===== DATOS DE EJEMPLO DE TORNEOS =====
  // En cuanto conectemos PHP, esto se reemplaza por una consulta real a la
  // base usando el id de la URL. Por ahora arma detalle.html "a mano" según
  // qué tarjeta de busqueda.html se haya clickeado (?id=1, ?id=2, etc).
  const TORNEOS_EJEMPLO = {
    1: { deporte: 'Fútbol 11', tipo: 'Liga', nombre: 'Mundialito 2026',
      desc: 'Torneo de fútbol 11 para equipos amateur. Todos contra todos, puntos por resultado. El mejor equipo al final de la temporada se lleva el título.',
      cupos: '8 equipos',
      cuposTotales: 8, fecha: '15 jun — 30 ago 2026',
      badgeClass: 'estado-naranja', badgeIcono: 'fa-circle-play', badgeTexto: 'En curso — Fecha 4',
      inscripcionEstado: 'en_curso', modalidad: 'equipos', cuposDisponibles: 0 },
    2: { deporte: 'Ajedrez', tipo: 'Sistema suizo', nombre: 'Torneo de ajedrez UTU',
      desc: 'Competencia de ajedrez por sistema suizo. Rondas semanales, clasificación acumulada. Abierto a todos los niveles.',
      cupos: '128 participantes',
      cuposTotales: 128, fecha: 'Comienza 28 jul 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'individual', cuposDisponibles: 12 },
    3: { deporte: 'CS2', tipo: 'Eliminación directa', nombre: 'CS:2 Gaming Cup',
      desc: 'Copa de Counter-Strike 2 en formato eliminación directa. 16 equipos, llaves automáticas. Torneo ya finalizado.',
      cupos: '16 equipos',
      cuposTotales: 16, fecha: 'Finalizó 20 jun 2026',
      badgeClass: 'estado-rojo', badgeIcono: 'fa-flag-checkered', badgeTexto: 'Finalizado',
      inscripcionEstado: 'finalizado', modalidad: 'equipos', cuposDisponibles: 0 },
    4: { deporte: 'Básquetbol', tipo: 'Liga', nombre: 'Liga Barrial de Básquet 2026',
      desc: 'Liga de básquetbol barrial, abierta a equipos de todo Montevideo. Todos contra todos a lo largo de la temporada.',
      cupos: '8 equipos',
      cuposTotales: 8, fecha: '1 jul — 15 sep 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'equipos', cuposDisponibles: 3 },
    5: { deporte: 'Valorant', tipo: 'Eliminación directa', nombre: 'Valorant Open UY',
      desc: 'Torneo abierto de Valorant para equipos uruguayos. Cupos limitados, llaves de eliminación directa.',
      cupos: '32 equipos',
      cuposTotales: 32, fecha: 'Comienza 10 jul 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'equipos', cuposDisponibles: 8 },
    6: { deporte: 'Tenis', tipo: 'Eliminación directa', nombre: 'Copa Tenis Arias 2025',
      desc: 'Torneo de tenis individual, categorías A y B. Torneo ya finalizado.',
      cupos: '24 participantes',
      cuposTotales: 24, fecha: 'Oct — Nov 2025',
      badgeClass: 'estado-rojo', badgeIcono: 'fa-flag-checkered', badgeTexto: 'Finalizado',
      inscripcionEstado: 'finalizado', modalidad: 'individual', cuposDisponibles: 0 },
    7: { deporte: 'Fútbol 5', tipo: 'Sistema suizo', nombre: 'Copa Fútbol 5 Ciudad Vieja',
      desc: 'Torneo de fútbol 5 entre equipos del barrio Ciudad Vieja. Formato suizo a 5 rondas, clasificación acumulada.',
      cupos: '12 equipos',
      cuposTotales: 12, fecha: 'Comienza 5 ago 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'equipos', cuposDisponibles: 4 },
    8: { deporte: 'Pádel', tipo: 'Eliminación directa', nombre: 'Open de Pádel Punta Carretas',
      desc: 'Torneo de pádel en parejas, eliminación directa desde octavos de final.',
      cupos: '16 parejas',
      cuposTotales: 16, fecha: 'En curso — Fecha 2',
      badgeClass: 'estado-naranja', badgeIcono: 'fa-circle-play', badgeTexto: 'En curso — Fecha 2',
      inscripcionEstado: 'en_curso', modalidad: 'equipos', cuposDisponibles: 0 },
    9: { deporte: 'Ping pong', tipo: 'Liga', nombre: 'Liga de Ping Pong ITS',
      desc: 'Liga interna de ping pong entre estudiantes del instituto. Todos contra todos.',
      cupos: '10 participantes',
      cuposTotales: 10, fecha: 'Comienza 12 ago 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'individual', cuposDisponibles: 6 },
    10: { deporte: 'Truco', tipo: 'Sistema suizo', nombre: 'Torneo de Truco Amistoso',
      desc: 'Competencia de truco en parejas, sistema suizo, clasificación por puntos acumulados.',
      cupos: '20 parejas',
      cuposTotales: 20, fecha: 'Comienza 20 ago 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'equipos', cuposDisponibles: 10 },
    11: { deporte: 'Damas', tipo: 'Eliminación directa', nombre: 'Copa Damas Clásicas',
      desc: 'Torneo de damas 1 contra 1, eliminación directa a partido único. Torneo ya finalizado.',
      cupos: '32 participantes',
      cuposTotales: 32, fecha: 'Finalizó 5 may 2026',
      badgeClass: 'estado-rojo', badgeIcono: 'fa-flag-checkered', badgeTexto: 'Finalizado',
      inscripcionEstado: 'finalizado', modalidad: 'individual', cuposDisponibles: 0 },
    12: { deporte: 'League of Legends', tipo: 'Liga', nombre: 'LoL PrimeCup Season 1',
      desc: 'Liga de League of Legends 5 contra 5 entre equipos amateurs. Formato todos contra todos.',
      cupos: '10 equipos',
      cuposTotales: 10, fecha: 'En curso — Fecha 6',
      badgeClass: 'estado-naranja', badgeIcono: 'fa-circle-play', badgeTexto: 'En curso — Fecha 6',
      inscripcionEstado: 'en_curso', modalidad: 'equipos', cuposDisponibles: 0 },
    13: { deporte: 'Fortnite', tipo: 'Eliminación directa', nombre: 'Fortnite Solo Showdown',
      desc: 'Torneo de Fortnite en modalidad solo, eliminación directa por puntaje acumulado.',
      cupos: '50 participantes',
      cuposTotales: 50, fecha: 'Comienza 18 ago 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'individual', cuposDisponibles: 12 },
    14: { deporte: 'Rocket League', tipo: 'Sistema suizo', nombre: 'Rocket League 3v3 Cup',
      desc: 'Torneo de Rocket League en equipos de 3, sistema suizo por rendimiento.',
      cupos: '12 equipos',
      cuposTotales: 12, fecha: 'Comienza 25 ago 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'equipos', cuposDisponibles: 2 },
    15: { deporte: 'Otro', tipo: 'Liga', nombre: 'Torneo Libre Multideporte',
      desc: 'Espacio para competencias que no encajan en las categorías tradicionales. Formato todos contra todos.',
      cupos: '6 equipos',
      cuposTotales: 6, fecha: 'Comienza 1 sep 2026',
      badgeClass: 'estado-verde', badgeIcono: 'fa-circle-check', badgeTexto: 'Inscripciones abiertas',
      inscripcionEstado: 'abierta', modalidad: 'equipos', cuposDisponibles: 6 },
  };

  // ===== TABLA DE POSICIONES POR TORNEO =====
  // Solo tienen datos los torneos que ya arrancaron (en_curso o finalizado);
  // los que todavía están en inscripciones lógicamente no tienen partidos jugados.
  const TABLAS_POSICIONES = {
    1: { columna: 'Equipo', filas: [
      { nombre: 'Los Cañones FC', pj: 3, pts: 9 },
      { nombre: 'Atlético Barrio Sur', pj: 3, pts: 7 },
      { nombre: 'Villa Española B', pj: 3, pts: 6 },
      { nombre: 'Rampla Juniors C', pj: 3, pts: 4 },
      { nombre: 'Cerro de las Rosas', pj: 3, pts: 4 },
      { nombre: 'Deportivo Sayago', pj: 3, pts: 3 },
      { nombre: 'Unidos del Norte', pj: 3, pts: 1 },
      { nombre: 'Juventud Unida FC', pj: 3, pts: 0 },
    ]},
    3: { columna: 'Equipo', filas: [
      { nombre: 'Ghost Squad', pj: 4, pts: 12 },
      { nombre: 'Nova Esports', pj: 4, pts: 9 },
      { nombre: 'Team Alpha', pj: 3, pts: 6 },
      { nombre: 'Delta Force UY', pj: 3, pts: 6 },
    ]},
    6: { columna: 'Participante', filas: [
      { nombre: 'Martina Ríos', pj: 5, pts: 5 },
      { nombre: 'Bruno Acosta', pj: 5, pts: 4 },
      { nombre: 'Sofía Batista', pj: 4, pts: 3 },
      { nombre: 'Lucas Ferreira', pj: 4, pts: 2 },
    ]},
    8: { columna: 'Pareja', filas: [
      { nombre: 'Fernández / Silva', pj: 2, pts: 6 },
      { nombre: 'Gómez / Techera', pj: 2, pts: 4 },
      { nombre: 'Correa / Núñez', pj: 2, pts: 3 },
      { nombre: 'Ramos / Vidal', pj: 2, pts: 1 },
    ]},
    11: { columna: 'Participante', filas: [
      { nombre: 'Diego Pereyra', pj: 5, pts: 5 },
      { nombre: 'Valentina Cruz', pj: 5, pts: 4 },
      { nombre: 'Emilia Sosa', pj: 4, pts: 3 },
      { nombre: 'Nicolás Bentancor', pj: 4, pts: 2 },
    ]},
    12: { columna: 'Equipo', filas: [
      { nombre: 'Dragones del Sur', pj: 6, pts: 15 },
      { nombre: 'Fénix Gaming', pj: 6, pts: 12 },
      { nombre: 'Lobos Nocturnos', pj: 6, pts: 9 },
      { nombre: 'Titanes UY', pj: 6, pts: 6 },
    ]},
  };

  function renderTablaPosiciones(idTorneo) {
    const seccion = document.querySelector('#seccionTablaPosiciones');
    const tbody = document.querySelector('#tablaPosicionesBody');
    if (!seccion || !tbody) return;

    const wrap = seccion.querySelector('.tabla-wrap');
    const leyenda = seccion.querySelector('.tabla-leyenda');
    const datos = TABLAS_POSICIONES[idTorneo];

    // Sin datos todavía (el torneo no arrancó): ocultamos la tabla y mostramos un aviso
    let aviso = seccion.querySelector('.tabla-sin-datos');
    if (!datos) {
      wrap.style.display = 'none';
      leyenda.style.display = 'none';
      if (!aviso) {
        aviso = document.createElement('p');
        aviso.className = 'tabla-sin-datos';
        seccion.appendChild(aviso);
      }
      aviso.textContent = 'Este torneo todavía no comenzó — la tabla se arma cuando se juegue la primera fecha.';
      return;
    }

    wrap.style.display = '';
    leyenda.style.display = '';
    if (aviso) aviso.remove();

    const columnaHeader = document.querySelector('#tablaColumnaNombre');
    if (columnaHeader) columnaHeader.textContent = datos.columna;

    tbody.innerHTML = '';
    datos.filas.forEach((fila, indice) => {
      const posicion = indice + 1;
      const tr = document.createElement('tr');
      if (posicion === 1) tr.className = 'tabla-lider';
      const claseNum = posicion <= 3 ? 'pos-num pos-' + posicion : 'pos-num';

      tr.innerHTML =
        '<td class="tabla-pos"><span class="' + claseNum + '">' + posicion + '</span></td>' +
        '<td class="tabla-equipo">' + fila.nombre + '</td>' +
        '<td>' + fila.pj + '</td>' +
        '<td class="tabla-pts"><strong>' + fila.pts + '</strong></td>';
      tbody.appendChild(tr);
    });
  }

  function renderParticipantes(idTorneo, torneo) {
    const grid = document.querySelector('#participantesGrid');
    const titulo = document.querySelector('#participantesTitulo');
    const cuposLabel = document.querySelector('#participantesCuposLabel');
    if (!grid) return;

    const esEquipos = torneo.modalidad === 'equipos';
    const etiqueta = esEquipos ? 'Equipos participantes' : 'Participantes';
    if (titulo) titulo.innerHTML = '<i class="fa-solid fa-users"></i> ' + etiqueta;

    const tema = TEMAS_DEPORTE[torneo.deporte] || TEMAS_DEPORTE['Otro'];
    const datosTabla = TABLAS_POSICIONES[idTorneo];

    let nombres;
    if (datosTabla) {
      // Ya hay tabla de posiciones armada: son los mismos equipos/jugadores
      nombres = datosTabla.filas.map((fila) => fila.nombre);
    } else {
      // Todavía no hay lista real: generamos nombres genéricos según cuántos cupos ya se ocuparon
      const yaInscriptos = torneo.cuposTotales - torneo.cuposDisponibles;
      const base = esEquipos ? 'Equipo' : 'Participante';
      nombres = [];
      for (let i = 1; i <= yaInscriptos; i++) nombres.push(base + ' ' + i);
    }

    if (cuposLabel) {
      cuposLabel.textContent = nombres.length + ' / ' + torneo.cuposTotales + ' inscriptos';
    }

    grid.innerHTML = '';
    if (nombres.length === 0) {
      grid.innerHTML = '<p class="tabla-sin-datos">Todavía no hay inscriptos. ¡Sé el primero!</p>';
      return;
    }

    nombres.forEach((nombre) => {
      const item = document.createElement('div');
      item.className = 'participante-item';
      item.innerHTML = '<i class="fa-solid ' + tema.icono + '"></i> ' + nombre;
      grid.appendChild(item);
    });
  }

  // Arma los enfrentamientos de "todos contra todos" (metodo del circulo),
  // a partir de una lista de nombres. Es el mismo algoritmo que va a necesitar
  // el modulo de Liga cuando se implemente la generacion automatica en PHP.
  function generarRoundRobin(nombres) {
    const lista = nombres.slice();
    if (lista.length % 2 !== 0) lista.push(null); // equipo libre ("bye") si son impares
    const n = lista.length;
    const rondas = [];

    for (let r = 0; r < n - 1; r++) {
      const partidosRonda = [];
      for (let i = 0; i < n / 2; i++) {
        const local = lista[i];
        const visitante = lista[n - 1 - i];
        if (local !== null && visitante !== null) {
          partidosRonda.push({ local, visitante });
        }
      }
      rondas.push(partidosRonda);
      lista.splice(1, 0, lista.pop()); // rota todos menos el primero
    }
    return rondas;
  }

  // ---- Arma el hero de detalle.html según el ?id= de la URL ----
  const detalleTitulo = document.querySelector('#detalleTitulo');
  let idTorneoActual = 1; // por defecto, usado también por la tarjeta de inscripción más abajo
  if (detalleTitulo) {
    const parametros = new URLSearchParams(window.location.search);
    idTorneoActual = Number(parametros.get('id')) || 1;
    const torneo = TORNEOS_EJEMPLO[idTorneoActual] || TORNEOS_EJEMPLO[1];

    document.querySelector('#detalleCategoria').textContent = torneo.deporte + ' · ' + torneo.tipo;
    detalleTitulo.textContent = torneo.nombre;
    document.querySelector('#detalleDesc').textContent = torneo.desc;
    document.querySelector('#detalleMetaFecha').textContent = torneo.fecha;
    document.querySelector('#detalleMetaCupos').textContent = torneo.cupos;
    document.title = torneo.nombre + ' — PrimeCup';

    const heroImg = document.querySelector('.detalle-hero-img');
    if (heroImg) heroImg.dataset.deporte = torneo.deporte;

    const heroAccion = document.querySelector('#detalleHeroAccion');
    if (heroAccion) {
      heroAccion.innerHTML =
        '<span class="badge ' + torneo.badgeClass + '" style="margin-top:0; font-size: 13px; padding: 5px 14px;">' +
        '<i class="fa-solid ' + torneo.badgeIcono + '"></i> ' + torneo.badgeTexto +
        '</span>';
    }

    // Le pasamos el estado real a la tarjeta de inscripción, antes de que se arme
    const cajaInscripcionPrevia = document.querySelector('#detalleInscripcion');
    if (cajaInscripcionPrevia) {
      cajaInscripcionPrevia.dataset.estado = torneo.inscripcionEstado;
      cajaInscripcionPrevia.dataset.modalidad = torneo.modalidad;
      cajaInscripcionPrevia.dataset.cuposDisponibles = torneo.cuposDisponibles;
    }

    aplicarTemaDeporte(); // reaplicamos ahora que el ícono/color ya corresponde al deporte real
    renderTablaPosiciones(idTorneoActual);
    renderParticipantes(idTorneoActual, torneo);
  }

  // ---- Calendario completo (fechas.html): arma el fixture con el generador round-robin ----
  const fechasContenedor = document.querySelector('#fechasContenedor');
  if (fechasContenedor) {
    const parametros = new URLSearchParams(window.location.search);
    const idTorneo = Number(parametros.get('id')) || 1;
    const torneo = TORNEOS_EJEMPLO[idTorneo] || TORNEOS_EJEMPLO[1];
    const datosTabla = TABLAS_POSICIONES[idTorneo];

    const linkVolver = document.querySelector('#fechasVolverLink');
    if (linkVolver) linkVolver.href = 'detalle.php?id=' + idTorneo;

    const subtitulo = document.querySelector('#fechasSubtitulo');

    if (!datosTabla) {
      // El torneo todavía está en inscripciones: no hay fixture generado todavía
      if (subtitulo) subtitulo.textContent = torneo.nombre + ' · ' + torneo.deporte + ' · ' + torneo.tipo;
      fechasContenedor.innerHTML =
        '<p class="tabla-sin-datos">Este torneo todavía está en inscripciones — el fixture se genera ' +
        'automáticamente apenas cierren.</p>';
      ['fechasStatJugadas', 'fechasStatEnCurso', 'fechasStatPendientes', 'fechasStatPartidos'].forEach((id) => {
        const el = document.querySelector('#' + id);
        if (el) el.textContent = '0';
      });
    } else {
      const nombres = datosTabla.filas.map((fila) => fila.nombre);
      const rondas = generarRoundRobin(nombres);

      // Cuántas fechas ya se jugaron: usamos el "pj" del primero de la tabla como referencia
      const fechasJugadas = Math.min(datosTabla.filas[0].pj, rondas.length);
      const hayFechaEnCurso = torneo.inscripcionEstado === 'en_curso' && fechasJugadas < rondas.length;

      if (subtitulo) {
        subtitulo.textContent =
          torneo.nombre + ' · ' + torneo.deporte + ' · ' + torneo.tipo + ' · ' +
          nombres.length + (torneo.modalidad === 'equipos' ? ' equipos' : ' participantes') +
          ' · ' + rondas.length + ' fechas';
      }

      let totalPartidosJugados = 0;
      fechasContenedor.innerHTML = '';

      rondas.forEach((partidos, indice) => {
        const numeroFecha = indice + 1;
        const esJugada = numeroFecha <= fechasJugadas;
        const esEnCurso = hayFechaEnCurso && numeroFecha === fechasJugadas + 1;

        const seccion = document.createElement('section');
        seccion.className = 'perfil-seccion';

        const badge = esJugada
          ? '<span class="badge estado-verde">Jugada</span>'
          : esEnCurso
            ? '<span class="badge estado-naranja">En curso</span>'
            : '<span class="badge estado-gris">Pendiente</span>';

        let partidosHtml = '';
        partidos.forEach((partido) => {
          if (esJugada) {
            const marcadorLocal = Math.floor(Math.random() * 5);
            const marcadorVisitante = Math.floor(Math.random() * 5);
            totalPartidosJugados++;
            partidosHtml +=
              '<div class="partido">' +
              '<div class="partido-equipo partido-local">' + partido.local + '</div>' +
              '<div class="partido-resultado">' +
              '<span class="resultado-num">' + marcadorLocal + '</span>' +
              '<span class="resultado-sep">-</span>' +
              '<span class="resultado-num">' + marcadorVisitante + '</span>' +
              '</div>' +
              '<div class="partido-equipo partido-visitante">' + partido.visitante + '</div>' +
              '</div>';
          } else {
            partidosHtml +=
              '<div class="partido partido-pendiente">' +
              '<div class="partido-equipo partido-local">' + partido.local + '</div>' +
              '<div class="partido-resultado partido-resultado-pendiente"><span class="resultado-sep">vs</span></div>' +
              '<div class="partido-equipo partido-visitante">' + partido.visitante + '</div>' +
              '</div>';
          }
        });

        seccion.innerHTML =
          '<div class="seccion-header"><h2 class="seccion-titulo">Fecha ' + numeroFecha + '</h2>' + badge + '</div>' +
          '<div class="partidos-lista">' + partidosHtml + '</div>';

        fechasContenedor.appendChild(seccion);
      });

      const statJugadas = document.querySelector('#fechasStatJugadas');
      const statEnCurso = document.querySelector('#fechasStatEnCurso');
      const statPendientes = document.querySelector('#fechasStatPendientes');
      const statPartidos = document.querySelector('#fechasStatPartidos');
      if (statJugadas) statJugadas.textContent = fechasJugadas;
      if (statEnCurso) statEnCurso.textContent = hayFechaEnCurso ? '1' : '0';
      if (statPendientes) statPendientes.textContent = rondas.length - fechasJugadas - (hayFechaEnCurso ? 1 : 0);
      if (statPartidos) statPartidos.textContent = totalPartidosJugados;
    }
  }

  // ---- Tarjeta de inscripción a un torneo (detalle.html) ----
  const inscripcionBox = document.querySelector('#detalleInscripcion');
  if (inscripcionBox) {
    let yaInscripto = false; // se pierde al recargar: es solo para simular la interacción sin backend

    function renderInscripcion() {
      const estado = inscripcionBox.dataset.estado;               // 'abierta' | 'en_curso' | 'finalizado'
      const modalidad = inscripcionBox.dataset.modalidad;         // 'individual' | 'equipos'
      const cupos = Number(inscripcionBox.dataset.cuposDisponibles);

      inscripcionBox.classList.remove('cerrado', 'inscripto');

      if (yaInscripto) {
        inscripcionBox.classList.add('inscripto');
        inscripcionBox.innerHTML =
          '<div class="detalle-inscripcion-texto">' +
          '<i class="fa-solid fa-circle-check"></i> Ya estás inscripto en este torneo.' +
          '</div>';
        return;
      }

      if (estado === 'en_curso') {
        inscripcionBox.classList.add('cerrado');
        inscripcionBox.innerHTML =
          '<div class="detalle-inscripcion-texto">' +
          '<i class="fa-solid fa-lock"></i> Las inscripciones para este torneo ya cerraron.' +
          '</div>';
        return;
      }

      if (estado === 'finalizado') {
        inscripcionBox.classList.add('cerrado');
        inscripcionBox.innerHTML =
          '<div class="detalle-inscripcion-texto">' +
          '<i class="fa-solid fa-flag-checkered"></i> Este torneo ya finalizó.' +
          '</div>';
        return;
      }

      // A partir de acá, estado === 'abierta'
      if (cupos <= 0) {
        inscripcionBox.classList.add('cerrado');
        inscripcionBox.innerHTML =
          '<div class="detalle-inscripcion-texto">' +
          '<i class="fa-solid fa-triangle-exclamation"></i> No quedan cupos disponibles.' +
          '</div>';
        return;
      }

      if (modalidad === 'individual') {
        inscripcionBox.innerHTML =
          '<div class="detalle-inscripcion-texto">' +
          '<i class="fa-solid fa-circle-info"></i> Inscripciones abiertas — ' + cupos + ' cupos disponibles.' +
          '</div>' +
          '<div class="detalle-inscripcion-acciones">' +
          '<button type="button" class="btn-primary" id="btnInscribirme">' +
          '<i class="fa-solid fa-user-plus"></i> Inscribirme</button>' +
          '</div>';
        document.querySelector('#btnInscribirme').addEventListener('click', inscribirseIndividual);
      } else {
        inscripcionBox.innerHTML =
          '<div class="detalle-inscripcion-texto">' +
          '<i class="fa-solid fa-circle-info"></i> Inscripciones abiertas — ' + cupos + ' cupos disponibles.' +
          '</div>' +
          '<div class="detalle-inscripcion-acciones">' +
          '<button type="button" class="btn" id="btnUnirmeEquipo">' +
          '<i class="fa-solid fa-user-group"></i> Unirme a un equipo</button>' +
          '<button type="button" class="btn-primary" id="btnCrearEquipo">' +
          '<i class="fa-solid fa-plus"></i> Crear equipo</button>' +
          '</div>';
        document.querySelector('#btnUnirmeEquipo').addEventListener('click', unirseAEquipo);
        document.querySelector('#btnCrearEquipo').addEventListener('click', crearEquipo);
      }
    }

    function inscribirseIndividual() {
      if (!confirm('¿Confirmás tu inscripción a este torneo?')) return;
      yaInscripto = true;
      mostrarToast('¡Te inscribiste al torneo!');
      renderInscripcion();
    }

    function crearEquipo() {
      inscripcionBox.classList.add('expandido');
      inscripcionBox.innerHTML =
        '<div class="form-equipo">' +
        '<div class="form-group">' +
        '<label class="form-label-p" for="nombreEquipoNuevo">Nombre del equipo</label>' +
        '<input type="text" id="nombreEquipoNuevo" class="form-input-p" placeholder="Ej: Los Tigres" />' +
        '</div>' +
        '<div class="form-group">' +
        '<label class="form-label-p">Integrantes</label>' +
        '<div class="lista-integrantes" id="listaIntegrantes">' +
        '<div class="integrante-fila">' +
        '<input type="text" class="form-input-p integrante-input" placeholder="Nombre del integrante" />' +
        '</div>' +
        '</div>' +
        '<button type="button" class="btn-agregar-integrante" id="btnAgregarIntegrante">' +
        '<i class="fa-solid fa-plus"></i> Agregar integrante</button>' +
        '</div>' +
        '<div class="form-equipo-acciones">' +
        '<button type="button" class="btn" id="btnCancelarEquipo">Cancelar</button>' +
        '<button type="button" class="btn-primary" id="btnConfirmarEquipo">' +
        '<i class="fa-solid fa-check"></i> Crear e inscribir equipo</button>' +
        '</div>' +
        '</div>';

      document.querySelector('#btnAgregarIntegrante').addEventListener('click', () => {
        const lista = document.querySelector('#listaIntegrantes');
        const fila = document.createElement('div');
        fila.className = 'integrante-fila';
        fila.innerHTML =
          '<input type="text" class="form-input-p integrante-input" placeholder="Nombre del integrante" />' +
          '<button type="button" class="btn-quitar-integrante" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button>';
        lista.appendChild(fila);
        fila.querySelector('.btn-quitar-integrante').addEventListener('click', () => fila.remove());
      });

      document.querySelector('#btnCancelarEquipo').addEventListener('click', () => {
        inscripcionBox.classList.remove('expandido');
        renderInscripcion();
      });

      document.querySelector('#btnConfirmarEquipo').addEventListener('click', () => {
        const nombreEquipo = document.querySelector('#nombreEquipoNuevo').value.trim();
        const integrantes = Array.from(document.querySelectorAll('.integrante-input'))
          .map((input) => input.value.trim())
          .filter((valor) => valor !== '');

        if (nombreEquipo === '') {
          mostrarToast('Ponele un nombre al equipo.');
          return;
        }
        if (integrantes.length === 0) {
          mostrarToast('Agregá al menos un integrante.');
          return;
        }

        yaInscripto = true;
        inscripcionBox.classList.remove('expandido');
        mostrarToast('Equipo "' + nombreEquipo + '" creado con ' + integrantes.length +
          (integrantes.length === 1 ? ' integrante' : ' integrantes') + ' e inscripto al torneo.');
        renderInscripcion();
      });
    }

    function unirseAEquipo() {
      // Si el torneo ya tiene equipos cargados (tabla de posiciones), ofrecemos elegir entre esos.
      // Si todavía no hay ninguno, dejamos escribir el nombre a mano.
      const datosTabla = TABLAS_POSICIONES[idTorneoActual];
      const equiposExistentes = datosTabla ? datosTabla.filas.map((fila) => fila.nombre) : [];

      inscripcionBox.classList.add('expandido');

      let opcionesHtml = '<option value="">Seleccioná un equipo...</option>';
      equiposExistentes.forEach((nombre) => {
        opcionesHtml += '<option value="' + nombre + '">' + nombre + '</option>';
      });

      inscripcionBox.innerHTML =
        '<div class="form-equipo">' +
        '<div class="form-group">' +
        '<label class="form-label-p" for="tuNombreUnirme">Tu nombre</label>' +
        '<input type="text" id="tuNombreUnirme" class="form-input-p" placeholder="Nombre y apellido" />' +
        '</div>' +
        '<div class="form-group">' +
        '<label class="form-label-p" for="selectEquipoExistente">Equipo</label>' +
        (equiposExistentes.length
          ? '<select id="selectEquipoExistente" class="form-input-p">' + opcionesHtml + '</select>'
          : '<input type="text" id="selectEquipoExistente" class="form-input-p" placeholder="Nombre o código del equipo" />') +
        '</div>' +
        '</div>' +
        '<div class="form-equipo-acciones">' +
        '<button type="button" class="btn" id="btnCancelarUnirme">Cancelar</button>' +
        '<button type="button" class="btn-primary" id="btnConfirmarUnirme">' +
        '<i class="fa-solid fa-check"></i> Solicitar unirme</button>' +
        '</div>';

      document.querySelector('#btnCancelarUnirme').addEventListener('click', () => {
        inscripcionBox.classList.remove('expandido');
        renderInscripcion();
      });

      document.querySelector('#btnConfirmarUnirme').addEventListener('click', () => {
        const tuNombre = document.querySelector('#tuNombreUnirme').value.trim();
        const equipo = document.querySelector('#selectEquipoExistente').value.trim();

        if (tuNombre === '') {
          mostrarToast('Ingresá tu nombre.');
          return;
        }
        if (equipo === '') {
          mostrarToast('Elegí o escribí un equipo.');
          return;
        }

        inscripcionBox.classList.remove('expandido');
        mostrarToast('Solicitud enviada para unirte a "' + equipo + '".');
        renderInscripcion();
      });
    }

    renderInscripcion(); // estado inicial, según los data-* del HTML
  }

});
