<?php
/**
 * Script para Reconstruir y Corregir Clave 1 (Efectivo) y Sumatorias de Cierres Históricos
 *
 * Uso CLI (Línea de comandos):
 *   php scripts/reconstruir_cierres.php [ID_SUCURSAL] [FECHA_INICIO] [FECHA_FIN] [--aplicar] [--gcp]
 *   Ejemplo vista previa en Local:  php scripts/reconstruir_cierres.php 1 2025-01-01 2026-12-31
 *   Ejemplo vista previa en GCP:    php scripts/reconstruir_cierres.php 1 2025-01-01 2026-12-31 --gcp
 *   Ejemplo aplicar cambios en GCP: php scripts/reconstruir_cierres.php 1 2025-01-01 2026-12-31 --gcp --aplicar
 *
 * Uso Web (Navegador):
 *   http://localhost/carniceriacano/scripts/reconstruir_cierres.php?id_sucursal=1&fecha_inicio=2025-01-01&fecha_fin=2026-12-31&gcp=1
 *   http://localhost/carniceriacano/scripts/reconstruir_cierres.php?id_sucursal=1&fecha_inicio=2025-01-01&fecha_fin=2026-12-31&gcp=1&aplicar=1
 */

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../functions/config.php";
require_once __DIR__ . "/../functions/config_2.php";
require_once __DIR__ . "/../functions/sync_queue.php";

$id_sucursal = 1;
$fecha_inicio = '2025-01-01';
$fecha_fin = date('Y-m-d');
$aplicar = false;
$usar_gcp = false;

if (PHP_SAPI === 'cli') {
    global $argv;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $id_sucursal = (int) $argv[1];
    }
    if (isset($argv[2]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[2])) {
        $fecha_inicio = $argv[2];
    }
    if (isset($argv[3]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[3])) {
        $fecha_fin = $argv[3];
    }
    foreach ($argv as $arg) {
        if ($arg === '--aplicar' || $arg === '-a') {
            $aplicar = true;
        }
        if ($arg === '--gcp' || $arg === '-g') {
            $usar_gcp = true;
        }
    }
} else {
    if (isset($_GET['id_sucursal']) && is_numeric($_GET['id_sucursal'])) {
        $id_sucursal = (int) $_GET['id_sucursal'];
    }
    if (isset($_GET['fecha_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha_inicio'])) {
        $fecha_inicio = $_GET['fecha_inicio'];
    }
    if (isset($_GET['fecha_fin']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha_fin'])) {
        $fecha_fin = $_GET['fecha_fin'];
    }
    if (isset($_GET['aplicar']) && $_GET['aplicar'] == '1') {
        $aplicar = true;
    }
    if (isset($_GET['gcp']) && $_GET['gcp'] == '1') {
        $usar_gcp = true;
    }
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$db = $usar_gcp ? $link2 : $link;
$nombreBD = $usar_gcp ? "GCP (Servidor Remoto 34.172.184.194)" : "LOCAL";

echo "=========================================================\n";
echo " RECONSTRUCCIÓN Y CORRECCIÓN DE CIERRES HISTÓRICOS\n";
echo " Base de Datos: $nombreBD\n";
echo " Sucursal: $id_sucursal | Rango: $fecha_inicio a $fecha_fin\n";
echo " Modo: " . ($aplicar ? "MODIFICACIÓN (Aplicando cambios en $nombreBD)" : "VISTA PREVIA (Solo lectura - usa --aplicar para guardar)") . "\n";
echo "=========================================================\n\n";

if (!$db) {
    die("Error: No hay conexión a la base de datos $nombreBD.\n");
}

$sqlCierres = mysqli_query($db, "
    SELECT id_cierre, fecha_ingreso, hora_ingreso
    FROM cc_cierre
    WHERE id_sucursal = $id_sucursal
      AND fecha_ingreso BETWEEN '$fecha_inicio' AND '$fecha_fin'
    GROUP BY id_cierre, fecha_ingreso, hora_ingreso
    ORDER BY id_cierre ASC
");

if (!$sqlCierres || mysqli_num_rows($sqlCierres) === 0) {
    die("No se encontraron cierres para la sucursal $id_sucursal en $nombreBD en el rango especificado.\n");
}

$modificados = 0;
$totalRevisados = 0;

while ($rowCierre = mysqli_fetch_assoc($sqlCierres)) {
    $totalRevisados++;
    $id_cierre = (int) $rowCierre['id_cierre'];
    $fecha_cierre = $rowCierre['fecha_ingreso'];

    // Leer valores actuales guardados en cc_cierre
    $resClavesActuales = mysqli_query($db, "SELECT clave, importe FROM cc_cierre WHERE id_sucursal = $id_sucursal AND id_cierre = $id_cierre");
    $clavesActuales = [];
    while ($rK = mysqli_fetch_assoc($resClavesActuales)) {
        $clavesActuales[(int) $rK['clave']] = (float) $rK['importe'];
    }

    $efectivoGuardado = $clavesActuales[1] ?? 0.0;
    $entradasGuardadas = $clavesActuales[2] ?? 0.0;
    $gastosGuardados = $clavesActuales[3] ?? 0.0;
    $creditoGuardado = $clavesActuales[4] ?? 0.0;
    $abonosEfectivoGuardados = $clavesActuales[5] ?? 0.0;

    // Recalcular Efectivo Contado real (Ventas sin cliente id_cliente = 0) asociadas a este id_cierre
    $resEfectivoReal = mysqli_query($db, "
        SELECT 
            COALESCE(
                SUM(
                    CASE 
                        WHEN (a.id_cliente = 0 OR a.id_cliente IS NULL) AND a.tipo_pago = 1 AND NOT EXISTS (SELECT 1 FROM cc_ventas_pagos px WHERE px.id_sucursal=a.id_sucursal AND px.id_venta=a.id_venta) THEN ROUND(b.cantidad * b.precio_venta, 2)
                        ELSE 0 
                    END
                ) 
                + (
                    SELECT COALESCE(SUM(vp.importe), 0) 
                    FROM cc_ventas_pagos vp 
                    INNER JOIN cc_det_ventas dv ON dv.id_sucursal=vp.id_sucursal AND dv.id_venta=vp.id_venta 
                    WHERE dv.id_sucursal = $id_sucursal 
                      AND dv.id_cierre = $id_cierre 
                      AND (dv.id_cliente = 0 OR dv.id_cliente IS NULL) 
                      AND EXISTS (SELECT 1 FROM cc_ventas va WHERE va.id_sucursal=vp.id_sucursal AND va.id_venta=vp.id_venta AND va.estatus <> 2) 
                      AND vp.tipo_pago = 1
                ),
                0
            ) AS efectivo_contado_real
        FROM cc_det_ventas a
        INNER JOIN cc_ventas b ON a.id_sucursal = b.id_sucursal AND a.id_venta = b.id_venta
        WHERE a.id_sucursal = $id_sucursal
          AND a.id_cierre = $id_cierre
          AND b.estatus <> 2
    ");

    $rowEfectivoReal = mysqli_fetch_assoc($resEfectivoReal);
    $efectivoContadoReal = (float) ($rowEfectivoReal['efectivo_contado_real'] ?? 0.0);

    $diferencia = round($efectivoGuardado - $efectivoContadoReal, 2);

    $cajaAnterior = round($efectivoGuardado + $entradasGuardadas + $abonosEfectivoGuardados - $gastosGuardados, 2);
    $cajaCorregida = round($efectivoContadoReal + $entradasGuardadas + $abonosEfectivoGuardados - $gastosGuardados, 2);

    if (abs($diferencia) > 0.01) {
        $modificados++;
        echo "[Cierre ID: $id_cierre | Fecha: $fecha_cierre]\n";
        echo "  - Clave 1 (Efectivo) Guardado: $" . number_format($efectivoGuardado, 2) . "  ==>  Nuevo Recalculado: $" . number_format($efectivoContadoReal, 2) . "\n";
        echo "  - Dinero en Caja Anterior:    $" . number_format($cajaAnterior, 2) . "  ==>  Nuevo Dinero en Caja: $" . number_format($cajaCorregida, 2) . "\n";

        if ($aplicar) {
            // Actualizar Clave 1 en la base de datos seleccionada
            $stmtUpd = mysqli_prepare($db, "UPDATE cc_cierre SET importe = ? WHERE id_sucursal = ? AND id_cierre = ? AND clave = 1");
            if ($stmtUpd) {
                mysqli_stmt_bind_param($stmtUpd, "dii", $efectivoContadoReal, $id_sucursal, $id_cierre);
                if (mysqli_stmt_execute($stmtUpd)) {
                    if (!$usar_gcp) {
                        // Encolar a GCP desde local
                        cc_sync_enqueue($link, $id_sucursal, 'cierre', 'upsert', [
                            'id_cierre' => (int) $id_cierre,
                            'clave' => 1,
                        ], [
                            'tabla' => 'cc_cierre',
                            'motivo' => 'reconstruccion_cierre_historico',
                        ]);
                    }
                    echo "  -> RESULTADO: ¡Cierre actualizado correctamente en $nombreBD!\n";
                } else {
                    echo "  -> ERROR: No se pudo actualizar el cierre: " . mysqli_error($db) . "\n";
                }
                mysqli_stmt_close($stmtUpd);
            }
        } else {
            echo "  -> (Vista previa: No se realizaron cambios. Ejecuta con --aplicar para guardar)\n";
        }
        echo "\n";
    }
}

echo "=========================================================\n";
echo " RESUMEN FINAL DE PROCESAMIENTO ($nombreBD)\n";
echo " Cierres revisados: $totalRevisados\n";
echo " Cierres con diferencia corregidos/detectados: $modificados\n";
echo "=========================================================\n";
