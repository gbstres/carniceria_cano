<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";

$id_sucursal = (int) $_SESSION["id_sucursal"];
$mensaje = "";
$error = "";
$cierresAfectados = [];

$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
$accion = $_POST['accion'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($accion)) {
    $aplicar = ($accion === 'aplicar');

    $sqlCierres = mysqli_query($link, "
        SELECT id_cierre, fecha_ingreso, hora_ingreso
        FROM cc_cierre
        WHERE id_sucursal = $id_sucursal
          AND fecha_ingreso BETWEEN '$fecha_inicio' AND '$fecha_fin'
        GROUP BY id_cierre, fecha_ingreso, hora_ingreso
        ORDER BY id_cierre ASC
    ");

    if ($sqlCierres && mysqli_num_rows($sqlCierres) > 0) {
        $modificadosCount = 0;
        while ($rowCierre = mysqli_fetch_assoc($sqlCierres)) {
            $id_cierre = (int) $rowCierre['id_cierre'];
            $fecha_cierre = $rowCierre['fecha_ingreso'];

            // Leer valores actuales guardados en cc_cierre
            $resClavesActuales = mysqli_query($link, "SELECT clave, importe FROM cc_cierre WHERE id_sucursal = $id_sucursal AND id_cierre = $id_cierre");
            $clavesActuales = [];
            while ($rK = mysqli_fetch_assoc($resClavesActuales)) {
                $clavesActuales[(int) $rK['clave']] = (float) $rK['importe'];
            }

            $efectivoGuardado = $clavesActuales[1] ?? 0.0;
            $entradasGuardadas = $clavesActuales[2] ?? 0.0;
            $gastosGuardados = $clavesActuales[3] ?? 0.0;
            $abonosEfectivoGuardados = $clavesActuales[5] ?? 0.0;

            // Recalcular Efectivo Contado real (Ventas sin cliente id_cliente = 0) asociadas a este id_cierre
            $resEfectivoReal = mysqli_query($link, "
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

            $cajaAnterior = round($efectivoGuardado + $entradasGuardadas + $abonosEfectivoGuardados - $gastosGuardados, 2);
            $cajaCorregida = round($efectivoContadoReal + $entradasGuardadas + $abonosEfectivoGuardados - $gastosGuardados, 2);
            $diferencia = round($efectivoGuardado - $efectivoContadoReal, 2);

            if (abs($diferencia) > 0.01) {
                $estadoProceso = "Detectado con diferencia";

                if ($aplicar) {
                    $stmtUpd = mysqli_prepare($link, "UPDATE cc_cierre SET importe = ? WHERE id_sucursal = ? AND id_cierre = ? AND clave = 1");
                    if ($stmtUpd) {
                        mysqli_stmt_bind_param($stmtUpd, "dii", $efectivoContadoReal, $id_sucursal, $id_cierre);
                        if (mysqli_stmt_execute($stmtUpd)) {
                            cc_sync_enqueue($link, $id_sucursal, 'cierre', 'upsert', [
                                'id_cierre' => (int) $id_cierre,
                                'clave' => 1,
                            ], [
                                'tabla' => 'cc_cierre',
                                'motivo' => 'reconstruccion_cierre_historico',
                            ]);
                            $estadoProceso = "¡Actualizado y Encolado a GCP!";
                            $modificadosCount++;
                        } else {
                            $estadoProceso = "Error al actualizar BD";
                        }
                        mysqli_stmt_close($stmtUpd);
                    }
                }

                $cierresAfectados[] = [
                    'id_cierre' => $id_cierre,
                    'fecha' => $fecha_cierre,
                    'efectivo_anterior' => $efectivoGuardado,
                    'efectivo_nuevo' => $efectivoContadoReal,
                    'caja_anterior' => $cajaAnterior,
                    'caja_nueva' => $cajaCorregida,
                    'estado' => $estadoProceso,
                ];
            }
        }

        if ($aplicar) {
            $mensaje = "Se han corregido y sincronizado exitosamente $modificadosCount cierre(s) en el rango seleccionado.";
        } else {
            $mensaje = "Vista previa completada. Se detectaron " . count($cierresAfectados) . " cierre(s) con diferencias.";
        }
    } else {
        $error = "No se encontraron cierres para la sucursal en el rango de fechas seleccionado.";
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reconstruir Cierres - Carnicería Cano</title>
    <link href="../css/bootstrap.css" rel="stylesheet">
    <link href="../css/navbar.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/bootstrap-icons.css">
    <style>
        .card { margin-top: 20px; }
        .table-responsive { margin-top: 20px; }
    </style>
</head>
<body>
<main>
    <div class="container">
        <?php require_once "../components/nav.php"; ?>
        <div class="bg-light p-4 rounded mt-3">
            <div class="col-md-10 mx-auto">
                <h2 class="text-center mb-4"><i class="bi bi-arrow-repeat"></i> Reconstruir Cierres Históricos</h2>
                <p class="text-muted text-center">
                    Esta herramienta recalcula la Clave 1 (Efectivo de ventas al contado) y corriga la sumatoria total del Dinero en Caja para los cierres seleccionados, enviando automáticamente la actualización a GCP.
                </p>

                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-calendar-range"></i> Seleccione el Rango de Fechas a Revisar</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="reconstruir_cierre.php" class="row g-3">
                            <div class="col-md-6">
                                <label for="fecha_inicio" class="form-label font-weight-bold">Fecha Inicio:</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fecha_fin" class="form-label font-weight-bold">Fecha Fin:</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>" required>
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" name="accion" value="simular" class="btn btn-secondary me-2">
                                    <i class="bi bi-search"></i> Vista Previa (Simular)
                                </button>
                                <button type="submit" name="accion" value="aplicar" class="btn btn-success" onclick="return confirm('¿Está seguro de corregir y sincronizar a GCP los cierres del rango seleccionado?');">
                                    <i class="bi bi-check2-square"></i> Aplicar Corrección y Sincronizar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($cierresAfectados)): ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-list-check"></i> Cierres Detectados con Diferencia</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle text-center">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID Cierre</th>
                                            <th>Fecha Cierre</th>
                                            <th>Efectivo Anterior</th>
                                            <th>Efectivo Recalculado</th>
                                            <th>Caja Anterior</th>
                                            <th>Nuevo Dinero en Caja</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cierresAfectados as $cierre): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo $cierre['id_cierre']; ?></td>
                                                <td><?php echo $cierre['fecha']; ?></td>
                                                <td class="text-danger">$<?php echo number_format($cierre['efectivo_anterior'], 2); ?></td>
                                                <td class="text-success fw-bold">$<?php echo number_format($cierre['efectivo_nuevo'], 2); ?></td>
                                                <td class="text-danger">$<?php echo number_format($cierre['caja_anterior'], 2); ?></td>
                                                <td class="text-success fw-bold">$<?php echo number_format($cierre['caja_nueva'], 2); ?></td>
                                                <td>
                                                    <span class="badge <?php echo strpos($cierre['estado'], '¡Actualizado') !== false ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                        <?php echo $cierre['estado']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>
<script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
