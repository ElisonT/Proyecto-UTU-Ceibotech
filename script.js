// script.js
// Lógica de interacción del sitio (JavaScript del lado del cliente).
// Se espera a que el DOM esté completamente cargado antes de buscar elementos.
document.addEventListener('DOMContentLoaded', () => {

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

  // ===== NAVEGACIÓN DEL SIDEBAR (resaltado activo + enlaces sin sección propia) =====
  const adminNavLinks = document.querySelectorAll('.admin-nav-link');
  if (adminNavLinks.length) {
    adminNavLinks.forEach((link) => {
      link.addEventListener('click', (evento) => {
        const destino = link.getAttribute('href');

        // Los enlaces sin sección propia todavía muestran un aviso, en vez de no hacer nada
        if (destino === '#modulos' || destino === '#reportes' || destino === '#exportar') {
          evento.preventDefault();
          mostrarToast('Sección en desarrollo');
          return;
        }

        // Si es un ancla dentro de la misma página, marcamos este link como el activo
        if (destino && destino.startsWith('#')) {
          adminNavLinks.forEach((l) => l.classList.remove('admin-nav-active'));
          link.classList.add('admin-nav-active');
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
  }

  // Accesos rápidos que todavía no tienen una sección propia en el panel
  document.querySelectorAll('.admin-acceso').forEach((link) => {
    const destino = link.getAttribute('href');
    if (destino === '#modulos' || destino === '#reportes' || destino === '#exportar') {
      link.addEventListener('click', (evento) => {
        evento.preventDefault();
        mostrarToast('Sección en desarrollo');
      });
    }
  });

  // ===== AVISO FLOTANTE ("TOAST") =====
  // Función compartida para confirmar acciones del panel admin sin usar alert()
  let toastTimeout;
  function mostrarToast(mensaje) {
    let toast = document.querySelector('.admin-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'admin-toast';
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
      window.location.href = 'detalle.html';
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

  // ===== FILTRADO Y PAGINACIÓN DE TORNEOS (busqueda.html) =====
  const filtroDeporte = document.querySelector('#filtroDeporte');
  const filtroTipo = document.querySelector('#filtroTipo');
  const filtroEstado = document.querySelector('#filtroEstado');
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

      return Array.from(tarjetas).filter((tarjeta) => {
        // value vacío ("Todos los...") significa que ese filtro no restringe nada
        const coincideDeporte = deporte === '' || tarjeta.dataset.deporte === deporte;
        const coincideTipo = tipo === '' || tarjeta.dataset.tipo === tipo;
        const coincideEstado = estado === '' || tarjeta.dataset.estado === estado;
        return coincideDeporte && coincideTipo && coincideEstado;
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

  // ---- Formulario de login ----
  const loginForm = document.querySelector('#loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', (evento) => {
      evento.preventDefault();

      const loginOk = validarCampo(
        document.querySelector('#login'), 'error-login',
        (v) => v !== '', 'Ingresá tu usuario o correo.'
      );
      const passwordOk = validarCampo(
        document.querySelector('#password'), 'error-password',
        (v) => v !== '', 'Ingresá tu contraseña.'
      );

      if (loginOk && passwordOk) {
        // Todavía no hay backend conectado (eso lo resuelve PHP más adelante).
        // Mostramos el estado de error como demostración del componente de la interfaz.
        const alerta = document.querySelector('#alert-login');
        if (alerta) alerta.style.display = 'flex';
      }
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
      evento.preventDefault();

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

      if (ok) {
        const alerta = document.querySelector('#alert-success');
        if (alerta) alerta.style.display = 'flex';
        registerForm.reset();
        if (strengthBox) strengthBox.style.display = 'none';
      }
    });
  }

  // ---- Formulario de crear torneo ----
  // Formulario grande, con validación HTML5 (required, pattern) ya puesta en el markup.
  // En vez de repetir esas reglas a mano en JS, usamos checkValidity()/reportValidity(),
  // que el propio navegador resuelve leyendo esos atributos.
  const crearTorneoForm = document.querySelector('#crearTorneoForm');
  if (crearTorneoForm) {
    crearTorneoForm.addEventListener('submit', (evento) => {
      evento.preventDefault(); // por ahora no hay backend conectado

      if (!crearTorneoForm.checkValidity()) {
        crearTorneoForm.reportValidity(); // resalta el primer campo inválido
        return;
      }

      // Placeholder: acá en el futuro va la conexión real con PHP para crear el torneo.
      alert('Formulario válido. (Conexión con el servidor pendiente)');
    });
  }

});
