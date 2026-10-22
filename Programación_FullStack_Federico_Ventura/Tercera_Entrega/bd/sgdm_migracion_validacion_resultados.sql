-- ============================================================
-- sgdm_migracion_validacion_resultados.sql
-- Se corre DESPUÉS de sgdm_migracion_intentos_login.sql.
--
-- Validación de resultados (opcional, la activa el Administrador en Configuración):
--   validar_resultados = 0  -> todo resultado vale apenas se carga (comportamiento de siempre)
--   validar_resultados = 1  -> lo que carga un Organizador queda "pendiente de validación" (resultados.validado = 0):
--                              no suma a la tabla de posiciones ni deja avanzar de ronda ni cerrar el torneo
--                              hasta que un Administrador lo valide. Lo que carga el Administrador vale al instante.
-- Por defecto está apagada. Se puede correr más de una vez sin efecto.
-- ============================================================

USE sgdm;

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
    ('validar_resultados', '0', 'Si es 1, los resultados que carga un organizador quedan pendientes hasta que un administrador los valida; si es 0, valen apenas se cargan');
