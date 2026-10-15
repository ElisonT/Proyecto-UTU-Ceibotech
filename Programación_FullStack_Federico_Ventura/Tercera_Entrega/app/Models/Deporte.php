<?php
/**
 * app/Models/Deporte.php
 *
 * Catálogo de deportes y juegos con el tamaño estándar de equipo de cada
 * uno (Fútbol 11 = 11, League of Legends = 5, Truco = 2, Ajedrez = 1...).
 * jugadores_por_equipo = 1 significa que se juega de forma individual. El
 * deporte "Otro" es libre (es_libre = 1): ahí el nombre y el tamaño los
 * escribe quien crea el torneo o el equipo.
 * De este catálogo salen la modalidad de un torneo y el tamaño de los
 * equipos, así que nadie los elige a mano.
 */

require_once __DIR__ . '/../../config/database.php';

class Deporte
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    public function listarTodos(): array
    {
        return $this->conexion
            ->query('SELECT id, codigo, nombre, categoria, jugadores_por_equipo, es_libre FROM deportes ORDER BY orden ASC, nombre ASC')
            ->fetchAll();
    }

    /** Deportes que se juegan por equipos (los únicos para los que se puede crear un equipo). */
    public function listarParaEquipos(): array
    {
        return $this->conexion
            ->query('SELECT id, codigo, nombre, categoria, jugadores_por_equipo, es_libre FROM deportes
                      WHERE jugadores_por_equipo >= 2 OR es_libre = 1 ORDER BY orden ASC, nombre ASC')
            ->fetchAll();
    }

    public function obtenerPorCodigo(string $codigo): array|false
    {
        $consulta = $this->conexion->prepare(
            'SELECT id, codigo, nombre, categoria, jugadores_por_equipo, es_libre FROM deportes WHERE codigo = :codigo'
        );
        $consulta->execute(['codigo' => $codigo]);
        return $consulta->fetch();
    }

    /** true si ya hay un deporte del catálogo con ese nombre (sin distinguir mayúsculas). */
    public function existeNombre(string $nombre): bool
    {
        $consulta = $this->conexion->prepare('SELECT COUNT(*) AS total FROM deportes WHERE LOWER(nombre) = LOWER(:nombre)');
        $consulta->execute(['nombre' => $nombre]);
        return (int) $consulta->fetch()['total'] > 0;
    }
}
