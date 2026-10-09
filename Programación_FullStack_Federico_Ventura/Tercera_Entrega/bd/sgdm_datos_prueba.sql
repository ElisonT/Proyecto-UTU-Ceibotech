-- ============================================================
-- sgdm_datos_prueba.sql
-- Carga unos torneos e inscripciones de prueba, para poder ver
-- el perfil con números y tarjetas reales (no solo para probar
-- que la tabla existe). Esto NO es para producción.
--
-- Antes de correrlo, reemplazá 'organizador_test' y
-- 'participante_test' por nombres de usuario que ya existan
-- de verdad en tu base (los que uses para probar).
-- ============================================================

USE sgdm;

-- Torneo finalizado, donde el participante de prueba salió campeón
INSERT INTO torneos (nombre, deporte, formato, estado, fecha_inicio, creado_por, ganador_id)
VALUES (
    'Mundialito 2026', 'Fútbol', 'liga', 'finalizado', '2026-06-20',
    (SELECT id FROM usuarios WHERE nombre_usuario = 'organizador_test'),
    (SELECT id FROM usuarios WHERE nombre_usuario = 'participante_test')
);

-- Torneo en curso, donde participa (pero todavía no se sabe quién gana)
INSERT INTO torneos (nombre, deporte, formato, estado, fecha_inicio, creado_por, ganador_id)
VALUES (
    'Torneo de ajedrez', 'Ajedrez', 'suizo', 'en_curso', '2026-07-28',
    (SELECT id FROM usuarios WHERE nombre_usuario = 'organizador_test'),
    NULL
);

-- Torneo con inscripciones abiertas, todavía no arrancó
INSERT INTO torneos (nombre, deporte, formato, estado, fecha_inicio, creado_por, ganador_id)
VALUES (
    'CS:2 Gaming Cup', 'Videojuegos', 'eliminacion', 'inscripciones_abiertas', '2026-09-20',
    (SELECT id FROM usuarios WHERE nombre_usuario = 'organizador_test'),
    NULL
);

-- Inscribe al participante de prueba en los 3 torneos de arriba
INSERT INTO inscripciones (torneo_id, usuario_id)
SELECT t.id, (SELECT id FROM usuarios WHERE nombre_usuario = 'participante_test')
FROM torneos t
WHERE t.nombre IN ('Mundialito 2026', 'Torneo de ajedrez', 'CS:2 Gaming Cup');
