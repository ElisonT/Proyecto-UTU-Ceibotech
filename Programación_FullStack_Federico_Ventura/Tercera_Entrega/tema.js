// tema.js: aplica el modo oscuro ANTES de que se pinte la página, para evitar el destello blanco al
// cargar o cambiar de página. Se carga en el <head> como script externo (y no inline) para que la
// política de contenido (CSP) pueda prohibir los scripts incrustados en las páginas.
(function () {
  try {
    if (localStorage.getItem('sgdm-tema') === 'oscuro') {
      document.documentElement.classList.add('modo-oscuro');
    }
  } catch (e) { /* sin acceso al almacenamiento: queda el tema claro */ }
})();
