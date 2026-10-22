<?php
/**
 * app/Models/Reporte.php
 *
 * Datos de los reportes (solo lectura). Cada método devuelve la estructura que entiende
 * app/Helpers/reporte.php: título, subtítulo opcional y una lista de secciones con columnas y filas.
 *
 *  - Administrador general (5.1): resumen, torneos, usuarios y auditoría.
 *  - Organizador / Administrador sobre un torneo (5.2): datos, participantes, tabla y resultados.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/TablaPosiciones.php';
require_once __DIR__ . '/../Helpers/texto.php';

class Reporte
{
    /** Tope de filas del reporte de auditoría, para que una descarga no sea interminable. */
    public const MAX_AUDITORIA = 5000;

    public const ETIQUETA_ESTADO  = ['inscripciones_abiertas' => 'Inscripciones abiertas', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'];
    public const ETIQUETA_FORMATO = ['liga' => 'Liga', 'eliminacion' => 'Eliminación directa', 'suizo' => 'Sistema suizo'];

    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    // ---------------------------------------------------------
    // Administrador general (5.1)
    // ---------------------------------------------------------

    public function resumen(): array
    {
        $r = $this->conexion->query('SELECT * FROM v_resumen_admin')->fetch();

        $general = [
            ['Usuarios activos', $r['usuarios_activos']],
            ['Usuarios suspendidos', $r['usuarios_suspendidos']],
            ['Organizadores activos', $r['organizadores']],
            ['Torneos en total', $r['torneos_total']],
            ['Torneos con inscripciones abiertas', $r['torneos_abiertos']],
            ['Torneos en curso', $r['torneos_en_curso']],
            ['Torneos finalizados', $r['torneos_finalizados']],
            ['Torneos sin organizador (sin finalizar)', $r['torneos_sin_organizador']],
            ['Inscripciones activas', $r['inscripciones_activas']],
            ['Partidos jugados', $r['partidos_jugados']],
            ['Partidos pendientes', $r['partidos_pendientes']],
            ['Resultados corregidos', $r['resultados_corregidos']],
            ['Resultados pendientes de validación', (int) $this->conexion->query('SELECT COUNT(*) FROM resultados WHERE validado = 0')->fetchColumn()],
            ['Acciones registradas hoy', $r['acciones_hoy']],
        ];

        $porDeporte = $this->conexion->query(
            "SELECT t.deporte, COUNT(*) AS torneos,
                    (SELECT COUNT(*) FROM inscripciones i JOIN torneos t2 ON t2.id = i.torneo_id
                      WHERE t2.deporte = t.deporte AND i.estado_inscripcion = 'activa') AS inscripciones
               FROM torneos t GROUP BY t.deporte ORDER BY torneos DESC, t.deporte"
        )->fetchAll(PDO::FETCH_NUM);

        $porFormato = $this->conexion->query('SELECT formato, COUNT(*) FROM torneos GROUP BY formato ORDER BY formato')->fetchAll(PDO::FETCH_NUM);
        foreach ($porFormato as &$fila) {
            $fila[0] = self::ETIQUETA_FORMATO[$fila[0]] ?? $fila[0];
        }
        unset($fila);

        return [
            'titulo'    => 'Resumen general del sistema',
            'secciones' => [
                ['titulo' => 'Indicadores', 'columnas' => ['Indicador', 'Valor'], 'filas' => $general],
                ['titulo' => 'Torneos por formato', 'columnas' => ['Formato', 'Torneos'], 'filas' => $porFormato, 'vacio' => 'Todavía no hay torneos.'],
                ['titulo' => 'Torneos e inscripciones por deporte', 'columnas' => ['Deporte', 'Torneos', 'Inscripciones activas'], 'filas' => $porDeporte, 'vacio' => 'Todavía no hay torneos.'],
            ],
        ];
    }

    public function torneos(string $estado): array
    {
        $params = [];
        $where  = '';
        if (isset(self::ETIQUETA_ESTADO[$estado])) {
            $where = 'WHERE estado = :estado';
            $params['estado'] = $estado;
        }
        $consulta = $this->conexion->prepare(
            "SELECT id, nombre, deporte, formato, modalidad, estado, nombre_organizador, nombre_creador,
                    inscriptos, partidos_jugados, partidos_pendientes, fecha_creacion
               FROM v_torneos_resumen $where ORDER BY fecha_creacion DESC, id DESC"
        );
        $consulta->execute($params);

        $filas = [];
        foreach ($consulta->fetchAll() as $t) {
            $filas[] = [
                $t['id'], $t['nombre'], $t['deporte'], self::ETIQUETA_FORMATO[$t['formato']] ?? $t['formato'],
                $t['modalidad'] === 'equipo' ? 'Por equipos' : 'Individual', self::ETIQUETA_ESTADO[$t['estado']] ?? $t['estado'],
                $t['nombre_organizador'] ?? 'Sin asignar', $t['nombre_creador'], $t['inscriptos'],
                $t['partidos_jugados'], $t['partidos_pendientes'], substr((string) $t['fecha_creacion'], 0, 10),
            ];
        }

        return [
            'titulo'    => 'Reporte de torneos',
            'subtitulo' => isset(self::ETIQUETA_ESTADO[$estado]) ? 'Estado: ' . self::ETIQUETA_ESTADO[$estado] : 'Todos los estados',
            'secciones' => [[
                'titulo'   => 'Torneos (' . count($filas) . ')',
                'columnas' => ['Id', 'Torneo', 'Deporte', 'Formato', 'Modalidad', 'Estado', 'Organizador', 'Creado por', 'Inscriptos', 'Partidos jugados', 'Partidos pendientes', 'Creado el'],
                'filas'    => $filas,
                'vacio'    => 'No hay torneos con ese filtro.',
            ]],
        ];
    }

    /** No incluye contraseñas ni celular: solo lo necesario para administrar cuentas. */
    public function usuarios(string $estado, int $rolId): array
    {
        $donde  = [];
        $params = [];
        $eliminado = "u.nombre_usuario LIKE 'usuario\\_eliminado\\_%'";
        if ($estado === 'activo') {
            $donde[] = 'u.activo = 1';
        } elseif ($estado === 'suspendido') {
            $donde[] = "u.activo = 0 AND NOT ($eliminado)";
        } elseif ($estado === 'eliminado') {
            $donde[] = $eliminado;
        }
        if ($rolId > 0) {
            $donde[]         = 'u.rol_id = :rol';
            $params['rol']   = $rolId;
        }
        $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

        $consulta = $this->conexion->prepare(
            "SELECT u.id, u.nombre_usuario, u.nombre_completo, u.email, r.nombre AS rol, u.activo,
                    ($eliminado) AS eliminado, u.fecha_registro
               FROM usuarios u JOIN roles r ON r.id = u.rol_id $where ORDER BY u.id"
        );
        $consulta->execute($params);

        $filas = [];
        foreach ($consulta->fetchAll() as $u) {
            $filas[] = [
                $u['id'], $u['nombre_usuario'], $u['nombre_completo'], $u['email'], $u['rol'],
                (int) $u['eliminado'] === 1 ? 'Dado de baja' : ((int) $u['activo'] === 1 ? 'Activo' : 'Suspendido'),
                substr((string) $u['fecha_registro'], 0, 10),
            ];
        }

        $nombres = [1 => 'Administradores', 2 => 'Organizadores', 3 => 'Participantes'];
        $partes  = [];
        $partes[] = $estado !== '' ? 'Estado: ' . ['activo' => 'activos', 'suspendido' => 'suspendidos', 'eliminado' => 'dados de baja'][$estado] : 'Todos los estados';
        $partes[] = $rolId > 0 ? 'Rol: ' . ($nombres[$rolId] ?? $rolId) : 'Todos los roles';

        return [
            'titulo'    => 'Reporte de usuarios',
            'subtitulo' => implode(' · ', $partes),
            'secciones' => [[
                'titulo'   => 'Usuarios (' . count($filas) . ')',
                'columnas' => ['Id', 'Usuario', 'Nombre', 'Correo', 'Rol', 'Estado', 'Registrado el'],
                'filas'    => $filas,
                'vacio'    => 'No hay usuarios con ese filtro.',
            ]],
        ];
    }

    /** Historial de auditoría entre dos fechas (YYYY-MM-DD, opcionales) y, si se indica, una acción. */
    public function auditoria(string $desde, string $hasta, string $accion): array
    {
        $donde  = [];
        $params = [];
        if ($desde !== '') {
            $donde[]         = 'fecha >= :desde';
            $params['desde'] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $donde[]         = 'fecha <= :hasta';
            $params['hasta'] = $hasta . ' 23:59:59';
        }
        if ($accion !== '') {
            $donde[]          = 'accion = :accion';
            $params['accion'] = $accion;
        }
        $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

        $consulta = $this->conexion->prepare(
            "SELECT fecha, nombre_usuario, accion, tabla_afectada, registro_id, detalle, ip
               FROM v_auditoria_detalle $where ORDER BY fecha DESC, id DESC LIMIT " . (self::MAX_AUDITORIA + 1)
        );
        $consulta->execute($params);
        $filas = $consulta->fetchAll(PDO::FETCH_NUM);

        $recortado = count($filas) > self::MAX_AUDITORIA;
        if ($recortado) {
            array_pop($filas);
        }

        $partes   = [];
        $partes[] = ($desde !== '' || $hasta !== '') ? 'Período: ' . ($desde !== '' ? $this->fechaLatina($desde) : 'inicio') . ' a ' . ($hasta !== '' ? $this->fechaLatina($hasta) : 'hoy') : 'Todo el historial';
        $partes[] = $accion !== '' ? 'Acción: ' . $accion : 'Todas las acciones';
        if ($recortado) {
            $partes[] = 'Se muestran los ' . self::MAX_AUDITORIA . ' registros más recientes; acotá las fechas para ver el resto';
        }

        return [
            'titulo'    => 'Reporte de auditoría',
            'subtitulo' => implode(' · ', $partes),
            'secciones' => [[
                'titulo'   => 'Acciones registradas (' . count($filas) . ')',
                'columnas' => ['Fecha', 'Usuario', 'Acción', 'Tabla', 'Registro', 'Detalle', 'IP'],
                'filas'    => $filas,
                'vacio'    => 'No hay acciones registradas con ese filtro.',
            ]],
        ];
    }

    // ---------------------------------------------------------
    // Un torneo (5.2)
    // ---------------------------------------------------------

    public function torneo(int $torneoId): array|false
    {
        $consulta = $this->conexion->prepare(
            "SELECT t.*, creador.nombre_completo AS nombre_creador, organizador.nombre_completo AS nombre_organizador,
                    ganador.nombre AS nombre_ganador
               FROM torneos t
               JOIN usuarios creador          ON creador.id = t.creado_por
          LEFT JOIN usuarios organizador      ON organizador.id = t.organizador_asignado_id
          LEFT JOIN (SELECT i.id, COALESCE(u.nombre_completo, eq.nombre_equipo) AS nombre
                       FROM inscripciones i
                  LEFT JOIN usuarios u ON u.id = i.usuario_id
                  LEFT JOIN equipos eq ON eq.id = i.equipo_id) ganador ON ganador.id = t.ganador_inscripcion_id
              WHERE t.id = :id"
        );
        $consulta->execute(['id' => $torneoId]);
        $t = $consulta->fetch();
        if (!$t) {
            return false;
        }

        $unidad = $t['modalidad'] === 'equipo' ? 'Equipos' : 'Participantes';
        $dato   = static fn (?string $f): string => $f ? date('d/m/Y', strtotime($f)) : 'Sin definir';

        $datos = [
            ['Torneo', $t['nombre']],
            ['Deporte', $t['deporte']],
            ['Formato', self::ETIQUETA_FORMATO[$t['formato']] ?? $t['formato']],
            ['Modalidad', $t['modalidad'] === 'equipo' ? 'Por equipos (' . (int) $t['jugadores_por_equipo'] . ' jugadores)' : 'Individual'],
            ['Estado', self::ETIQUETA_ESTADO[$t['estado']] ?? $t['estado']],
            ['Cupos', $t['cupos'] === null ? 'Sin límite' : $t['cupos']],
            ['Apertura de inscripciones', $dato($t['fecha_apertura_inscripcion'])],
            ['Cierre de inscripciones', $dato($t['fecha_cierre_inscripcion'])],
            ['Inicio', $dato($t['fecha_inicio'])],
            ['Fin', $dato($t['fecha_fin'])],
            ['Organizador', $t['nombre_organizador'] ?? 'Sin asignar'],
            ['Creado por', $t['nombre_creador']],
            ['Campeón', $t['nombre_ganador'] ?? ($t['estado'] === 'finalizado' ? 'Sin definir' : 'Todavía no hay')],
            ['Premio', textoPremio($t) ?? 'Sin premio'],
            ['Visibilidad', $t['visibilidad'] === 'privado' ? 'Privado (solo con el link)' : 'Público'],
        ];

        $ins = $this->conexion->prepare(
            "SELECT COALESCE(u.nombre_completo, eq.nombre_equipo) AS nombre, i.fecha_inscripcion
               FROM inscripciones i
          LEFT JOIN usuarios u ON u.id = i.usuario_id
          LEFT JOIN equipos eq ON eq.id = i.equipo_id
              WHERE i.torneo_id = :id AND i.estado_inscripcion = 'activa'
              ORDER BY nombre"
        );
        $ins->execute(['id' => $torneoId]);
        $inscriptos = [];
        foreach ($ins->fetchAll() as $n => $i) {
            $inscriptos[] = [$n + 1, $i['nombre'], substr((string) $i['fecha_inscripcion'], 0, 10)];
        }

        // Mismo orden (y mismos desempates) que ve el público en detalle.php.
        $tabla = [];
        foreach ((new TablaPosiciones($this->conexion))->listarPorTorneo($torneoId) as $n => $p) {
            $tabla[] = [$n + 1, $p['nombre'], $p['puntos'], $p['partidos_jugados'], $p['partidos_ganados'], $p['partidos_empatados'], $p['partidos_perdidos'],
                        ($p['diferencia'] > 0 ? '+' : '') . $p['diferencia']];
        }

        $res = $this->conexion->prepare(
            "SELECT ro.numero, COALESCE(ro.nombre, CONCAT('Ronda ', ro.numero)) AS ronda,
                    COALESCE(ul.nombre_completo, eql.nombre_equipo) AS local_nombre,
                    COALESCE(uv.nombre_completo, eqv.nombre_equipo) AS visitante_nombre,
                    e.inscripcion_visitante_id, r.puntaje_local, r.puntaje_visitante, r.id AS res_id,
                    r.fecha_correccion, r.validado
               FROM enfrentamientos e
               JOIN rondas ro ON ro.id = e.ronda_id
               JOIN inscripciones il ON il.id = e.inscripcion_local_id
          LEFT JOIN inscripciones iv ON iv.id = e.inscripcion_visitante_id
          LEFT JOIN usuarios ul ON ul.id = il.usuario_id
          LEFT JOIN equipos eql ON eql.id = il.equipo_id
          LEFT JOIN usuarios uv ON uv.id = iv.usuario_id
          LEFT JOIN equipos eqv ON eqv.id = iv.equipo_id
          LEFT JOIN resultados r ON r.enfrentamiento_id = e.id
              WHERE ro.torneo_id = :id
              ORDER BY ro.numero, e.id"
        );
        $res->execute(['id' => $torneoId]);
        $partidos = [];
        foreach ($res->fetchAll() as $p) {
            if ($p['inscripcion_visitante_id'] === null) {
                $partidos[] = [$p['ronda'], $p['local_nombre'], '', '', 'Libre (pasa de ronda)', 'Bye'];
                continue;
            }
            $jugado = $p['res_id'] !== null;
            $partidos[] = [
                $p['ronda'], $p['local_nombre'], $jugado ? $p['puntaje_local'] : '', $jugado ? $p['puntaje_visitante'] : '',
                $p['visitante_nombre'], $jugado ? ((int) $p['validado'] === 0 ? 'Pendiente de validación' : ($p['fecha_correccion'] ? 'Jugado (corregido)' : 'Jugado')) : 'Pendiente',
            ];
        }

        $secciones = [
            ['titulo' => 'Datos del torneo', 'columnas' => ['Dato', 'Valor'], 'filas' => $datos],
            ['titulo' => $unidad . ' inscriptos (' . count($inscriptos) . ')', 'columnas' => ['N.º', $t['modalidad'] === 'equipo' ? 'Equipo' : 'Participante', 'Inscripto el'],
             'filas' => $inscriptos, 'vacio' => 'Todavía no hay inscriptos.'],
        ];
        if ($t['estado'] !== 'inscripciones_abiertas') {
            $secciones[] = ['titulo' => 'Tabla de posiciones', 'columnas' => ['Pos.', $t['modalidad'] === 'equipo' ? 'Equipo' : 'Participante', 'Puntos', 'PJ', 'PG', 'PE', 'PP', 'Dif.'],
                            'filas' => $tabla, 'vacio' => 'Todavía no hay posiciones.'];
            $secciones[] = ['titulo' => 'Partidos y resultados (' . count($partidos) . ')', 'columnas' => ['Ronda', 'Local', 'Puntaje local', 'Puntaje visitante', 'Visitante', 'Estado'],
                            'filas' => $partidos, 'vacio' => 'Todavía no hay partidos.'];
        }

        return [
            'titulo'    => 'Reporte del torneo: ' . $t['nombre'],
            'subtitulo' => $t['deporte'] . ' · ' . (self::ETIQUETA_FORMATO[$t['formato']] ?? $t['formato']) . ' · ' . (self::ETIQUETA_ESTADO[$t['estado']] ?? $t['estado']),
            'secciones' => $secciones,
        ];
    }

    private function fechaLatina(string $ymd): string
    {
        return date('d/m/Y', strtotime($ymd));
    }
}
