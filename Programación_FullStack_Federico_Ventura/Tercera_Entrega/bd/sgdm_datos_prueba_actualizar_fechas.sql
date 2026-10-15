-- ============================================================
-- sgdm_datos_prueba_actualizar_fechas.sql
-- Se corre DESPUÉS de sgdm_datos_prueba.sql (el init.sh de Docker ya lo hace).
--
-- Problema que resuelve: los datos de prueba se generaron "como si hoy fuera
-- el 7/oct/2026" (HOY en generar_datos_prueba.py). Con el paso de los días, los
-- torneos con inscripciones abiertas quedan con la inscripción vencida y los
-- torneos en curso parecen abandonados, y no se puede probar ni mostrar en la
-- defensa que alguien se inscribe. Este script corre TODAS las fechas de los
-- datos de prueba la misma cantidad de días, para que la foto vuelva a estar
-- "al día de hoy" y todo siga teniendo la misma relación entre sí.
--
-- Solo toca datos de prueba (lo que cuelga de usuarios test_*).
--
-- Se puede correr cuantas veces quieras: el desfase se calcula contra una fecha
-- ancla (la creación del torneo 1003, que en el seed original es 2026-09-20),
-- así que si ya está al día no mueve nada.
-- ============================================================

USE sgdm;

SET @ancla_original = '2026-09-20';   -- fecha de creación del torneo 1003 en el seed original
SET @hoy_original   = '2026-10-07';   -- HOY de generar_datos_prueba.py
SET @desfase = IFNULL(
    DATEDIFF(CURDATE(), @hoy_original)
    - DATEDIFF((SELECT DATE(fecha_creacion) FROM torneos WHERE id = 1003), @ancla_original),
    0);

-- Ids de prueba (todo cuelga de usuarios test_*)
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_test_usuarios  (id INT PRIMARY KEY);
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_test_torneos   (id INT PRIMARY KEY);
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_test_equipos   (id INT PRIMARY KEY);
TRUNCATE tmp_test_usuarios; TRUNCATE tmp_test_torneos; TRUNCATE tmp_test_equipos;
INSERT INTO tmp_test_usuarios SELECT id FROM usuarios WHERE nombre_usuario LIKE 'test\_%';
INSERT INTO tmp_test_torneos  SELECT id FROM torneos  WHERE creado_por IN (SELECT id FROM tmp_test_usuarios);
INSERT INTO tmp_test_equipos  SELECT id FROM equipos  WHERE capitan_id IN (SELECT id FROM tmp_test_usuarios);

UPDATE usuarios SET fecha_registro = DATE_ADD(fecha_registro, INTERVAL @desfase DAY)
 WHERE id IN (SELECT id FROM tmp_test_usuarios);

UPDATE torneos SET
    fecha_inicio               = DATE_ADD(fecha_inicio, INTERVAL @desfase DAY),
    fecha_apertura_inscripcion = DATE_ADD(fecha_apertura_inscripcion, INTERVAL @desfase DAY),
    fecha_cierre_inscripcion   = DATE_ADD(fecha_cierre_inscripcion, INTERVAL @desfase DAY),
    fecha_fin                  = DATE_ADD(fecha_fin, INTERVAL @desfase DAY),
    fecha_creacion             = DATE_ADD(fecha_creacion, INTERVAL @desfase DAY)
 WHERE id IN (SELECT id FROM tmp_test_torneos);

UPDATE rondas SET fecha = DATE_ADD(fecha, INTERVAL @desfase DAY)
 WHERE torneo_id IN (SELECT id FROM tmp_test_torneos);

UPDATE enfrentamientos e JOIN rondas r ON r.id = e.ronda_id
   SET e.fecha_hora = DATE_ADD(e.fecha_hora, INTERVAL @desfase DAY)
 WHERE r.torneo_id IN (SELECT id FROM tmp_test_torneos);

UPDATE resultados res
  JOIN enfrentamientos e ON e.id = res.enfrentamiento_id
  JOIN rondas r ON r.id = e.ronda_id
   SET res.fecha_carga      = DATE_ADD(res.fecha_carga, INTERVAL @desfase DAY),
       res.fecha_correccion = DATE_ADD(res.fecha_correccion, INTERVAL @desfase DAY)
 WHERE r.torneo_id IN (SELECT id FROM tmp_test_torneos);

UPDATE inscripciones SET fecha_inscripcion = DATE_ADD(fecha_inscripcion, INTERVAL @desfase DAY)
 WHERE torneo_id IN (SELECT id FROM tmp_test_torneos);

UPDATE equipos SET fecha_creacion = DATE_ADD(fecha_creacion, INTERVAL @desfase DAY)
 WHERE id IN (SELECT id FROM tmp_test_equipos);

UPDATE equipo_miembros SET fecha_ingreso = DATE_ADD(fecha_ingreso, INTERVAL @desfase DAY)
 WHERE equipo_id IN (SELECT id FROM tmp_test_equipos);

UPDATE invitaciones_equipo SET
    fecha_creacion  = DATE_ADD(fecha_creacion, INTERVAL @desfase DAY),
    fecha_respuesta = DATE_ADD(fecha_respuesta, INTERVAL @desfase DAY)
 WHERE equipo_id IN (SELECT id FROM tmp_test_equipos);

UPDATE auditoria SET fecha = DATE_ADD(fecha, INTERVAL @desfase DAY)
 WHERE usuario_id IN (SELECT id FROM tmp_test_usuarios);

-- Ninguna fecha-hora puede quedar en el futuro (el seed original llegaba hasta el mediodía de "hoy").
UPDATE resultados res JOIN enfrentamientos e ON e.id = res.enfrentamiento_id JOIN rondas r ON r.id = e.ronda_id
   SET res.fecha_carga = NOW() - INTERVAL 1 HOUR
 WHERE r.torneo_id IN (SELECT id FROM tmp_test_torneos) AND res.fecha_carga > NOW();
UPDATE inscripciones SET fecha_inscripcion = NOW() - INTERVAL 1 HOUR
 WHERE torneo_id IN (SELECT id FROM tmp_test_torneos) AND fecha_inscripcion > NOW();
UPDATE invitaciones_equipo SET fecha_creacion = NOW() - INTERVAL 1 HOUR
 WHERE equipo_id IN (SELECT id FROM tmp_test_equipos) AND fecha_creacion > NOW();
UPDATE invitaciones_equipo SET fecha_respuesta = NOW() - INTERVAL 1 HOUR
 WHERE equipo_id IN (SELECT id FROM tmp_test_equipos) AND fecha_respuesta > NOW();
UPDATE auditoria SET fecha = NOW() - INTERVAL 1 HOUR
 WHERE usuario_id IN (SELECT id FROM tmp_test_usuarios) AND fecha > NOW();

DROP TEMPORARY TABLE IF EXISTS tmp_test_usuarios;
DROP TEMPORARY TABLE IF EXISTS tmp_test_torneos;
DROP TEMPORARY TABLE IF EXISTS tmp_test_equipos;

SELECT @desfase AS dias_corridos;
