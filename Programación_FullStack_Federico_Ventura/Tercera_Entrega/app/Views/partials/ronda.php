<?php
/**
 * app/Views/partials/ronda.php
 *
 * Dibuja una ronda con sus enfrentamientos. Variables que espera:
 *   $ronda          fila de Enfrentamiento::listarRondasConEnfrentamientos()
 *   $torneoId       id del torneo
 *   $puedeGestionar true si hay que mostrar los formularios de cargar y corregir resultados
 *
 * Los formularios funcionan solos (POST a gestionar-torneo.php); si hay JavaScript,
 * script.js los intercepta y los manda con fetch a api/resultado.php, que devuelve
 * esta misma partial ya actualizada.
 */
require_once __DIR__ . '/../../Helpers/csrf.php';

$jugados = 0;
$total   = 0;
foreach ($ronda['enfrentamientos'] as $enf) {
    if ($enf['inscripcion_visitante_id'] === null) {
        continue; // un bye no es un partido
    }
    $total++;
    if ((int) $enf['tiene_resultado'] === 1) {
        $jugados++;
    }
}
$completa = $jugados === $total;
?>
<div class="fecha-grupo" id="ronda-<?= (int) $ronda['id'] ?>" style="margin-top: 1.25rem;">
  <div class="fecha-titulo">
    <span><?= escapar($ronda['nombre'] ?: 'Ronda ' . $ronda['numero']) ?></span>
    <span class="badge <?= $completa ? 'estado-verde' : 'estado-naranja' ?>" style="margin-top:0;">
      <?= $completa ? 'Jugada' : 'Jugados ' . $jugados . ' de ' . $total ?>
    </span>
  </div>
  <div class="partidos-lista">
    <?php foreach ($ronda['enfrentamientos'] as $enf): ?>
      <?php if ($enf['inscripcion_visitante_id'] === null): ?>
        <div class="partido">
          <div class="partido-equipo partido-local"><?= escapar($enf['nombre_local']) ?></div>
          <div class="partido-resultado partido-resultado-pendiente"><span class="resultado-sep">descansa (bye)</span></div>
          <div class="partido-equipo partido-visitante">—</div>
        </div>
      <?php elseif ((int) $enf['tiene_resultado'] === 1): ?>
        <div class="partido-item">
          <div class="partido">
            <div class="partido-equipo partido-local"><?= escapar($enf['nombre_local']) ?></div>
            <div class="partido-resultado">
              <span class="resultado-num"><?= (int) $enf['puntaje_local'] ?></span>
              <span class="resultado-sep">—</span>
              <span class="resultado-num"><?= (int) $enf['puntaje_visitante'] ?></span>
            </div>
            <div class="partido-equipo partido-visitante"><?= escapar($enf['nombre_visitante']) ?></div>
          </div>
          <?php if (!empty($enf['fecha_correccion'])): ?>
            <span class="partido-corregido" title="Corregido el <?= escapar($enf['fecha_correccion']) ?>"><i class="fa-solid fa-pen-to-square"></i> corregido</span>
          <?php endif; ?>
          <?php if ($puedeGestionar): ?>
            <details class="partido-corregir">
              <summary><i class="fa-solid fa-pen"></i> Corregir</summary>
              <form method="post" action="gestionar-torneo.php" class="partido-form js-resultado-form" data-accion="corregir">
                <input type="hidden" name="accion" value="corregir_resultado" />
                <input type="hidden" name="torneo_id" value="<?= (int) $torneoId ?>" />
                <input type="hidden" name="enfrentamiento_id" value="<?= (int) $enf['id'] ?>" />
                <?= campoCsrf() ?>
                <input type="number" name="puntaje_local" min="0" required class="partido-input"
                       value="<?= (int) $enf['puntaje_local'] ?>" aria-label="Puntaje local" />
                <span class="resultado-sep">-</span>
                <input type="number" name="puntaje_visitante" min="0" required class="partido-input"
                       value="<?= (int) $enf['puntaje_visitante'] ?>" aria-label="Puntaje visitante" />
                <button type="submit" class="partido-guardar" aria-label="Guardar corrección"><i class="fa-solid fa-check"></i></button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="partido partido-pendiente">
          <div class="partido-equipo partido-local"><?= escapar($enf['nombre_local']) ?></div>
          <div class="partido-resultado partido-resultado-pendiente">
            <?php if ($puedeGestionar): ?>
              <form method="post" action="gestionar-torneo.php" class="partido-form js-resultado-form" data-accion="cargar">
                <input type="hidden" name="accion" value="cargar_resultado" />
                <input type="hidden" name="torneo_id" value="<?= (int) $torneoId ?>" />
                <input type="hidden" name="enfrentamiento_id" value="<?= (int) $enf['id'] ?>" />
                <?= campoCsrf() ?>
                <input type="number" name="puntaje_local" min="0" required class="partido-input" aria-label="Puntaje local" />
                <span class="resultado-sep">-</span>
                <input type="number" name="puntaje_visitante" min="0" required class="partido-input" aria-label="Puntaje visitante" />
                <button type="submit" class="partido-guardar" aria-label="Guardar resultado"><i class="fa-solid fa-check"></i></button>
              </form>
            <?php else: ?>
              <span class="resultado-sep">vs</span>
            <?php endif; ?>
          </div>
          <div class="partido-equipo partido-visitante"><?= escapar($enf['nombre_visitante']) ?></div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
