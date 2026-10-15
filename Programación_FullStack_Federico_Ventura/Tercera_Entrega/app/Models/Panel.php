<?php
/**
 * app/Models/Panel.php
 *
 * Consultas del panel de administración. Los resúmenes salen de las vistas
 * SQL (bd/sgdm_migracion_correccion_vistas.sql): v_resumen_admin,
 * v_torneos_resumen y v_auditoria_detalle.
 */

require_once __DIR__ . '/../../config/database.php';

class Panel
{
    public const POR_PAGINA = 10;

    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    /** Los números del dashboard (una sola fila). */
    public function resumen(): array
    {
        return $this->conexion->query('SELECT * FROM v_resumen_admin')->fetch();
    }

    /** Cuántos torneos hay de cada formato: ['liga' => 3, ...]. */
    public function torneosPorFormato(): array
    {
        $filas = $this->conexion->query('SELECT formato, COUNT(*) AS total FROM torneos GROUP BY formato')->fetchAll();
        return array_column($filas, 'total', 'formato');
    }

    /**
     * Usuarios con filtro de texto (nombre, usuario o email) y de estado
     * ('activo', 'suspendido', 'eliminado' o '' para todos), paginados.
     */
    public function listarUsuarios(string $texto, string $estado, int $pagina): array
    {
        $donde  = [];
        $params = [];
        if ($texto !== '') {
            $donde[] = '(u.nombre_completo LIKE :t1 OR u.nombre_usuario LIKE :t2 OR u.email LIKE :t3)';
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $params += ['t1' => $like, 't2' => $like, 't3' => $like];
        }
        $eliminado = "u.nombre_usuario LIKE 'usuario\\_eliminado\\_%'";
        if ($estado === 'activo') {
            $donde[] = 'u.activo = 1';
        } elseif ($estado === 'suspendido') {
            $donde[] = "u.activo = 0 AND NOT ($eliminado)";
        } elseif ($estado === 'eliminado') {
            $donde[] = $eliminado;
        }
        $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

        $total = $this->conexion->prepare("SELECT COUNT(*) FROM usuarios u $where");
        $total->execute($params);

        $consulta = $this->conexion->prepare(
            "SELECT u.id, u.nombre_completo, u.nombre_usuario, u.email, u.rol_id, r.nombre AS rol, u.activo,
                    ($eliminado) AS eliminado, u.fecha_registro
               FROM usuarios u JOIN roles r ON r.id = u.rol_id
               $where
              ORDER BY u.fecha_registro DESC, u.id DESC
              LIMIT :limite OFFSET :desde"
        );
        foreach ($params as $k => $v) {
            $consulta->bindValue($k, $v);
        }
        $this->paginar($consulta, $pagina);
        $consulta->execute();

        return ['filas' => $consulta->fetchAll(), 'total' => (int) $total->fetchColumn()];
    }

    /** Suspende (0) o reactiva (1). No toca cuentas ya dadas de baja (anonimizadas). */
    public function cambiarEstadoUsuario(int $usuarioId, bool $activo): bool
    {
        $consulta = $this->conexion->prepare(
            "UPDATE usuarios SET activo = :activo
              WHERE id = :id AND nombre_usuario NOT LIKE 'usuario\\_eliminado\\_%'"
        );
        $consulta->execute(['activo' => $activo ? 1 : 0, 'id' => $usuarioId]);
        return $consulta->rowCount() > 0;
    }

    public function buscarUsuario(int $id): array|false
    {
        $consulta = $this->conexion->prepare(
            "SELECT id, nombre_completo, nombre_usuario, rol_id, activo FROM usuarios WHERE id = :id"
        );
        $consulta->execute(['id' => $id]);
        return $consulta->fetch();
    }

    /** Torneos (vista v_torneos_resumen) con filtro por nombre/deporte y estado. */
    public function listarTorneos(string $texto, string $estado, int $pagina): array
    {
        $donde  = [];
        $params = [];
        if ($texto !== '') {
            $donde[] = '(nombre LIKE :t1 OR deporte LIKE :t2)';
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $params += ['t1' => $like, 't2' => $like];
        }
        if (in_array($estado, ['inscripciones_abiertas', 'en_curso', 'finalizado'], true)) {
            $donde[]          = 'estado = :estado';
            $params['estado'] = $estado;
        }
        $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

        $total = $this->conexion->prepare("SELECT COUNT(*) FROM v_torneos_resumen $where");
        $total->execute($params);

        $consulta = $this->conexion->prepare(
            "SELECT * FROM v_torneos_resumen $where ORDER BY fecha_creacion DESC, id DESC LIMIT :limite OFFSET :desde"
        );
        foreach ($params as $k => $v) {
            $consulta->bindValue($k, $v);
        }
        $this->paginar($consulta, $pagina);
        $consulta->execute();

        return ['filas' => $consulta->fetchAll(), 'total' => (int) $total->fetchColumn()];
    }

    /** Acciones distintas que existen en el historial (para el filtro). */
    public function accionesDeAuditoria(): array
    {
        // Las que ya están en la base más las que el sistema sabe registrar, así el filtro
        // ofrece también acciones que todavía no ocurrieron (por ejemplo una suspensión nueva).
        $existentes = $this->conexion->query('SELECT DISTINCT accion FROM auditoria')->fetchAll(PDO::FETCH_COLUMN);
        $conocidas  = ['CARGA_RESULTADO', 'CORRECCION_RESULTADO', 'SUSPENSION_USUARIO', 'REACTIVACION_USUARIO'];
        $todas      = array_values(array_unique(array_merge($existentes, $conocidas)));
        sort($todas);
        return $todas;
    }

    /** Historial de auditoría (vista v_auditoria_detalle), con filtro por acción y por texto. */
    public function listarAuditoria(string $accion, string $texto, int $pagina): array
    {
        $donde  = [];
        $params = [];
        if ($accion !== '') {
            $donde[]          = 'accion = :accion';
            $params['accion'] = $accion;
        }
        if ($texto !== '') {
            $donde[] = '(detalle LIKE :t1 OR nombre_usuario LIKE :t2 OR nombre_completo LIKE :t3)';
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $params += ['t1' => $like, 't2' => $like, 't3' => $like];
        }
        $where = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';

        $total = $this->conexion->prepare("SELECT COUNT(*) FROM v_auditoria_detalle $where");
        $total->execute($params);

        $consulta = $this->conexion->prepare(
            "SELECT * FROM v_auditoria_detalle $where ORDER BY fecha DESC, id DESC LIMIT :limite OFFSET :desde"
        );
        foreach ($params as $k => $v) {
            $consulta->bindValue($k, $v);
        }
        $this->paginar($consulta, $pagina);
        $consulta->execute();

        return ['filas' => $consulta->fetchAll(), 'total' => (int) $total->fetchColumn()];
    }

    private function paginar(PDOStatement $consulta, int $pagina): void
    {
        $pagina = max(1, $pagina);
        $consulta->bindValue('limite', self::POR_PAGINA, PDO::PARAM_INT);
        $consulta->bindValue('desde', ($pagina - 1) * self::POR_PAGINA, PDO::PARAM_INT);
    }
}
