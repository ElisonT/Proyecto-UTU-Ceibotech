<?php
/**
 * app/Controllers/TorneoController.php
 *
 * Por ahora solo maneja la creación mínima de un torneo (nombre, deporte,
 * formato, fecha de inicio). El resto del formulario de crear-torneo.php
 * (premio, reglas, visibilidad, participantes) se conecta más adelante,
 * cuando se amplíe el modelo de datos con esas entidades.
 */

require_once __DIR__ . '/../Models/Torneo.php';
require_once __DIR__ . '/../Models/Auditoria.php';
require_once __DIR__ . '/../Models/Deporte.php';
require_once __DIR__ . '/../Models/Configuracion.php';

class TorneoController
{
    private Torneo $modeloTorneo;
    private Auditoria $auditoria;
    private Deporte $modeloDeporte;
    private Configuracion $config;

    public function __construct()
    {
        $this->config = new Configuracion();
        $this->modeloTorneo = new Torneo();
        $this->auditoria    = new Auditoria();
        $this->modeloDeporte = new Deporte();
    }

    /**
     * Procesa el formulario de "Crear torneo" (solo los campos mínimos).
     * @return array{0: string[], 1: int|null} [errores, idDelTorneoCreado]
     */
    public function procesarCreacion(array $datos, int $creadoPor, array $archivos = []): array
    {
        $errores = [];

        $nombre        = trim($datos['nombre'] ?? '');
        $deporteCodigo = $datos['deporte'] ?? '';
        $formato       = $datos['tipo'] ?? '';
        $fechaInicio   = trim($datos['fecha_inicio_torneo'] ?? '');

        if (strlen($nombre) < 3) {
            $errores[] = 'El nombre del torneo debe tener al menos 3 caracteres.';
        }
        // El deporte sale del catálogo (tabla deportes). Él define la modalidad
        // del torneo y el tamaño de los equipos: no se eligen a mano.
        $deporteFila = $this->modeloDeporte->obtenerPorCodigo((string) $deporteCodigo);
        if (!$deporteFila) {
            $errores[] = 'Seleccioná un deporte válido.';
        }
        if (!in_array($formato, Configuracion::FORMATOS, true)) {
            $errores[] = 'Seleccioná un tipo de torneo válido.';
        } elseif (!in_array($formato, $this->config->formatosHabilitados(), true)) {
            // El administrador puede deshabilitar un módulo (letra 5.1): no se crean torneos nuevos con ese formato.
            $errores[] = 'Ese tipo de torneo está deshabilitado por el administrador: elegí otro.';
        }

        // --- Fechas: inscripciones (apertura y cierre), inicio y fin del torneo ---
        $esFecha = static fn (string $f): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1
            && checkdate((int) substr($f, 5, 2), (int) substr($f, 8, 2), (int) substr($f, 0, 4));
        $hoy       = (new DateTimeImmutable('now', new DateTimeZone('America/Montevideo')))->format('Y-m-d');
        $apertura  = trim($datos['fecha_inicio_inscripcion'] ?? '');
        $cierre    = trim($datos['fecha_cierre_inscripcion'] ?? '');
        $fechaFin  = trim($datos['fecha_fin_torneo'] ?? '');

        if (!$esFecha($apertura)) {
            $errores[] = 'Ingresá una fecha válida para el inicio de las inscripciones.';
        } elseif ($apertura < $hoy) {
            $errores[] = 'El inicio de las inscripciones no puede ser una fecha pasada.';
        }
        if (!$esFecha($cierre)) {
            $errores[] = 'Ingresá una fecha válida para el cierre de las inscripciones.';
        } elseif ($esFecha($apertura) && $cierre < $apertura) {
            $errores[] = 'El cierre de las inscripciones no puede ser anterior a su inicio.';
        }
        if (!$esFecha($fechaInicio)) {
            $errores[] = 'Ingresá una fecha de inicio válida.';
        } elseif ($esFecha($cierre) && $fechaInicio < $cierre) {
            $errores[] = 'El torneo no puede empezar antes del cierre de las inscripciones.';
        }
        if (!$esFecha($fechaFin)) {
            $errores[] = 'Ingresá una fecha válida para el fin del torneo.';
        } elseif ($esFecha($fechaInicio) && $fechaFin < $fechaInicio) {
            $errores[] = 'La fecha de fin del torneo no puede ser anterior a su inicio.';
        }

        $maxCupos = $this->config->maxParticipantes();   // lo define el administrador en Configuración
        $cupos = filter_var($datos['max_participantes'] ?? '', FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 2, 'max_range' => $maxCupos]]);
        if ($cupos === false) {
            $errores[] = "El máximo de participantes tiene que ser un número entre 2 y {$maxCupos}.";
        }

        // Nombre del deporte y tamaño de equipo: salen del catálogo, salvo "Otro" (libre),
        // donde el administrador escribe el nombre y cuántos jugadores lleva cada equipo.
        $deporte            = $deporteFila ? $deporteFila['nombre'] : '';
        $jugadoresPorEquipo = $deporteFila ? (int) $deporteFila['jugadores_por_equipo'] : 1;

        if ($deporteFila && (int) $deporteFila['es_libre'] === 1) {
            $deporte = trim(preg_replace('/\s+/', ' ', (string) ($datos['deporte_otro'] ?? '')));
            $largo   = mb_strlen($deporte);
            if ($largo < 3 || $largo > 60) {
                $errores[] = 'Escribí el nombre del deporte o juego (entre 3 y 60 caracteres).';
            } elseif ($this->modeloDeporte->existeNombre($deporte)) {
                $errores[] = "\"{$deporte}\" ya está en la lista de deportes: elegilo ahí en lugar de \"Otro\".";
            }

            $jugadoresPorEquipo = filter_var($datos['jugadores_otro'] ?? '', FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($jugadoresPorEquipo === false) {
                $errores[] = 'Indicá cuántos jugadores lleva cada equipo (1 si es individual, hasta 50).';
                $jugadoresPorEquipo = 1;
            }
        }

        // Más de 1 jugador por equipo => se juega por equipos; si no, individual.
        $modalidad = $jugadoresPorEquipo >= 2 ? 'equipo' : 'individual';

        // Pasos "Premio", "Reglas" y "Visibilidad" del asistente.
        [$erroresExtras, $extras] = $this->validarExtras($datos, $archivos, true);
        $errores = array_merge($errores, $erroresExtras);

        if (!empty($errores)) {
            return [$errores, null];
        }

        $id = $this->modeloTorneo->crear($nombre, $deporte, $formato, $fechaInicio, $creadoPor, $modalidad, $cupos, $jugadoresPorEquipo, $apertura, $cierre, $fechaFin, $extras);

        if ($id === false) {
            return [['No se pudo crear el torneo. Probá de nuevo.'], null];
        }

        $this->auditoria->registrar($creadoPor, 'CREACION_TORNEO', 'torneos', $id, "Torneo '{$nombre}' ({$deporte}, {$formato})");

        return [[], $id];
    }

    /** Tamaño máximo del PDF de reglas (cabe en el límite de subida por defecto de PHP). */
    public const MAX_PDF_BYTES = 2 * 1024 * 1024;

    /**
     * Valida y normaliza los pasos "Premio", "Reglas" y "Visibilidad" del formulario.
     *  - Premio: ninguno | monetario (monto en USD, opcional) | otro (descripción obligatoria).
     *  - Reglas: texto de hasta 5000 caracteres o un PDF de hasta 2 MB; si hay PDF se usa ese y se ignora el texto.
     *  - Visibilidad: publico | privado.
     * $permitirPdf es false al editar (el PDF solo se sube al crear el torneo).
     *
     * @return array{0: string[], 1: array} [errores, valores listos para guardar]
     */
    private function validarExtras(array $datos, array $archivos, bool $permitirPdf): array
    {
        $errores = [];

        // --- Premio ---
        $tipoPremio = (string) ($datos['tipo_premio'] ?? 'ninguno');
        $monto      = null;
        $descPremio = null;
        if (!in_array($tipoPremio, ['ninguno', 'monetario', 'otro'], true)) {
            $errores[] = 'Elegí un tipo de premio válido.';
            $tipoPremio = 'ninguno';
        } elseif ($tipoPremio === 'monetario') {
            $crudo = trim((string) ($datos['monto_premio'] ?? ''));
            if ($crudo !== '') {
                $valor = filter_var($crudo, FILTER_VALIDATE_FLOAT);
                if ($valor === false || $valor <= 0 || $valor > 1000000) {
                    $errores[] = 'El monto del premio tiene que ser un número mayor a 0 y de hasta 1.000.000 USD.';
                } else {
                    $monto = round($valor, 2);
                }
            }
        } elseif ($tipoPremio === 'otro') {
            $descPremio = trim(preg_replace('/\s+/', ' ', (string) ($datos['desc_premio'] ?? '')));
            $largo      = mb_strlen($descPremio);
            if ($largo < 3 || $largo > 300) {
                $errores[] = 'Describí el premio (entre 3 y 300 caracteres).';
            }
        }

        // --- Reglas ---
        $texto = trim((string) ($datos['reglas_texto'] ?? ''));
        if (mb_strlen($texto) > 5000) {
            $errores[] = 'Las reglas escritas no pueden pasar de 5000 caracteres.';
        }
        $pdf = null;
        if ($permitirPdf) {
            [$erroresPdf, $pdf] = $this->validarPdfReglas($archivos['reglas_pdf'] ?? null);
            $errores = array_merge($errores, $erroresPdf);
        }
        if ($pdf !== null) {
            $texto = '';        // con PDF se usa el PDF y se ignora el texto (como avisa el formulario)
        }

        // --- Visibilidad ---
        $visibilidad = (string) ($datos['visibilidad'] ?? '');
        if (!in_array($visibilidad, ['publico', 'privado'], true)) {
            $errores[] = 'Elegí quién puede ver el torneo (público o privado).';
            $visibilidad = 'publico';
        }

        return [$errores, [
            'tipo_premio'  => $tipoPremio,
            'monto_premio' => $monto,
            'desc_premio'  => $descPremio,
            'reglas_texto' => $texto !== '' ? $texto : null,
            'visibilidad'  => $visibilidad,
            'pdf'          => $pdf,
        ]];
    }

    /**
     * Revisa el PDF de reglas subido ($_FILES['reglas_pdf']). No se confía en el nombre ni en el tipo que
     * manda el navegador: se mira el tamaño, el contenido real (finfo) y la firma "%PDF-" del archivo.
     *
     * @return array{0: string[], 1: ?array{nombre: string, tamano: int, contenido: string}}
     */
    private function validarPdfReglas(?array $archivo): array
    {
        $codigo = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($archivo === null || $codigo === UPLOAD_ERR_NO_FILE) {
            return [[], null];                       // no subió nada: es válido
        }
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return [['El PDF de reglas supera el tamaño máximo (2 MB).'], null];
        }
        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file((string) $archivo['tmp_name'])) {
            return [['No se pudo subir el PDF de reglas. Probá de nuevo.'], null];
        }
        $tamano = (int) $archivo['size'];
        if ($tamano <= 0 || $tamano > self::MAX_PDF_BYTES) {
            return [['El PDF de reglas tiene que pesar más de 0 y hasta 2 MB.'], null];
        }
        $contenido = (string) file_get_contents($archivo['tmp_name']);
        $mime      = (new finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        if ($mime !== 'application/pdf' || strncmp($contenido, '%PDF-', 5) !== 0
            || strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            return [['El archivo de reglas tiene que ser un PDF válido.'], null];
        }
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo((string) $archivo['name'], PATHINFO_FILENAME));
        $nombre = trim(mb_substr($nombre, 0, 100), '-.') ?: 'reglas';

        return [[], ['nombre' => $nombre . '.pdf', 'tamano' => $tamano, 'contenido' => $contenido]];
    }

    /**
     * Asigna (o quita) el organizador de un torneo. $organizadorId puede ser
     * null para dejarlo "sin asignar" de nuevo.
     */
    public function procesarAsignacion(int $torneoId, ?int $organizadorId, int $actorId): bool
    {
        $exito = $this->modeloTorneo->asignarOrganizador($torneoId, $organizadorId);

        if ($exito) {
            $detalle = $organizadorId
                ? "Torneo #{$torneoId} asignado al organizador #{$organizadorId}"
                : "Torneo #{$torneoId} quedó sin organizador asignado";
            $this->auditoria->registrar($actorId, 'ASIGNACION_ORGANIZADOR', 'torneos', $torneoId, $detalle);
        }

        return $exito;
    }

    /**
     * El Administrador general habilita o quita, torneo por torneo, la autorización para
     * que el organizador asignado corrija resultados (letra 5.2). Queda en auditoría.
     * Solo tiene sentido si el torneo ya tiene organizador asignado.
     *
     * @return string[] errores (vacía si se guardó)
     */
    public function cambiarAutorizacionCorreccion(array $torneo, bool $autorizar, int $actorId): array
    {
        if (empty($torneo['organizador_asignado_id'])) {
            return ['Primero asigná un organizador a este torneo.'];
        }
        if ((int) $torneo['correccion_autorizada'] === ($autorizar ? 1 : 0)) {
            return [$autorizar ? 'La corrección ya estaba autorizada.' : 'La corrección ya estaba sin autorizar.'];
        }
        if (!$this->modeloTorneo->establecerAutorizacionCorreccion((int) $torneo['id'], $autorizar)) {
            return ['No se pudo guardar el cambio. Probá de nuevo.'];
        }

        $detalle = $autorizar
            ? "Se autorizó al organizador #{$torneo['organizador_asignado_id']} a corregir resultados del torneo #{$torneo['id']}"
            : "Se quitó al organizador #{$torneo['organizador_asignado_id']} la autorización para corregir resultados del torneo #{$torneo['id']}";
        $this->auditoria->registrar($actorId, $autorizar ? 'AUTORIZACION_CORRECCION' : 'REVOCACION_CORRECCION',
            'torneos', (int) $torneo['id'], $detalle);
        return [];
    }

    /**
     * Quien gestiona el torneo edita sus datos. Mientras están abiertas las inscripciones se puede cambiar
     * nombre, cupos y las cuatro fechas; con el torneo en curso solo el nombre y las fechas de inicio y fin
     * (las inscripciones ya se cerraron). Deporte, formato y modalidad no se tocan: cambiarlos rompería lo
     * ya inscripto y el fixture.
     *
     * @return string[] errores (vacía si se guardó)
     */
    public function editarTorneo(array $torneo, array $datos, int $actorId): array
    {
        $estado = $torneo['estado'];
        if (!in_array($estado, ['inscripciones_abiertas', 'en_curso'], true)) {
            return ['Este torneo ya no se puede editar.'];
        }
        $abierto = $estado === 'inscripciones_abiertas';
        $errores = [];

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
            $errores[] = 'El nombre del torneo debe tener entre 3 y 120 caracteres.';
        }

        $esFecha = static fn (string $f): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1
            && checkdate((int) substr($f, 5, 2), (int) substr($f, 8, 2), (int) substr($f, 0, 4));
        $leer = static fn (string $campo, ?string $actual): string => trim((string) ($datos[$campo] ?? $actual ?? ''));

        // Con el torneo en curso las fechas de inscripción quedan como estaban.
        $apertura = $abierto ? $leer('fecha_inicio_inscripcion', null) : (string) $torneo['fecha_apertura_inscripcion'];
        $cierre   = $abierto ? $leer('fecha_cierre_inscripcion', null)  : (string) $torneo['fecha_cierre_inscripcion'];
        $inicio   = $leer('fecha_inicio_torneo', null);
        $fin      = $leer('fecha_fin_torneo', null);

        $nombresFecha = ['apertura' => 'inicio de las inscripciones', 'cierre' => 'cierre de las inscripciones', 'inicio' => 'inicio del torneo', 'fin' => 'fin del torneo'];
        foreach (['apertura' => $apertura, 'cierre' => $cierre, 'inicio' => $inicio, 'fin' => $fin] as $clave => $valor) {
            if (!$esFecha($valor)) {
                $errores[] = "Ingresá una fecha válida para el {$nombresFecha[$clave]}.";
            }
        }
        if (empty($errores)) {
            if ($cierre < $apertura) {
                $errores[] = 'El cierre de las inscripciones no puede ser anterior a su inicio.';
            }
            if ($inicio < $cierre) {
                $errores[] = 'El torneo no puede empezar antes del cierre de las inscripciones.';
            }
            if ($fin < $inicio) {
                $errores[] = 'La fecha de fin del torneo no puede ser anterior a su inicio.';
            }
        }

        // Cupos: solo se cambian mientras hay inscripciones abiertas, y nunca por debajo de los ya inscriptos.
        $cupos = $torneo['cupos'] === null ? null : (int) $torneo['cupos'];
        if ($abierto) {
            $crudo = trim((string) ($datos['max_participantes'] ?? ''));
            if ($crudo === '' && $torneo['cupos'] === null) {
                $cupos = null;                       // sigue sin límite
            } else {
                $maximo   = $this->config->maxParticipantes();
                $anterior = $cupos;
                $cupos    = filter_var($crudo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);
                if ($cupos === false) {
                    $errores[] = 'El máximo de participantes tiene que ser un número de 2 o más.';
                } elseif ($cupos > $maximo && $cupos !== $anterior) {
                    $errores[] = "El máximo de participantes no puede superar {$maximo}.";
                } else {
                    $inscriptos = count($this->modeloTorneo->listarInscriptos((int) $torneo['id']));
                    if ($cupos < $inscriptos) {
                        $errores[] = "Ya hay {$inscriptos} inscriptos: el máximo no puede ser menor.";
                    }
                }
            }
        }
        // Premio, reglas escritas y visibilidad también se pueden corregir. Si las reglas están en PDF
        // (se suben solo al crear), el texto queda como estaba.
        // Lo que no venga en el envío conserva su valor actual (así un envío parcial no borra ni rechaza nada).
        foreach (['tipo_premio', 'monto_premio', 'desc_premio', 'reglas_texto', 'visibilidad'] as $campoExtra) {
            if (!array_key_exists($campoExtra, $datos)) {
                $datos[$campoExtra] = (string) ($torneo[$campoExtra] ?? '');
            }
        }
        [$erroresExtras, $extras] = $this->validarExtras($datos, [], false);
        $errores = array_merge($errores, $erroresExtras);
        if (!empty($torneo['tiene_reglas_pdf'])) {
            $extras['reglas_texto'] = $torneo['reglas_texto'];
        }
        if (!empty($errores)) {
            return $errores;
        }

        $nuevo = ['nombre' => $nombre, 'cupos' => $cupos, 'apertura' => $apertura, 'cierre' => $cierre, 'inicio' => $inicio, 'fin' => $fin,
                  'tipo_premio' => $extras['tipo_premio'], 'monto_premio' => $extras['monto_premio'], 'desc_premio' => $extras['desc_premio'],
                  'reglas_texto' => $extras['reglas_texto'], 'visibilidad' => $extras['visibilidad']];
        $viejo = ['nombre' => $torneo['nombre'], 'cupos' => $torneo['cupos'] === null ? null : (int) $torneo['cupos'],
                  'apertura' => $torneo['fecha_apertura_inscripcion'], 'cierre' => $torneo['fecha_cierre_inscripcion'],
                  'inicio' => $torneo['fecha_inicio'], 'fin' => $torneo['fecha_fin'],
                  'tipo_premio' => $torneo['tipo_premio'], 'monto_premio' => $torneo['monto_premio'] === null ? null : round((float) $torneo['monto_premio'], 2),
                  'desc_premio' => $torneo['desc_premio'], 'reglas_texto' => $torneo['reglas_texto'], 'visibilidad' => $torneo['visibilidad']];
        $cambios = [];
        foreach ($nuevo as $campo => $valor) {
            if ($valor !== $viejo[$campo]) {
                // Las reglas pueden ser largas: en la auditoría solo se anota que cambiaron.
                $cambios[] = $campo === 'reglas_texto'
                    ? 'reglas: texto modificado'
                    : "{$campo}: " . ($viejo[$campo] ?? '(sin valor)') . ' a ' . ($valor ?? '(sin valor)');
            }
        }
        if (empty($cambios)) {
            return ['No hay cambios para guardar.'];
        }

        if (!$this->modeloTorneo->actualizarDatos((int) $torneo['id'], $nuevo)) {
            return ['No se pudo guardar el cambio. Probá de nuevo.'];
        }
        $this->auditoria->registrar($actorId, 'EDICION_TORNEO', 'torneos', (int) $torneo['id'], implode('; ', $cambios));
        return [];
    }

    /**
     * Cancela un torneo que todavía no terminó. Queda como historia (con sus inscripciones y resultados)
     * pero no admite más movimientos. El motivo es opcional y queda en la auditoría.
     *
     * @return string[] errores (vacía si se canceló)
     */
    public function cancelarTorneo(array $torneo, string $motivo, int $actorId): array
    {
        if (!in_array($torneo['estado'], ['inscripciones_abiertas', 'en_curso'], true)) {
            return ['Solo se puede cancelar un torneo que todavía no terminó.'];
        }
        $motivo = trim(mb_substr(preg_replace('/\s+/', ' ', $motivo), 0, 200));
        if (!$this->modeloTorneo->cancelar((int) $torneo['id'])) {
            return ['No se pudo cancelar el torneo. Probá de nuevo.'];
        }
        $this->auditoria->registrar($actorId, 'CANCELACION_TORNEO', 'torneos', (int) $torneo['id'],
            "Torneo cancelado estando {$torneo['estado']}" . ($motivo !== '' ? ". Motivo: {$motivo}" : ''));
        return [];
    }
}
