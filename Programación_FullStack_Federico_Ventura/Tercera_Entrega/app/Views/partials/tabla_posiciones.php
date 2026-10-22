<?php
/**
 * app/Views/partials/tabla_posiciones.php
 *
 * Tabla de posiciones de un torneo. Variables que espera:
 *   $tabla   filas de TablaPosiciones::listarPorTorneo()
 *   $unidad  'equipos' | 'participantes'
 * La usan detalle.php y api/resultado.php (que la devuelve ya armada para
 * actualizar la tabla sin recargar la página).
 */
?>
<?php if (empty($tabla)): ?>
  <p class="tabla-sin-datos">Este torneo todavía no comenzó — la tabla se arma cuando se juegue la primera ronda.</p>
<?php else: ?>
  <div class="tabla-wrap">
    <table class="tabla-posiciones">
      <thead>
        <tr>
          <th class="tabla-pos">#</th>
          <th class="tabla-equipo"><?= $unidad === 'equipos' ? 'Equipo' : 'Participante' ?></th>
          <th>PJ</th>
          <th>G</th>
          <th>E</th>
          <th>P</th>
          <th title="Diferencia de tantos (a favor menos en contra)">DIF</th>
          <th class="tabla-pts">PTS</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tabla as $indice => $fila): $pos = $indice + 1; ?>
          <tr class="<?= $pos === 1 ? 'tabla-lider' : '' ?>">
            <td class="tabla-pos"><span class="pos-num<?= $pos <= 3 ? ' pos-' . $pos : '' ?>"><?= $pos ?></span></td>
            <td class="tabla-equipo"><?= escapar($fila['nombre']) ?></td>
            <td><?= (int) $fila['partidos_jugados'] ?></td>
            <td><?= (int) $fila['partidos_ganados'] ?></td>
            <td><?= (int) $fila['partidos_empatados'] ?></td>
            <td><?= (int) $fila['partidos_perdidos'] ?></td>
            <td><?= (int) $fila['diferencia'] > 0 ? '+' : '' ?><?= (int) $fila['diferencia'] ?></td>
            <td class="tabla-pts"><strong><?= (int) $fila['puntos'] ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="tabla-leyenda">
    <span class="leyenda-item"><span class="leyenda-color leyenda-lider"></span> Líder</span>
    <span class="leyenda-sep">·</span>
    <span class="tabla-hint">PJ jugados · G ganados · E empatados · P perdidos · DIF diferencia de tantos · PTS puntos. Desempate: enfrentamiento directo, diferencia, tantos a favor.</span>
  </div>
<?php endif; ?>
