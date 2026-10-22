-- ============================================================
-- sgdm_migracion_editar_cancelar.sql
-- Se corre DESPUÉS de sgdm_migracion_validacion_resultados.sql.
--
-- Editar y cancelar torneos:
--   * torneos.estado suma el valor 'cancelado'. Un torneo cancelado conserva sus inscripciones,
--     rondas y resultados (queda como historia) pero no admite nada más: ni inscripciones ni resultados,
--     y libera a los equipos que estaban "bloqueados" por estar inscriptos.
--   * Se rehace la vista v_resumen_admin: suma torneos_cancelados y los torneos cancelados dejan de
--     contarse como "sin organizador" (igual que los finalizados).
-- El motivo de cada cancelación y el detalle de cada edición quedan en la tabla auditoria.
-- Se puede correr más de una vez sin efecto.
-- ============================================================

USE sgdm;

ALTER TABLE torneos
    MODIFY COLUMN estado ENUM('inscripciones_abiertas', 'en_curso', 'finalizado', 'cancelado')
        NOT NULL DEFAULT 'inscripciones_abiertas';

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
    (SELECT COUNT(*) FROM torneos WHERE estado = 'cancelado')                          AS torneos_cancelados,
    (SELECT COUNT(*) FROM torneos WHERE organizador_asignado_id IS NULL
                                    AND estado IN ('inscripciones_abiertas', 'en_curso')) AS torneos_sin_organizador,
    (SELECT COUNT(*) FROM inscripciones WHERE estado_inscripcion = 'activa')           AS inscripciones_activas,
    (SELECT COUNT(*) FROM enfrentamientos e
       JOIN resultados r ON r.enfrentamiento_id = e.id
      WHERE e.inscripcion_visitante_id IS NOT NULL)                                    AS partidos_jugados,
    (SELECT COUNT(*) FROM enfrentamientos e
       LEFT JOIN resultados r ON r.enfrentamiento_id = e.id
      WHERE e.inscripcion_visitante_id IS NOT NULL AND r.id IS NULL)                   AS partidos_pendientes,
    (SELECT COUNT(*) FROM resultados WHERE corregido_por IS NOT NULL)                  AS resultados_corregidos,
    (SELECT COUNT(*) FROM auditoria WHERE DATE(fecha) = CURDATE())                     AS acciones_hoy;
