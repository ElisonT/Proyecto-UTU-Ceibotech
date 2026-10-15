-- ============================================================
-- sgdm_migracion_correccion_vistas.sql
-- Se corre DESPUÉS de sgdm_migracion_fechas.sql (y antes de los
-- datos de prueba, aunque también se puede correr después).
--
-- Hace dos cosas:
--
--  1) Corrección de resultados: la letra pide que el Administrador
--     y el Organizador puedan corregir un resultado ya cargado.
--     La corrección no pisa la historia: queda en la fila quién
--     la hizo y cuándo (corregido_por, fecha_correccion), y el
--     detalle "antes -> después" queda en la tabla auditoria.
--
--  2) Vistas SQL para el panel de administración. Concentran en
--     la base las consultas de resumen (que antes eran números
--     de ejemplo escritos a mano en la maqueta), así PHP solo
--     hace SELECT sobre una vista y no repite JOINs por todos
--     lados. Son de solo lectura: la aplicación (sgdm_app) ya
--     tiene SELECT sobre sgdm.* y sgdm_backup tiene SHOW VIEW.
-- ============================================================

USE sgdm;

-- ---------------------------------------------------------
-- 1) Corrección de resultados
-- ---------------------------------------------------------
ALTER TABLE resultados
    ADD COLUMN IF NOT EXISTS corregido_por     INT      NULL AFTER fecha_carga,
    ADD COLUMN IF NOT EXISTS fecha_correccion  DATETIME NULL AFTER corregido_por;

-- Mismo criterio que auditoria: si la cuenta del usuario se anonimiza
-- la fila de usuarios sigue existiendo, así que la FK no estorba.
ALTER TABLE resultados
    ADD CONSTRAINT fk_resultados_corregido_por FOREIGN KEY IF NOT EXISTS (corregido_por) REFERENCES usuarios(id);

-- ---------------------------------------------------------
-- 2) Vistas para el panel de administración
-- ---------------------------------------------------------

-- Una sola fila con los números del dashboard.
-- "Partido" = enfrentamiento con dos lados: los byes (visitante NULL)
-- no se juegan y no cuentan.
CREATE OR REPLACE VIEW v_resumen_admin AS
SELECT
    (SELECT COUNT(*) FROM usuarios WHERE activo = 1)                                   AS usuarios_activos,
    (SELECT COUNT(*) FROM usuarios
      WHERE activo = 0 AND nombre_usuario NOT LIKE 'usuario\_eliminado\_%')            AS usuarios_suspendidos,
    (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol_id = 2)                    AS organizadores,
    (SELECT COUNT(*) FROM torneos)                                                     AS torneos_total,
    (SELECT COUNT(*) FROM torneos WHERE estado = 'inscripciones_abiertas')             AS torneos_abiertos,
    (SELECT COUNT(*) FROM torneos WHERE estado = 'en_curso')                           AS torneos_en_curso,
    (SELECT COUNT(*) FROM torneos WHERE estado = 'finalizado')                         AS torneos_finalizados,
    (SELECT COUNT(*) FROM torneos WHERE organizador_asignado_id IS NULL
                                    AND estado <> 'finalizado')                        AS torneos_sin_organizador,
    (SELECT COUNT(*) FROM inscripciones WHERE estado_inscripcion = 'activa')           AS inscripciones_activas,
    (SELECT COUNT(*) FROM enfrentamientos e
       JOIN resultados r ON r.enfrentamiento_id = e.id
      WHERE e.inscripcion_visitante_id IS NOT NULL)                                    AS partidos_jugados,
    (SELECT COUNT(*) FROM enfrentamientos e
       LEFT JOIN resultados r ON r.enfrentamiento_id = e.id
      WHERE e.inscripcion_visitante_id IS NOT NULL AND r.id IS NULL)                   AS partidos_pendientes,
    (SELECT COUNT(*) FROM resultados WHERE corregido_por IS NOT NULL)                  AS resultados_corregidos,
    (SELECT COUNT(*) FROM auditoria WHERE DATE(fecha) = CURDATE())                     AS acciones_hoy;

-- Historial de auditoría ya unido con el nombre de quien hizo cada acción.
CREATE OR REPLACE VIEW v_auditoria_detalle AS
SELECT a.id,
       a.fecha,
       a.accion,
       a.tabla_afectada,
       a.registro_id,
       a.detalle,
       a.ip,
       a.usuario_id,
       u.nombre_usuario,
       u.nombre_completo
  FROM auditoria a
  LEFT JOIN usuarios u ON u.id = a.usuario_id;

-- Un renglón por torneo, con quién lo creó/gestiona y su avance.
CREATE OR REPLACE VIEW v_torneos_resumen AS
SELECT t.id,
       t.nombre,
       t.deporte,
       t.formato,
       t.modalidad,
       t.estado,
       t.fecha_creacion,
       t.organizador_asignado_id,
       creador.nombre_completo     AS nombre_creador,
       organizador.nombre_completo AS nombre_organizador,
       (SELECT COUNT(*) FROM inscripciones i
         WHERE i.torneo_id = t.id AND i.estado_inscripcion = 'activa')                 AS inscriptos,
       (SELECT COUNT(*) FROM enfrentamientos e
          JOIN rondas ro   ON ro.id = e.ronda_id
          JOIN resultados r ON r.enfrentamiento_id = e.id
         WHERE ro.torneo_id = t.id AND e.inscripcion_visitante_id IS NOT NULL)         AS partidos_jugados,
       (SELECT COUNT(*) FROM enfrentamientos e
          JOIN rondas ro ON ro.id = e.ronda_id
     LEFT JOIN resultados r ON r.enfrentamiento_id = e.id
         WHERE ro.torneo_id = t.id AND e.inscripcion_visitante_id IS NOT NULL
           AND r.id IS NULL)                                                           AS partidos_pendientes
  FROM torneos t
  JOIN usuarios creador          ON creador.id = t.creado_por
  LEFT JOIN usuarios organizador ON organizador.id = t.organizador_asignado_id;
