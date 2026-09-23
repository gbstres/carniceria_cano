<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";

// Check administrator permission
if (!tienePermiso('ver')) {
    header("location: ../main/inicio.php");
    exit;
}

date_default_timezone_set("America/Mexico_City");

$id_sucursal_sesion = (int) $_SESSION["id_sucursal"];

// Define variables and initialize with default values
$fecha1 = $_POST['fecha1'] ?? date('Y-m-d');
$fecha2 = $_POST['fecha2'] ?? date('Y-m-d');
$filtro_sucursal = isset($_POST['id_sucursal']) ? (int) $_POST['id_sucursal'] : $id_sucursal_sesion;
$filtro_tipo_pago = $_POST['tipo_pago_filtro'] ?? 'todos'; // 'todos', 'transferencia', 'tarjeta'
$filtro_origen = $_POST['origen_filtro'] ?? 'todos'; // 'todos', 'ventas', 'abonos'

$fecha1Escaped = mysqli_real_escape_string($link, $fecha1);
$fecha2Escaped = mysqli_real_escape_string($link, $fecha2);

$condicion_sucursal_dv = "";
$condicion_sucursal_p = "";
if ($filtro_sucursal > 0) {
    $condicion_sucursal_dv = " AND dv.id_sucursal = $filtro_sucursal ";
    $condicion_sucursal_p = " AND p.id_sucursal = $filtro_sucursal ";
}

$condiciones_where = [];

if ($filtro_origen === 'ventas') {
    $condiciones_where[] = " t.origen_tipo = 'VENTA' ";
} elseif ($filtro_origen === 'abonos') {
    $condiciones_where[] = " t.origen_tipo = 'ABONO_CLIENTE' ";
}

if ($filtro_tipo_pago === 'transferencia') {
    $condiciones_where[] = " t.monto_transferencia > 0 ";
} elseif ($filtro_tipo_pago === 'tarjeta') {
    $condiciones_where[] = " t.monto_tarjeta > 0 ";
} else {
    $condiciones_where[] = " (t.monto_transferencia > 0 OR t.monto_tarjeta > 0) ";
}

$whereClause = " WHERE " . implode(" AND ", $condiciones_where);

$sqlReporte = "
SELECT * FROM (
    SELECT 
        'VENTA' AS origen_tipo,
        dv.id_sucursal,
        s.desc_sucursal,
        dv.id_venta AS folio,
        dv.fecha_ingreso,
        dv.hora_ingreso,
        dv.id_cliente,
        CONCAT(COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido_paterno, '')) AS nombre_cliente,
        dv.tipo_pago AS tipo_pago_hdr,
        COALESCE(tot.total_venta, 0) AS total_operacion,
        ROUND(IF(vpex.tiene_pagos IS NOT NULL, COALESCE(vp2.monto_trans, 0), IF(dv.tipo_pago = 2, COALESCE(tot.total_venta, 0), 0)), 2) AS monto_transferencia,
        ROUND(IF(vpex.tiene_pagos IS NOT NULL, COALESCE(vp3.monto_tarj, 0), IF(dv.tipo_pago = 3, COALESCE(tot.total_venta, 0), 0)), 2) AS monto_tarjeta,
        '' AS observaciones
    FROM cc_det_ventas dv
    INNER JOIN cc_sucursales s ON s.id_sucursal = dv.id_sucursal
    LEFT JOIN cc_clientes c ON c.id_sucursal = dv.id_sucursal AND c.id_cliente = dv.id_cliente
    LEFT JOIN (
        SELECT id_sucursal, id_venta, SUM(ROUND(cantidad * precio_venta, 2)) AS total_venta
        FROM cc_ventas
        WHERE estatus <> 2
        GROUP BY id_sucursal, id_venta
    ) tot ON tot.id_sucursal = dv.id_sucursal AND tot.id_venta = dv.id_venta
    LEFT JOIN (
        SELECT DISTINCT id_sucursal, id_venta, 1 AS tiene_pagos
        FROM cc_ventas_pagos
    ) vpex ON vpex.id_sucursal = dv.id_sucursal AND vpex.id_venta = dv.id_venta
    LEFT JOIN (
        SELECT id_sucursal, id_venta, SUM(importe) AS monto_trans
        FROM cc_ventas_pagos
        WHERE tipo_pago = 2
        GROUP BY id_sucursal, id_venta
    ) vp2 ON vp2.id_sucursal = dv.id_sucursal AND vp2.id_venta = dv.id_venta
    LEFT JOIN (
        SELECT id_sucursal, id_venta, SUM(importe) AS monto_tarj
        FROM cc_ventas_pagos
        WHERE tipo_pago = 3
        GROUP BY id_sucursal, id_venta
    ) vp3 ON vp3.id_sucursal = dv.id_sucursal AND vp3.id_venta = dv.id_venta
    WHERE dv.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
      AND dv.estatus IN (1, 3)
      $condicion_sucursal_dv

    UNION ALL

    SELECT 
        'ABONO_CLIENTE' AS origen_tipo,
        p.id_sucursal,
        s.desc_sucursal,
        p.id_pago AS folio,
        p.fecha_ingreso,
        p.hora_ingreso,
        p.id_cliente,
        CONCAT(COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido_paterno, '')) AS nombre_cliente,
        p.tipo_pago AS tipo_pago_hdr,
        p.importe AS total_operacion,
        ROUND(IF(p.tipo_pago = 2, p.importe, 0), 2) AS monto_transferencia,
        ROUND(IF(p.tipo_pago = 3, p.importe, 0), 2) AS monto_tarjeta,
        COALESCE(p.observaciones, '') AS observaciones
    FROM cc_pagos_clientes p
    INNER JOIN cc_sucursales s ON s.id_sucursal = p.id_sucursal
    LEFT JOIN cc_clientes c ON c.id_sucursal = p.id_sucursal AND c.id_cliente = p.id_cliente
    WHERE p.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
      AND p.estatus <> 2
      AND p.tipo_pago IN (2, 3)
      $condicion_sucursal_p
) t
$whereClause
ORDER BY t.fecha_ingreso DESC, t.hora_ingreso DESC, t.folio DESC
";

$queryReporte = mysqli_query($link, $sqlReporte);

$reporteData = [];
$totalTransferencias = 0;
$transVentas = 0;
$transAbonos = 0;

$totalTarjetas = 0;
$tarjVentas = 0;
$tarjAbonos = 0;

$granTotal = 0;

if ($queryReporte) {
    while ($row = mysqli_fetch_assoc($queryReporte)) {
        $montoTrans = (float) $row['monto_transferencia'];
        $montoTarj = (float) $row['monto_tarjeta'];
        $montoOp = (float) $row['total_operacion'];
        $esVenta = ($row['origen_tipo'] === 'VENTA');

        $totalTransferencias += $montoTrans;
        if ($esVenta) {
            $transVentas += $montoTrans;
        } else {
            $transAbonos += $montoTrans;
        }

        $totalTarjetas += $montoTarj;
        if ($esVenta) {
            $tarjVentas += $montoTarj;
        } else {
            $tarjAbonos += $montoTarj;
        }

        $granTotal += ($montoTrans + $montoTarj);

        $reporteData[] = [
            'origen_tipo' => $row['origen_tipo'],
            'id_sucursal' => $row['id_sucursal'],
            'desc_sucursal' => $row['desc_sucursal'],
            'folio' => $row['folio'],
            'fecha_ingreso' => $row['fecha_ingreso'],
            'hora_ingreso' => $row['hora_ingreso'],
            'cliente' => trim($row['nombre_cliente']) ?: 'Público en general',
            'monto_transferencia' => $montoTrans,
            'monto_tarjeta' => $montoTarj,
            'total_operacion' => $montoOp,
            'tipo_pago_hdr' => (int) $row['tipo_pago_hdr'],
            'observaciones' => trim($row['observaciones'])
        ];
    }
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Reporte de Transferencias y Tarjetas</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>
        <script src="../js/xlsx.full.min.js"></script>

        <style>
            @import "../css/bootstrap.css";
        </style>

        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link href="../css/gijgo.min.css" rel="stylesheet" type="text/css" />
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php"; ?>
                <div>
                    <div class="bg-light p-4 rounded">
                        <div class="col-sm-10 mx-auto">
                            <h1 class="text-center">Reporte de Transferencias y Pagos con Tarjeta</h1>
                            <p class="text-center text-muted">Ventas y pagos de clientes (abonos) mediante transferencia bancaria o tarjeta de débito/crédito</p>
                        </div>
                        <br>
                        
                        <form class="row g-3 needs-validation mb-4" action="#" method="post">
                            <div class="col-md-3">
                                <label for="datepicker1" class="form-label font-weight-bold">Fecha inicio:</label>
                                <input name="fecha1" id="datepicker1" class="form-control" autocomplete="off" readonly value="<?php echo htmlspecialchars($fecha1); ?>" />
                            </div>
                            <div class="col-md-3">
                                <label for="datepicker2" class="form-label font-weight-bold">Fecha fin:</label>
                                <input name="fecha2" id="datepicker2" class="form-control" autocomplete="off" readonly value="<?php echo htmlspecialchars($fecha2); ?>" />
                            </div>
                            <div class="col-md-2">
                                <label for="id_sucursal" class="form-label font-weight-bold">Sucursal:</label>
                                <select id="id_sucursal" name="id_sucursal" class="form-select">
                                    <option value="0" <?php echo ($filtro_sucursal === 0) ? 'selected' : ''; ?>>Todas las sucursales</option>
                                    <?php
                                    $qSuc = mysqli_query($link, "SELECT id_sucursal, desc_sucursal FROM cc_sucursales WHERE activo = 1 ORDER BY id_sucursal");
                                    while ($suc = mysqli_fetch_assoc($qSuc)) {
                                        $selected = ((int)$suc['id_sucursal'] === $filtro_sucursal) ? 'selected' : '';
                                        echo '<option value="' . (int)$suc['id_sucursal'] . '" ' . $selected . '>' . htmlspecialchars($suc['desc_sucursal']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="origen_filtro" class="form-label font-weight-bold">Operación:</label>
                                <select id="origen_filtro" name="origen_filtro" class="form-select">
                                    <option value="todos" <?php echo ($filtro_origen === 'todos') ? 'selected' : ''; ?>>Todas (Ventas y Abonos)</option>
                                    <option value="ventas" <?php echo ($filtro_origen === 'ventas') ? 'selected' : ''; ?>>Solo Ventas</option>
                                    <option value="abonos" <?php echo ($filtro_origen === 'abonos') ? 'selected' : ''; ?>>Solo Abonos de Clientes</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="tipo_pago_filtro" class="form-label font-weight-bold">Medio de pago:</label>
                                <select id="tipo_pago_filtro" name="tipo_pago_filtro" class="form-select">
                                    <option value="todos" <?php echo ($filtro_tipo_pago === 'todos') ? 'selected' : ''; ?>>Todos (Trans. y Tarj.)</option>
                                    <option value="transferencia" <?php echo ($filtro_tipo_pago === 'transferencia') ? 'selected' : ''; ?>>Solo Transferencia</option>
                                    <option value="tarjeta" <?php echo ($filtro_tipo_pago === 'tarjeta') ? 'selected' : ''; ?>>Solo Tarjeta</option>
                                </select>
                            </div>
                            <div class="col-12 text-center mt-3">
                                <button class="btn btn-primary px-4" type="submit" id="buscar_fecha">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>
                        </form>

                        <!-- Cards Resumen Financiero -->
                        <div class="row text-center mb-4">
                            <div class="col-md-4 mb-2">
                                <div class="card border-primary shadow-sm h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary"><i class="bi bi-bank"></i> Total Transferencias</h5>
                                        <h3 class="card-text font-weight-bold mb-2">$<?php echo number_format($totalTransferencias, 2); ?></h3>
                                        <div class="small text-muted">
                                            Ventas: <strong>$<?php echo number_format($transVentas, 2); ?></strong> | Abonos: <strong>$<?php echo number_format($transAbonos, 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="card border-success shadow-sm h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-success"><i class="bi bi-credit-card-2-front"></i> Total Tarjetas</h5>
                                        <h3 class="card-text font-weight-bold mb-2">$<?php echo number_format($totalTarjetas, 2); ?></h3>
                                        <div class="small text-muted">
                                            Ventas: <strong>$<?php echo number_format($tarjVentas, 2); ?></strong> | Abonos: <strong>$<?php echo number_format($tarjAbonos, 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="card border-dark shadow-sm h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-dark"><i class="bi bi-cash-stack"></i> Gran Total Acumulado</h5>
                                        <h3 class="card-text font-weight-bold mb-2">$<?php echo number_format($granTotal, 2); ?></h3>
                                        <div class="small text-muted">
                                            Trans: <strong>$<?php echo number_format($totalTransferencias, 2); ?></strong> | Tarjetas: <strong>$<?php echo number_format($totalTarjetas, 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <br>
                        <div class="col-sm mx-auto mb-3">
                            <h3 class="text-left">Detalle de Operaciones (Ventas y Abonos de Clientes)</h3>
                        </div>

                        <div class="table-responsive">
                            <table id="reporte_tabla" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Sucursal</th>
                                        <th>Operación / Folio</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Cliente</th>
                                        <th>Tipo Pago</th>
                                        <th>Monto Transferencia</th>
                                        <th>Monto Tarjeta</th>
                                        <th>Total Operación</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($reporteData as $v) {
                                        $descTipoPago = '';
                                        if ($v['monto_transferencia'] > 0 && $v['monto_tarjeta'] > 0) {
                                            $descTipoPago = 'PAGO MIXTO (TRA / TAR)';
                                        } elseif ($v['monto_transferencia'] > 0) {
                                            $descTipoPago = 'TRANSFERENCIA';
                                        } elseif ($v['monto_tarjeta'] > 0) {
                                            $descTipoPago = 'TARJETA';
                                        } else {
                                            $descTipoPago = 'OTRO';
                                        }

                                        $badgeOperacion = ($v['origen_tipo'] === 'VENTA')
                                            ? '<span class="badge bg-primary">Venta #' . $v['folio'] . '</span>'
                                            : '<span class="badge bg-success">Abono Cliente #' . $v['folio'] . '</span>';

                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($v['desc_sucursal']) . '</td>';
                                        echo '<td>' . $badgeOperacion . '</td>';
                                        echo '<td>' . $v['fecha_ingreso'] . '</td>';
                                        echo '<td>' . $v['hora_ingreso'] . '</td>';
                                        echo '<td>' . htmlspecialchars($v['cliente']) . '</td>';
                                        echo '<td><span class="badge bg-secondary">' . $descTipoPago . '</span></td>';
                                        echo '<td class="text-end">$ ' . number_format($v['monto_transferencia'], 2) . '</td>';
                                        echo '<td class="text-end">$ ' . number_format($v['monto_tarjeta'], 2) . '</td>';
                                        echo '<td class="text-end">$ ' . number_format($v['total_operacion'], 2) . '</td>';
                                        echo '<td>' . htmlspecialchars($v['observaciones']) . '</td>';
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th class="text-end">$<?php echo number_format($totalTransferencias, 2); ?></th>
                                        <th class="text-end">$<?php echo number_format($totalTarjetas, 2); ?></th>
                                        <th class="text-end">$<?php echo number_format($granTotal, 2); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <br>
                        <div class="align-content-center text-center mt-3">
                            <a href="#" onclick="htmlTableToExcel('xlsx')" class="btn btn-success m-1" role="button" id="btnExport">
                                <i class="bi bi-file-earmark-excel"></i> Extraer Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                $('#reporte_tabla').DataTable({
                    language: {
                        "decimal": "",
                        "emptyTable": "No hay información de transferencias o tarjetas en el rango seleccionado",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                        "infoFiltered": "(Filtrado de _MAX_ total entradas)",
                        "thousands": ",",
                        "lengthMenu": "Mostrar _MENU_ Entradas",
                        "loadingRecords": "Cargando...",
                        "processing": "Procesando...",
                        "search": "Buscar:",
                        "zeroRecords": "Sin resultados encontrados",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    order: [[2, 'desc'], [3, 'desc'], [1, 'desc']],
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Mostrar todo']]
                });
            });

            $('#datepicker1').datepicker({
                uiLibrary: 'bootstrap5',
                format: 'yyyy-mm-dd'
            });
            $('#datepicker2').datepicker({
                uiLibrary: 'bootstrap5',
                format: 'yyyy-mm-dd'
            });

            function htmlTableToExcel(type) {
                const table = document.getElementById('reporte_tabla');
                const datosTabla = [];
                const filas = table.rows;

                for (let i = 0; i < filas.length; i++) {
                    const celdas = filas[i].cells;
                    const filaDatos = [];
                    for (let j = 0; j < celdas.length; j++) {
                        filaDatos.push(celdas[j].innerText.trim());
                    }
                    datosTabla.push(filaDatos);
                }

                const ws = XLSX.utils.aoa_to_sheet(datosTabla);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Transferencias_y_Tarjetas");
                XLSX.writeFile(wb, "Reporte_Transferencias_Tarjetas." + (type || 'xlsx'));
            }
        </script>
    </body>
</html>
