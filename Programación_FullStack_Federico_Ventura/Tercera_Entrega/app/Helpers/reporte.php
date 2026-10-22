<?php
/**
 * app/Helpers/reporte.php
 *
 * Arma la salida de los reportes (letra 5.1 y 5.2) a partir de una estructura simple:
 *
 *   [
 *     'titulo'    => 'Reporte de torneos',
 *     'subtitulo' => 'Estado: en curso',                 // opcional
 *     'secciones' => [
 *        ['titulo' => 'Torneos', 'columnas' => ['Id', 'Nombre'], 'filas' => [[1, 'Copa'], ...],
 *         'vacio'  => 'No hay torneos.']                  // 'vacio' es opcional
 *     ],
 *   ]
 *
 * Con la misma estructura se emiten dos formatos: HTML imprimible (reporte_html) y CSV (reporte_csv).
 */

require_once __DIR__ . '/texto.php';

/** Fecha y hora actual en Montevideo, para el encabezado de cada reporte. */
function reporteFechaGeneracion(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Montevideo')))->format('d/m/Y H:i');
}

/** Nombre de archivo seguro (sin tildes ni espacios) para la descarga del CSV. */
function reporteNombreArchivo(string $base): string
{
    $limpio = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $base) ?: $base));
    $limpio = trim((string) $limpio, '-');
    return ($limpio !== '' ? $limpio : 'reporte') . '-' . (new DateTimeImmutable('now', new DateTimeZone('America/Montevideo')))->format('Ymd-His');
}

/**
 * Protege una celda contra la "inyección de fórmulas" en Excel/Calc: un texto que empieza con
 * = + - @ (o tabulación / retorno) se interpretaría como fórmula al abrir el CSV. Como los nombres los
 * escriben los usuarios, se les antepone una comilla simple. Los números reales no se tocan.
 */
function reporteCeldaCsv(mixed $valor): string
{
    if ($valor === null) {
        return '';
    }
    $texto = (string) $valor;
    if ($texto !== '' && !is_numeric($texto) && strpbrk($texto[0], "=+-@\t\r") !== false) {
        return "'" . $texto;
    }
    return $texto;
}

/** Emite el reporte como CSV descargable: UTF-8 con BOM y separador ";" (así lo abre Excel en español). */
function reporte_csv(array $reporte, string $nombreArchivo): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    $escribir = static function (array $fila) use ($salida): void {
        fputcsv($salida, array_map('reporteCeldaCsv', $fila), ';', '"', '', "\r\n");
    };

    $escribir([$reporte['titulo']]);
    if (!empty($reporte['subtitulo'])) {
        $escribir([$reporte['subtitulo']]);
    }
    $escribir(['Generado el ' . reporteFechaGeneracion()]);

    foreach ($reporte['secciones'] as $seccion) {
        $escribir([]);
        $escribir([$seccion['titulo']]);
        $escribir($seccion['columnas']);
        if (empty($seccion['filas'])) {
            $escribir([$seccion['vacio'] ?? 'Sin datos.']);
            continue;
        }
        foreach ($seccion['filas'] as $fila) {
            $escribir($fila);
        }
    }
    fclose($salida);
}

/**
 * Emite el reporte como página HTML lista para imprimir (o guardar como PDF desde el navegador).
 * $volverA es el enlace del botón "Volver"; $csvUrl el del botón de descarga CSV.
 */
function reporte_html(array $reporte, string $volverA, string $csvUrl): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex" />
  <title><?= escapar($reporte['titulo']) ?> — PrimeCup</title>
  <link rel="stylesheet" href="reportes.css?v=1" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="reporte-body">
  <div class="reporte-barra no-imprimir">
    <a href="<?= escapar($volverA) ?>" class="reporte-boton"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    <span class="reporte-barra-espacio"></span>
    <a href="<?= escapar($csvUrl) ?>" class="reporte-boton"><i class="fa-solid fa-file-csv"></i> Descargar CSV</a>
    <button type="button" class="reporte-boton reporte-boton-principal" id="btnImprimir"><i class="fa-solid fa-print"></i> Imprimir / guardar PDF</button>
  </div>

  <main class="reporte-hoja">
    <header class="reporte-encabezado">
      <div class="reporte-marca">PrimeCup · SGDM</div>
      <h1><?= escapar($reporte['titulo']) ?></h1>
      <?php if (!empty($reporte['subtitulo'])): ?><p class="reporte-subtitulo"><?= escapar($reporte['subtitulo']) ?></p><?php endif; ?>
      <p class="reporte-generado">Generado el <?= escapar(reporteFechaGeneracion()) ?></p>
    </header>

    <?php foreach ($reporte['secciones'] as $seccion): ?>
      <section class="reporte-seccion">
        <h2><?= escapar($seccion['titulo']) ?></h2>
        <?php if (empty($seccion['filas'])): ?>
          <p class="reporte-vacio"><?= escapar($seccion['vacio'] ?? 'Sin datos.') ?></p>
        <?php else: ?>
          <div class="reporte-tabla-wrap">
            <table class="reporte-tabla">
              <thead><tr><?php foreach ($seccion['columnas'] as $columna): ?><th scope="col"><?= escapar($columna) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($seccion['filas'] as $fila): ?>
                  <tr><?php foreach ($fila as $celda): ?><td><?= escapar($celda === null ? '' : (string) $celda) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </main>
  <script src="reportes.js?v=1"></script>
</body>
</html>
    <?php
}
