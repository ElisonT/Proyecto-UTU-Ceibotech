<?php
/**
 * gestionar-torneo.php
 *
 * Recibe los formularios de gestión que se muestran en detalle.php:
 * generar fixture, avanzar de ronda, cargar y corregir un resultado, y (solo el
 * Administrador general) autorizar al organizador a corregir; también inscribir o retirar
 * participantes y equipos mientras las inscripciones siguen abiertas (letra 5.2). Solo puede usarlo
 * quien gestiona ese torneo (Administrador general, u Organizador al que se lo
 * asignaron); corregir exige además tener la autorización (letra 5.2).
 * Siempre vuelve a detalle.php con un aviso.
 */
session_start();
require_once __DIR__ . '/app/Helpers/manejador_errores.php';
require_once __DIR__ . '/app/Helpers/roles.php';
require_once __DIR__ . '/app/Helpers/csrf.php';
require_once __DIR__ . '/app/Models/Torneo.php';
require_once __DIR__ . '/app/Models/Enfrentamiento.php';
require_once __DIR__ . '/app/Controllers/CompetenciaController.php';
require_once __DIR__ . '/app/Controllers/TorneoController.php';
require_once __DIR__ . '/app/Controllers/InscripcionController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: busqueda.php');
    exit;
}

if (!csrfValido()) {
    $_SESSION['flash_torneo'] = ['ok' => false, 'mensajes' => ['La página quedó desactualizada. Recargala e intentá de nuevo.']];
    header('Location: ' . (isset($_POST['torneo_id']) ? 'detalle.php?id=' . (int) $_POST['torneo_id'] : 'index.php'));
    exit;
}

$torneoId = (int) ($_POST['torneo_id'] ?? 0);
$torneo   = $torneoId > 0 ? (new Torneo())->obtenerPorId($torneoId) : false;

if (!$torneo || !puedeGestionarTorneo($torneo)) {
    header('Location: index.php');
    exit;
}

$actorId     = (int) $_SESSION['usuario_id'];
$competencia = new CompetenciaController();
$accion      = $_POST['accion'] ?? '';
$errores     = [];
$exito       = '';

switch ($accion) {
    case 'generar_fixture':
        $errores = $competencia->generarFixture($torneoId, $actorId);
        $exito   = 'Fixture generado. El torneo está en curso.';
        break;

    case 'avanzar_ronda':
        $errores = $competencia->avanzarRonda($torneoId, $actorId);
        $exito   = 'Listo, se avanzó de ronda.';
        break;

    case 'cargar_resultado':
    case 'corregir_resultado':
        $enfrentamientoId = (int) ($_POST['enfrentamiento_id'] ?? 0);
        $local     = filter_var($_POST['puntaje_local'] ?? '', FILTER_VALIDATE_INT);
        $visitante = filter_var($_POST['puntaje_visitante'] ?? '', FILTER_VALIDATE_INT);

        $enf = (new Enfrentamiento())->obtenerConFormato($enfrentamientoId);
        if ($accion === 'corregir_resultado' && !puedeCorregirResultados($torneo)) {
            $errores = ['No tenés autorización para corregir resultados en este torneo. Pedísela al administrador.'];
        } elseif (!$enf || (int) $enf['torneo_id'] !== $torneoId) {
            $errores = ['Ese enfrentamiento no pertenece a este torneo.'];
        } elseif ($local === false || $visitante === false) {
            $errores = ['Los puntajes tienen que ser números enteros.'];
        } else {
            if ($accion === 'corregir_resultado') {
                $errores = $competencia->corregirResultado($enfrentamientoId, $local, $visitante, $actorId, resultadoQuedaValidado());
                $exito   = 'Resultado corregido.';
            } else {
                $errores = $competencia->cargarResultado($enfrentamientoId, $local, $visitante, $actorId, resultadoQuedaValidado());
                $exito   = resultadoQuedaValidado() ? 'Resultado guardado.' : 'Resultado guardado: queda pendiente de validación del administrador.';
            }
        }
        break;

    case 'editar_torneo':
        $errores = (new TorneoController())->editarTorneo($torneo, $_POST, $actorId);
        $exito   = 'Torneo actualizado.';
        break;

    case 'cancelar_torneo':
        $errores = (new TorneoController())->cancelarTorneo($torneo, (string) ($_POST['motivo'] ?? ''), $actorId);
        $exito   = 'El torneo fue cancelado.';
        break;

    case 'validar_resultado':
    case 'validar_pendientes':
        if (!esAdministrador()) {
            $errores = ['Solo el administrador puede validar resultados.'];
            break;
        }
        if ($accion === 'validar_resultado') {
            $enf = (new Enfrentamiento())->obtenerConFormato((int) ($_POST['enfrentamiento_id'] ?? 0));
            $errores = ($enf && (int) $enf['torneo_id'] === $torneoId)
                ? $competencia->validarResultado((int) $enf['id'], $actorId)
                : ['El enfrentamiento no pertenece a este torneo.'];
            $exito = 'Resultado validado.';
        } else {
            $r       = $competencia->validarPendientes($torneoId, $actorId);
            $errores = $r['errores'];
            $exito   = $r['validados'] === 1 ? 'Se validó 1 resultado.' : "Se validaron {$r['validados']} resultados.";
        }
        break;

    case 'inscribir_persona':
        $errores = (new InscripcionController())->inscribirPersonaPorOrganizador($torneoId, (string) ($_POST['nombre_usuario'] ?? ''), $actorId);
        $exito   = 'Participante inscripto al torneo.';
        break;

    case 'inscribir_equipo_org':
        $errores = (new InscripcionController())->inscribirEquipoPorOrganizador($torneoId, (int) ($_POST['equipo_id'] ?? 0), $actorId);
        $exito   = 'Equipo inscripto al torneo.';
        break;

    case 'retirar_inscripcion':
        $errores = (new InscripcionController())->retirarPorOrganizador($torneoId, (int) ($_POST['inscripcion_id'] ?? 0), $actorId);
        $exito   = 'Inscripción retirada.';
        break;

    case 'autorizar_correccion':
    case 'revocar_correccion':
        if (!esAdministrador()) {
            $errores = ['Solo el administrador puede cambiar esta autorización.'];
            break;
        }
        $autorizar = $accion === 'autorizar_correccion';
        $errores   = (new TorneoController())->cambiarAutorizacionCorreccion($torneo, $autorizar, $actorId);
        $exito     = $autorizar
            ? 'Listo: el organizador ahora puede corregir resultados de este torneo.'
            : 'Listo: el organizador ya no puede corregir resultados de este torneo.';
        break;

    default:
        $errores = ['Acción desconocida.'];
}

$_SESSION['flash_torneo'] = empty($errores)
    ? ['ok' => true,  'mensajes' => [$exito]]
    : ['ok' => false, 'mensajes' => $errores];

header('Location: detalle.php?id=' . $torneoId);
exit;
