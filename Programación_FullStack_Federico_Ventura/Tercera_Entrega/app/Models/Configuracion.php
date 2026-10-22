<?php
/**
 * app/Models/Configuracion.php
 *
 * Módulos de competencia habilitados y parámetros generales del sistema
 * (tablas modulos_competencia y configuracion). Si la base todavía no tiene
 * la migración, las lecturas devuelven los valores por defecto en lugar de
 * romper las páginas públicas (se deja registro del problema en el log).
 */

require_once __DIR__ . '/../../config/database.php';

class Configuracion
{
    public const FORMATOS = ['liga', 'eliminacion', 'suizo'];
    public const MAX_PARTICIPANTES_POR_DEFECTO = 512;

    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    /** @return array<int, array<string, mixed>> Un renglón por módulo, en orden fijo. */
    public function modulos(): array
    {
        try {
            return $this->conexion->query(
                "SELECT formato, nombre, descripcion, habilitado FROM modulos_competencia
                  ORDER BY FIELD(formato, 'liga', 'eliminacion', 'suizo')"
            )->fetchAll();
        } catch (PDOException $e) {
            error_log('[SGDM] Falta la tabla modulos_competencia (¿se corrió la migración?): ' . $e->getMessage());
            return array_map(static fn ($f) => ['formato' => $f, 'nombre' => $f, 'descripcion' => '', 'habilitado' => 1], self::FORMATOS);
        }
    }

    /** @return string[] Formatos que se pueden elegir al crear un torneo nuevo. */
    public function formatosHabilitados(): array
    {
        $habilitados = [];
        foreach ($this->modulos() as $modulo) {
            if ((int) $modulo['habilitado'] === 1) {
                $habilitados[] = $modulo['formato'];
            }
        }
        return $habilitados;
    }

    public function cambiarModulo(string $formato, bool $habilitado, int $actorId): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE modulos_competencia SET habilitado = :h, actualizado_por = :actor, fecha_actualizacion = NOW() WHERE formato = :f'
        );
        $consulta->execute(['h' => $habilitado ? 1 : 0, 'actor' => $actorId, 'f' => $formato]);
        return $consulta->rowCount() > 0;
    }

    /** @return array<string, string> clave => valor */
    public function todas(): array
    {
        try {
            $filas = $this->conexion->query('SELECT clave, valor FROM configuracion')->fetchAll();
            return array_column($filas, 'valor', 'clave');
        } catch (PDOException $e) {
            error_log('[SGDM] Falta la tabla configuracion (¿se corrió la migración?): ' . $e->getMessage());
            return [];
        }
    }

    public function maxParticipantes(): int
    {
        $valor = (int) ($this->todas()['max_participantes_por_torneo'] ?? self::MAX_PARTICIPANTES_POR_DEFECTO);
        return ($valor >= 2 && $valor <= self::MAX_PARTICIPANTES_POR_DEFECTO) ? $valor : self::MAX_PARTICIPANTES_POR_DEFECTO;
    }

    public function registroPermitido(): bool
    {
        return ($this->todas()['permitir_registro'] ?? '1') !== '0';
    }

    private function existe(string $clave): bool
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM configuracion WHERE clave = :c');
        $consulta->execute(['c' => $clave]);
        return (int) $consulta->fetchColumn() > 0;
    }

    /** true si los resultados que carga un organizador esperan la validación de un administrador. */
    public function validacionActiva(): bool
    {
        return ($this->todas()['validar_resultados'] ?? '0') === '1';
    }

    public function establecer(string $clave, string $valor, int $actorId): bool
    {
        $consulta = $this->conexion->prepare(
            'UPDATE configuracion SET valor = :v, actualizado_por = :actor, fecha_actualizacion = NOW() WHERE clave = :c'
        );
        $consulta->execute(['v' => $valor, 'actor' => $actorId, 'c' => $clave]);
        return $consulta->rowCount() > 0 || $this->existe($clave);
    }
}
