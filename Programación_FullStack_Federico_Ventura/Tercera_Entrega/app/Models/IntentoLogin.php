<?php
/**
 * app/Models/IntentoLogin.php
 *
 * Registro de intentos fallidos de inicio de sesión para frenar la fuerza bruta.
 * Reglas (ventana de 15 minutos):
 *   - MAX_POR_USUARIO fallos del mismo usuario/correo desde la misma IP -> esa combinación queda bloqueada
 *   - MAX_POR_IP fallos desde la misma IP, con cualquier usuario        -> esa IP queda bloqueada
 * Se cuenta por lo que se escribió en el campo (exista o no la cuenta) para no revelar qué cuentas existen,
 * y por IP (REMOTE_ADDR, no encabezados que el cliente puede falsear) para que un atacante no pueda dejar
 * afuera a un usuario desde otra IP.
 */

require_once __DIR__ . '/../../config/database.php';

class IntentoLogin
{
    public const MAX_POR_USUARIO = 5;
    public const MAX_POR_IP      = 20;
    public const VENTANA_MIN     = 15;

    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = obtenerConexion();
    }

    /** Segundos que faltan para poder reintentar (0 = no está bloqueado). */
    public function segundosDeBloqueo(string $login, string $ip): int
    {
        $login = $this->normalizar($login);

        $porUsuario = $this->ultimosFallos('WHERE login = :login AND ip = :ip', ['login' => $login, 'ip' => $ip], self::MAX_POR_USUARIO);
        $porIp      = $this->ultimosFallos('WHERE ip = :ip', ['ip' => $ip], self::MAX_POR_IP);

        $espera = 0;
        foreach ([$porUsuario, $porIp] as $f) {
            // $f = ['cantidad' => n, 'limite_alcanzado' => bool, 'fecha_tope' => fecha del fallo que cuenta como el que bloquea]
            if ($f['limite_alcanzado']) {
                $espera = max($espera, strtotime($f['fecha_tope']) + self::VENTANA_MIN * 60 - time());
            }
        }
        return max(0, $espera);
    }

    public function registrarFallo(string $login, string $ip): void
    {
        $this->conexion->prepare('INSERT INTO intentos_login (login, ip) VALUES (:login, :ip)')
                       ->execute(['login' => $this->normalizar($login), 'ip' => $ip]);
        // Limpieza: lo de más de un día no sirve para nada.
        $this->conexion->exec('DELETE FROM intentos_login WHERE fecha < (NOW() - INTERVAL 1 DAY)');
    }

    /** Después de un inicio de sesión correcto se borran los fallos de esa combinación. */
    public function limpiar(string $login, string $ip): void
    {
        $this->conexion->prepare('DELETE FROM intentos_login WHERE login = :login AND ip = :ip')
                       ->execute(['login' => $this->normalizar($login), 'ip' => $ip]);
    }

    /** Cuántos fallos hay en la ventana y, si llegó al límite, la fecha del fallo que lo cumplió (el más antiguo que cuenta). */
    private function ultimosFallos(string $where, array $params, int $limite): array
    {
        // Se traen los N fallos más recientes de la ventana; si hay N, el más viejo de ellos marca cuándo "vence" el bloqueo.
        $consulta = $this->conexion->prepare(
            "SELECT fecha FROM intentos_login $where AND fecha > (NOW() - INTERVAL " . self::VENTANA_MIN . " MINUTE)
              ORDER BY fecha DESC LIMIT " . $limite
        );
        $consulta->execute($params);
        $fechas = $consulta->fetchAll(PDO::FETCH_COLUMN);

        $alcanzado = count($fechas) >= $limite;
        return ['cantidad' => count($fechas), 'limite_alcanzado' => $alcanzado, 'fecha_tope' => $alcanzado ? end($fechas) : null];
    }

    private function normalizar(string $login): string
    {
        return mb_substr(mb_strtolower(trim($login)), 0, 100);
    }
}
