-- ============================================================
-- sgdm_migracion_modulos_config.sql
-- Se corre DESPUÉS de sgdm_migracion_correccion_vistas.sql.
--
-- La letra (5.1) pide que el Administrador general pueda habilitar o
-- deshabilitar módulos y administrar la configuración general del sistema.
--
--  * modulos_competencia: un renglón por formato de competencia (liga,
--    eliminación directa, sistema suizo). Un módulo deshabilitado deja de
--    ofrecerse al crear torneos nuevos; los torneos que ya existen con ese
--    formato siguen funcionando hasta terminar.
--  * configuracion: parámetros generales con clave y valor. Hoy hay dos que
--    el sistema usa de verdad: el cupo máximo al crear un torneo y si se
--    permite el registro público de usuarios nuevos.
--
-- actualizado_por usa ON DELETE SET NULL: si alguna vez se borra la cuenta del
-- administrador que hizo el cambio (por ejemplo al recargar los datos de prueba)
-- el cambio queda y solo se pierde quién lo hizo.
-- Se puede correr más de una vez sin efecto.
-- ============================================================

USE sgdm;

CREATE TABLE IF NOT EXISTS modulos_competencia (
    formato             VARCHAR(20)  NOT NULL,
    nombre              VARCHAR(40)  NOT NULL,
    descripcion         VARCHAR(200) NOT NULL,
    habilitado          TINYINT(1)   NOT NULL DEFAULT 1,
    actualizado_por     INT          NULL,
    fecha_actualizacion DATETIME     NULL,
    PRIMARY KEY (formato),
    CONSTRAINT chk_modulo_formato    CHECK (formato IN ('liga', 'eliminacion', 'suizo')),
    CONSTRAINT chk_modulo_habilitado CHECK (habilitado IN (0, 1)),
    CONSTRAINT fk_modulo_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO modulos_competencia (formato, nombre, descripcion) VALUES
    ('liga',        'Liga',                'Todos contra todos. Victoria 3 puntos, empate 1.'),
    ('eliminacion', 'Eliminación directa', 'El perdedor queda eliminado; se arma una ronda por vez, con descansos si hace falta.'),
    ('suizo',       'Sistema suizo',       'Emparejamiento por puntos sin repetir rival; cantidad de rondas fija.');

CREATE TABLE IF NOT EXISTS configuracion (
    clave               VARCHAR(50)  NOT NULL,
    valor               VARCHAR(100) NOT NULL,
    descripcion         VARCHAR(200) NOT NULL,
    actualizado_por     INT          NULL,
    fecha_actualizacion DATETIME     NULL,
    PRIMARY KEY (clave),
    CONSTRAINT fk_config_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
    ('max_participantes_por_torneo', '512', 'Cupo máximo que se puede definir al crear un torneo (entre 2 y 512)'),
    ('permitir_registro',            '1',   'Si es 1, cualquier persona puede crear una cuenta; si es 0, solo el administrador crea cuentas');
