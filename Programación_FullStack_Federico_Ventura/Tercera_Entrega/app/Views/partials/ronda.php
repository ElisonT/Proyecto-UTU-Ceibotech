<?php
/**
 * app/Views/partials/ronda.php
 *
 * Dibuja una ronda con sus enfrentamientos. Variables que espera:
 *   $ronda          fila de Enfrentamiento::listarRondasConEnfrentamientos()
 *   $torneoId       id del torneo
 *   $puedeGestionar true si hay que mostrar el formulario de carga de resultados
 */
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
<div class="fecha-grupo" style="margin-top: 1.25rem;">
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
        <div class="partido">
          <div class="partido-equipo partido-local"><?= escapar($enf['nombre_local']) ?></div>
          <div class="partido-resultado">
            <span class="resultado-num"><?= (int) $enf['puntaje_local'] ?></span>
            <span class="resultado-sep">—</span>
            <span class="resultado-num"><?= (int) $enf['puntaje_visitante'] ?></span>
          </div>
          <div class="partido-equipo partido-visitante"><?= escapar($enf['nombre_visitante']) ?></div>
        </div>
      <?php else: ?>
        <div class="partido partido-pendiente">
          <div class="partido-equipo partido-local"><?= escapar($enf['nombre_local']) ?></div>
          <div class="partido-resultado partido-resultado-pendiente">
            <?php if ($puedeGestionar): ?>
              <form method="post" action="gestionar-torneo.php" class="partido-form">
                <input type="hidden" name="accion" value="cargar_resultado" />
                <input type="hidden" name="torneo_id" value="<?= (int) $torneoId ?>" />
                <input type="hidden" name="enfrentamiento_id" value="<?= (int) $enf['id'] ?>" />
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
