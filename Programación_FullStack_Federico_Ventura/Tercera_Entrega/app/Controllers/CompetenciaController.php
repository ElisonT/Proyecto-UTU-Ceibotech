<?php
/**
 * app/Controllers/CompetenciaController.php
 *
 * Orquesta la generación automática de enfrentamientos según el
 * formato del torneo (ver letra: liga, eliminación directa, sistema
 * suizo), la carga de resultados y la actualización de la tabla
 * de posiciones.
 *
 * Trabaja con "inscripciones", sin importar si cada una es de un equipo
 * o de un participante individual (ver arco exclusivo en
 * bd/sgdm_migracion_competencia.sql): el algoritmo solo necesita una
 * lista de ids que se enfrentan entre sí.
 */

require_once __DIR__ . '/../Models/Enfrentamiento.php';
require_once __DIR__ . '/../Models/TablaPosiciones.php';
require_once __DIR__ . '/../Models/Auditoria.php';
require_once __DIR__ . '/../Models/Torneo.php';

class CompetenciaController
{
    private Enfrentamiento $modeloEnfrentamiento;
    private TablaPosiciones $modeloPosiciones;
    private Auditoria $auditoria;

    public function __construct()
    {
        $this->modeloEnfrentamiento = new Enfrentamiento();
        $this->modeloPosiciones     = new TablaPosiciones();
        $this->auditoria            = new Auditoria();
    }

    /**
     * Genera el fixture completo de una Liga: todos contra todos, una vez
     * (ida solamente), usando el "método del círculo": se deja un
     * participante fijo y los demás rotan una posición por ronda, lo que
     * garantiza que cada par se enfrente exactamente una vez.
     * Si la cantidad es impar, se agrega un "descanso" (null): en cada
     * ronda, quien le toca contra el descanso simplemente no juega.
     *
     * @return string[] Lista de errores. Vacía si se generó bien.
     */
    public function generarLiga(int $torneoId, int $actorId): array
    {
        if ($this->modeloEnfrentamiento->contarRondas($torneoId) > 0) {
            return ['Este torneo ya tiene un fixture generado.'];
        }

        $inscripciones = $this->modeloEnfrentamiento->listarInscripcionesActivas($torneoId);

        if (count($inscripciones) < 2) {
            return ['Hacen falta al menos 2 inscriptos para generar una liga.'];
        }

        $lista = $inscripciones;
        if (count($lista) % 2 !== 0) {
            $lista[] = null; // descanso
        }

        $total       = count($lista);
        $totalRondas = $total - 1;
        $mitad       = intdiv($total, 2);

        for ($numeroRonda = 1; $numeroRonda <= $totalRondas; $numeroRonda++) {
            $rondaId = $this->modeloEnfrentamiento->crearRonda($torneoId, $numeroRonda, "Fecha {$numeroRonda}");

            for ($i = 0; $i < $mitad; $i++) {
                $local     = $lista[$i];
                $visitante = $lista[$total - 1 - $i];

                // Si uno de los dos es el "descanso", ese partido no se juega.
                if ($local !== null && $visitante !== null) {
                    $this->modeloEnfrentamiento->crearEnfrentamiento($rondaId, $local, $visitante);
                }
            }

            // Rota todos menos el primero (que queda fijo).
            $fijo  = $lista[0];
            $resto = array_slice($lista, 1);
            array_unshift($resto, array_pop($resto));
            $lista = array_merge([$fijo], $resto);
        }

        foreach ($inscripciones as $inscripcionId) {
            $this->modeloPosiciones->inicializar((int) $inscripcionId);
        }

        $this->auditoria->registrar(
            $actorId, 'GENERACION_FIXTURE', 'torneos', $torneoId,
            "Liga generada: " . count($inscripciones) . " inscriptos, {$totalRondas} rondas"
        );

        return [];
    }

    /**
     * Genera la primera ronda de una eliminación directa. Si la cantidad
     * de inscriptos no es potencia de 2, reparte "byes" (descansos):
     * esos inscriptos pasan directo a la ronda siguiente sin jugar.
     * Las rondas siguientes se generan con avanzarRondaEliminacion(),
     * una vez que estén cargados los resultados de la ronda anterior
     * (no se puede armar el cuadro completo de entrada: no se sabe
     * quién avanza hasta que se juega).
     *
     * @return string[] Lista de errores. Vacía si se generó bien.
     */
    public function generarEliminacionDirecta(int $torneoId, int $actorId): array
    {
        if ($this->modeloEnfrentamiento->contarRondas($torneoId) > 0) {
            return ['Este torneo ya tiene un fixture generado.'];
        }

        $inscripciones = $this->modeloEnfrentamiento->listarInscripcionesActivas($torneoId);
        $cantidad = count($inscripciones);

        if ($cantidad < 2) {
            return ['Hacen falta al menos 2 inscriptos para generar una eliminación directa.'];
        }

        $siguientePotencia = 1;
        while ($siguientePotencia < $cantidad) {
            $siguientePotencia *= 2;
        }
        $byes = $siguientePotencia - $cantidad;

        // Se desordena para no favorecer a nadie según el orden de inscripción
        // (no hay ranking/seeding todavía; repartir al azar es lo más justo).
        shuffle($inscripciones);

        $rondaId = $this->modeloEnfrentamiento->crearRonda($torneoId, 1, $this->nombreRonda($siguientePotencia / 2));

        $indice = 0;
        for ($i = 0; $i < $byes; $i++) {
            $this->modeloEnfrentamiento->crearBye($rondaId, $inscripciones[$indice]);
            $indice++;
        }
        while ($indice < $cantidad) {
            $this->modeloEnfrentamiento->crearEnfrentamiento($rondaId, $inscripciones[$indice], $inscripciones[$indice + 1]);
            $indice += 2;
        }

        foreach ($inscripciones as $id) {
            $this->modeloPosiciones->inicializar((int) $id);
        }

        $this->auditoria->registrar(
            $actorId, 'GENERACION_FIXTURE', 'torneos', $torneoId,
            "Eliminación directa generada: {$cantidad} inscriptos, {$byes} bye(s)"
        );

        return [];
    }

    /**
     * Toma los ganadores de la última ronda jugada y arma la ronda
     * siguiente. Si ya queda un solo ganador, el torneo se da por
     * finalizado en vez de generar una ronda nueva.
     *
     * @return string[] Lista de errores. Vacía si avanzó bien (haya
     *                   generado una ronda nueva, o finalizado el torneo).
     */
    public function avanzarRondaEliminacion(int $torneoId, int $actorId): array
    {
        $ultimaRonda = $this->modeloEnfrentamiento->obtenerUltimaRonda($torneoId);
        if ($ultimaRonda === 0) {
            return ['Todavía no se generó el cuadro de este torneo.'];
        }

        $ronda = $this->modeloEnfrentamiento->obtenerRondaPorNumero($torneoId, $ultimaRonda);
        $ganadores = $this->modeloEnfrentamiento->obtenerGanadoresDeRonda((int) $ronda['id']);

        if ($ganadores === null) {
            return ['Todavía hay partidos de la ronda actual sin resultado cargado.'];
        }

        if (count($ganadores) === 1) {
            require_once __DIR__ . '/../Models/Torneo.php';
            (new Torneo())->finalizar($torneoId, $ganadores[0]);
            $this->auditoria->registrar(
                $actorId, 'FINALIZACION_TORNEO', 'torneos', $torneoId,
                "Campeón: inscripción #{$ganadores[0]}"
            );
            return [];
        }

        $numeroNuevaRonda = $ultimaRonda + 1;
        $rondaId = $this->modeloEnfrentamiento->crearRonda($torneoId, $numeroNuevaRonda, $this->nombreRonda((int) (count($ganadores) / 2)));

        for ($i = 0; $i < count($ganadores); $i += 2) {
            $this->modeloEnfrentamiento->crearEnfrentamiento($rondaId, $ganadores[$i], $ganadores[$i + 1]);
        }

        $this->auditoria->registrar(
            $actorId, 'GENERACION_FIXTURE', 'torneos', $torneoId,
            "Ronda {$numeroNuevaRonda} generada con " . count($ganadores) . " clasificados"
        );

        return [];
    }

    /** Nombre prolijo de la ronda, según cuántos van a quedar clasificados después de jugarla. */
    private function nombreRonda(int $clasificadosDespues): string
    {
        return match ($clasificadosDespues) {
            1 => 'Final',
            2 => 'Semifinal',
            4 => 'Cuartos de final',
            8 => 'Octavos de final',
            default => "Ronda (quedan {$clasificadosDespues})",
        };
    }

    /**
     * Carga el resultado de un enfrentamiento y actualiza la tabla de posiciones.
     * En eliminación directa no se aceptan empates (alguien tiene que avanzar).
     *
     * @return string[] Lista de errores. Vacía si se cargó bien.
     */
    public function cargarResultado(int $enfrentamientoId, int $puntajeLocal, int $puntajeVisitante, int $actorId): array
    {
        $enf = $this->modeloEnfrentamiento->obtenerConFormato($enfrentamientoId);
        if (!$enf) {
            return ['El enfrentamiento no existe.'];
        }
        if ((int) $enf['tiene_resultado'] > 0) {
            return ['Este enfrentamiento ya tiene resultado cargado.'];
        }
        if ($enf['inscripcion_visitante_id'] === null) {
            return ['Un bye no se juega, no lleva resultado.'];
        }
        if ($puntajeLocal < 0 || $puntajeVisitante < 0) {
            return ['Los puntajes no pueden ser negativos.'];
        }
        if ($puntajeLocal === $puntajeVisitante && $enf['formato'] === 'eliminacion') {
            return ['En eliminación directa no puede haber empate.'];
        }

        $localId     = (int) $enf['inscripcion_local_id'];
        $visitanteId = (int) $enf['inscripcion_visitante_id'];

        $ganadorId = null;
        if ($puntajeLocal > $puntajeVisitante) {
            $ganadorId = $localId;
        } elseif ($puntajeVisitante > $puntajeLocal) {
            $ganadorId = $visitanteId;
        }

        $this->modeloEnfrentamiento->guardarResultado($enfrentamientoId, $puntajeLocal, $puntajeVisitante, $ganadorId);

        if ($ganadorId === null) {
            $this->modeloPosiciones->registrarResultado($localId, 'empatado');
            $this->modeloPosiciones->registrarResultado($visitanteId, 'empatado');
        } else {
            $perdedorId = ($ganadorId === $localId) ? $visitanteId : $localId;
            $this->modeloPosiciones->registrarResultado($ganadorId, 'ganado');
            $this->modeloPosiciones->registrarResultado($perdedorId, 'perdido');
        }
        $this->modeloPosiciones->recalcularPosiciones((int) $enf['torneo_id']);

        $this->auditoria->registrar(
            $actorId, 'CARGA_RESULTADO', 'enfrentamientos', $enfrentamientoId,
            "Resultado {$puntajeLocal} - {$puntajeVisitante}"
        );

        // Liga: cuando no queda ningún partido sin resultado, el torneo se cierra solo.
        if ($enf['formato'] === 'liga'
            && $this->modeloEnfrentamiento->contarPendientesTorneo((int) $enf['torneo_id']) === 0) {
            $tabla     = $this->modeloPosiciones->listarPorTorneo((int) $enf['torneo_id']);
            $campeonId = (int) $tabla[0]['inscripcion_id'];
            (new Torneo())->finalizar((int) $enf['torneo_id'], $campeonId);
            $this->auditoria->registrar(
                $actorId, 'FINALIZACION_TORNEO', 'torneos', (int) $enf['torneo_id'],
                "Campeón: inscripción #{$campeonId}"
            );
        }

        return [];
    }

    /**
     * Genera la ronda 1 del sistema suizo. Cantidad de rondas: ceil(log2(n)).
     * En la ronda 1 nadie tiene puntos, así que se mezclan al azar.
     *
     * @return string[] Lista de errores. Vacía si se generó bien.
     */
    public function generarSuizo(int $torneoId, int $actorId): array
    {
        if ($this->modeloEnfrentamiento->contarRondas($torneoId) > 0) {
            return ['Este torneo ya tiene un fixture generado.'];
        }

        $inscripciones = array_map('intval', $this->modeloEnfrentamiento->listarInscripcionesActivas($torneoId));
        $cantidad = count($inscripciones);

        if ($cantidad < 2) {
            return ['Hacen falta al menos 2 inscriptos para generar un sistema suizo.'];
        }

        foreach ($inscripciones as $id) {
            $this->modeloPosiciones->inicializar($id);
        }

        shuffle($inscripciones);
        $totalRondas = $this->totalRondasSuizo($cantidad);
        $this->crearRondaSuiza($torneoId, 1, $totalRondas, $inscripciones);

        $this->auditoria->registrar(
            $actorId, 'GENERACION_FIXTURE', 'torneos', $torneoId,
            "Sistema suizo generado: {$cantidad} inscriptos, {$totalRondas} rondas (ronda 1 armada)"
        );

        return [];
    }

    /**
     * Cuando la ronda actual está completa, arma la siguiente emparejando por
     * puntos (sin repetir rival). Si era la última ronda, cierra el torneo con
     * el primero de la tabla como campeón.
     *
     * @return string[] Lista de errores. Vacía si avanzó bien.
     */
    public function avanzarRondaSuizo(int $torneoId, int $actorId): array
    {
        $ultima = $this->modeloEnfrentamiento->obtenerUltimaRonda($torneoId);
        if ($ultima === 0) {
            return ['Todavía no se generó el fixture de este torneo.'];
        }

        $ronda = $this->modeloEnfrentamiento->obtenerRondaPorNumero($torneoId, $ultima);
        if (!$this->modeloEnfrentamiento->rondaCompleta((int) $ronda['id'])) {
            return ['Todavía hay partidos de la ronda actual sin resultado cargado.'];
        }

        $inscripciones = array_map('intval', $this->modeloEnfrentamiento->listarInscripcionesActivas($torneoId));
        $totalRondas   = $this->totalRondasSuizo(count($inscripciones));

        if ($ultima >= $totalRondas) {
            $tabla = $this->modeloPosiciones->listarPorTorneo($torneoId);
            $campeonId = (int) $tabla[0]['inscripcion_id'];

            require_once __DIR__ . '/../Models/Torneo.php';
            (new Torneo())->finalizar($torneoId, $campeonId);
            $this->auditoria->registrar(
                $actorId, 'FINALIZACION_TORNEO', 'torneos', $torneoId,
                "Campeón: inscripción #{$campeonId}"
            );
            return [];
        }

        // Orden actual de la tabla (puntos, victorias, nombre), solo inscriptos activos.
        $ordenados = [];
        foreach ($this->modeloPosiciones->listarPorTorneo($torneoId) as $fila) {
            if (in_array((int) $fila['inscripcion_id'], $inscripciones, true)) {
                $ordenados[] = (int) $fila['inscripcion_id'];
            }
        }

        $this->crearRondaSuiza($torneoId, $ultima + 1, $totalRondas, $ordenados);

        $this->auditoria->registrar(
            $actorId, 'GENERACION_FIXTURE', 'torneos', $torneoId,
            "Ronda suiza " . ($ultima + 1) . " de {$totalRondas} generada"
        );

        return [];
    }

    private function totalRondasSuizo(int $cantidad): int
    {
        return max(1, (int) ceil(log($cantidad, 2)));
    }

    /** Empareja una lista ya ordenada y crea ronda + enfrentamientos (+ bye si es impar). */
    private function crearRondaSuiza(int $torneoId, int $numero, int $totalRondas, array $ordenados): void
    {
        $yaJugaron = [];
        $tuvoBye   = [];
        foreach ($this->modeloEnfrentamiento->listarCrucesPrevios($torneoId) as $cruce) {
            if ($cruce['visitante_id'] === null) {
                $tuvoBye[(int) $cruce['local_id']] = true;
            } else {
                $yaJugaron[$this->clavePar((int) $cruce['local_id'], (int) $cruce['visitante_id'])] = true;
            }
        }

        $bye = null;
        $parejas = null;

        if (count($ordenados) % 2 === 0) {
            $parejas = $this->emparejarSuizo($ordenados, $yaJugaron);
        } else {
            // El bye le toca al de abajo de la tabla que todavía no descansó,
            // siempre que el resto se pueda emparejar sin repetir rival.
            foreach (array_reverse($ordenados) as $candidato) {
                if (isset($tuvoBye[$candidato])) {
                    continue;
                }
                $resto = array_values(array_diff($ordenados, [$candidato]));
                $posibles = $this->emparejarSuizo($resto, $yaJugaron);
                if ($posibles !== null) {
                    $bye = $candidato;
                    $parejas = $posibles;
                    break;
                }
            }
        }

        // Plan B: si no hay forma de evitar repetir rival (pasa en torneos
        // chicos), se permite revancha antes que dejar el torneo trabado.
        if ($parejas === null) {
            if (count($ordenados) % 2 !== 0) {
                $bye = end($ordenados);
                $resto = array_slice($ordenados, 0, -1);
            } else {
                $resto = $ordenados;
            }
            $parejas = $this->emparejarSuizo($resto, []);
        }

        $rondaId = $this->modeloEnfrentamiento->crearRonda($torneoId, $numero, "Ronda {$numero} de {$totalRondas}");

        foreach ($parejas as [$local, $visitante]) {
            $this->modeloEnfrentamiento->crearEnfrentamiento($rondaId, $local, $visitante);
        }

        if ($bye !== null) {
            $this->modeloEnfrentamiento->crearBye($rondaId, $bye);
            $this->modeloPosiciones->registrarResultado($bye, 'ganado');
            $this->modeloPosiciones->recalcularPosiciones($torneoId);
        }
    }

    /**
     * Recorre la lista de arriba hacia abajo: el primero se empareja con el
     * mejor ubicado que todavía no enfrentó; si el resto queda sin solución,
     * prueba con el siguiente (backtracking). null = no hay emparejamiento posible.
     */
    private function emparejarSuizo(array $ordenados, array $yaJugaron): ?array
    {
        if (count($ordenados) === 0) {
            return [];
        }

        $primero = array_shift($ordenados);

        foreach ($ordenados as $i => $candidato) {
            if (isset($yaJugaron[$this->clavePar($primero, $candidato)])) {
                continue;
            }
            $resto = $ordenados;
            unset($resto[$i]);

            $parejas = $this->emparejarSuizo(array_values($resto), $yaJugaron);
            if ($parejas !== null) {
                array_unshift($parejas, [$primero, $candidato]);
                return $parejas;
            }
        }

        return null;
    }

    private function clavePar(int $a, int $b): string
    {
        return min($a, $b) . '-' . max($a, $b);
    }

    /**
     * Genera el fixture según el formato del torneo y lo pasa a "en curso".
     *
     * @return string[] Lista de errores. Vacía si se generó bien.
     */
    public function generarFixture(int $torneoId, int $actorId): array
    {
        $modeloTorneo = new Torneo();
        $torneo = $modeloTorneo->obtenerPorId($torneoId);
        if (!$torneo) {
            return ['El torneo no existe.'];
        }
        if ($torneo['estado'] !== 'inscripciones_abiertas') {
            return ['Este torneo ya no está en etapa de inscripciones.'];
        }

        $errores = match ($torneo['formato']) {
            'liga'        => $this->generarLiga($torneoId, $actorId),
            'eliminacion' => $this->generarEliminacionDirecta($torneoId, $actorId),
            'suizo'       => $this->generarSuizo($torneoId, $actorId),
            default       => ['Formato de torneo desconocido.'],
        };

        if (empty($errores)) {
            $modeloTorneo->iniciar($torneoId);
        }
        return $errores;
    }

    /**
     * Avanza de ronda en eliminación directa o sistema suizo. La liga no
     * avanza: se cierra sola al cargar el último resultado.
     *
     * @return string[] Lista de errores. Vacía si avanzó bien.
     */
    public function avanzarRonda(int $torneoId, int $actorId): array
    {
        $torneo = (new Torneo())->obtenerPorId($torneoId);
        if (!$torneo) {
            return ['El torneo no existe.'];
        }
        if ($torneo['estado'] !== 'en_curso') {
            return ['El torneo no está en curso.'];
        }

        return match ($torneo['formato']) {
            'eliminacion' => $this->avanzarRondaEliminacion($torneoId, $actorId),
            'suizo'       => $this->avanzarRondaSuizo($torneoId, $actorId),
            default       => ['La liga no necesita avanzar de ronda: se cierra sola al cargar todos los resultados.'],
        };
    }
}
