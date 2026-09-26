<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";

if (!tienePermiso('ver')) {
    header("location: ../main/inicio.php");
    exit;
}

date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];

// Parámetros de filtro
$fecha1 = $_POST['fecha1'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha2 = $_POST['fecha2'] ?? date('Y-m-d');
$dias_cobertura = isset($_POST['dias_cobertura']) ? max(1, (int) $_POST['dias_cobertura']) : 7;
$id_categoria_filtro = isset($_POST['id_categoria']) ? (int) $_POST['id_categoria'] : 0;

$fecha1Escaped = mysqli_real_escape_string($link, $fecha1);
$fecha2Escaped = mysqli_real_escape_string($link, $fecha2);

// Calcular número de días en el periodo seleccionado
$dt1 = new DateTime($fecha1);
$dt2 = new DateTime($fecha2);
$dias_periodo = max(1, $dt1->diff($dt2)->days + 1);

// Cargar categorías para el filtro
$sqlCats = mysqli_query($link, "SELECT id_categoria, desc_categoria FROM cc_categorias WHERE id_sucursal = $id_sucursal ORDER BY desc_categoria");
$categoriasFiltro = [];
if ($sqlCats) {
    while ($r = mysqli_fetch_assoc($sqlCats)) {
        $categoriasFiltro[] = $r;
    }
}

// -------------------------------------------------------------
// 1. CONSULTA PRINCIPAL: Velocidad de Venta y Sugerencia de Compra
// -------------------------------------------------------------
$whereCat = $id_categoria_filtro > 0 ? "AND p.id_categoria = $id_categoria_filtro" : "";

$sqlVelocidadQuery = "
    SELECT 
        p.codigo,
        p.descripcion,
        p.id_categoria,
        COALESCE(c.desc_categoria, 'Sin categoría') AS desc_categoria,
        COALESCE(p.stock, 0) AS stock_actual,
        COALESCE(p.limite_stock, 0) AS limite_stock,
        COALESCE(v.total_cant, 0) AS total_cant,
        COALESCE(v.total_importe, 0) AS total_importe,
        COALESCE(v.total_costo, 0) AS total_costo
    FROM cc_productos p
    LEFT JOIN cc_categorias c ON c.id_sucursal = p.id_sucursal AND c.id_categoria = p.id_categoria
    LEFT JOIN (
        SELECT 
            v.codigo,
            SUM(v.cantidad) AS total_cant,
            SUM(ROUND(v.cantidad * v.precio_venta, 2)) AS total_importe,
            SUM(ROUND(v.cantidad * v.precio_compra, 2)) AS total_costo
        FROM cc_det_ventas dv
        INNER JOIN cc_ventas v ON v.id_sucursal = dv.id_sucursal AND v.id_venta = dv.id_venta
        WHERE dv.id_sucursal = $id_sucursal
          AND dv.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
          AND dv.estatus IN (1, 3)
          AND v.estatus <> 2
        GROUP BY v.codigo
    ) v ON v.codigo = p.codigo
    WHERE p.id_sucursal = $id_sucursal $whereCat
      AND (COALESCE(v.total_cant, 0) > 0 OR COALESCE(p.stock, 0) > 0)
    ORDER BY total_cant DESC, p.descripcion ASC
";

$resVelocidad = mysqli_query($link, $sqlVelocidadQuery);
$listaVelocidad = [];
$totKilosVendidos = 0;
$totImporteVendido = 0;
$totGananciaEstimada = 0;
$totalSugerenciaCompraKg = 0;

if ($resVelocidad) {
    while ($row = mysqli_fetch_assoc($resVelocidad)) {
        $cant = (float) $row['total_cant'];
        $imp = (float) $row['total_importe'];
        $cost = (float) $row['total_costo'];
        $stock = (float) $row['stock_actual'];
        $ganancia = $imp - $cost;

        $promDiario = $cant / $dias_periodo;
        $necesidadPeriodo = $promDiario * $dias_cobertura;
        $sugerenciaCompra = max(0, $necesidadPeriodo - $stock);

        $totKilosVendidos += $cant;
        $totImporteVendido += $imp;
        $totGananciaEstimada += $ganancia;
        $totalSugerenciaCompraKg += $sugerenciaCompra;

        $listaVelocidad[] = [
            'codigo' => $row['codigo'],
            'descripcion' => $row['descripcion'],
            'desc_categoria' => $row['desc_categoria'],
            'stock_actual' => $stock,
            'total_cant' => $cant,
            'total_importe' => $imp,
            'ganancia' => $ganancia,
            'prom_diario' => $promDiario,
            'necesidad' => $necesidadPeriodo,
            'sugerencia_compra' => $sugerenciaCompra,
        ];
    }
}

// -------------------------------------------------------------
// 2. CONSULTA POR DÍA DE LA SEMANA (Estacionalidad Semanal)
// -------------------------------------------------------------
$sqlDiasSemanaQuery = "
    SELECT 
        DAYOFWEEK(dv.fecha_ingreso) as num_dia,
        SUM(v.cantidad) as total_cant,
        SUM(ROUND(v.cantidad * v.precio_venta, 2)) as total_importe
    FROM cc_det_ventas dv
    INNER JOIN cc_ventas v ON v.id_sucursal = dv.id_sucursal AND v.id_venta = dv.id_venta
    INNER JOIN cc_productos p ON p.id_sucursal = v.id_sucursal AND p.codigo = v.codigo
    WHERE dv.id_sucursal = $id_sucursal
      AND dv.fecha_ingreso BETWEEN '$fecha1Escaped' AND '$fecha2Escaped'
      AND dv.estatus IN (1, 3)
      AND v.estatus <> 2
      $whereCat
    GROUP BY DAYOFWEEK(dv.fecha_ingreso)
    ORDER BY num_dia ASC
";

$resDiasSemana = mysqli_query($link, $sqlDiasSemanaQuery);
$nombresDias = [
    1 => 'Domingo',
    2 => 'Lunes',
    3 => 'Martes',
    4 => 'Miércoles',
    5 => 'Jueves',
    6 => 'Viernes',
    7 => 'Sábado'
];
$datosDiasSemana = array_fill(1, 7, ['cant' => 0, 'importe' => 0]);

if ($resDiasSemana) {
    while ($r = mysqli_fetch_assoc($resDiasSemana)) {
        $dia = (int) $r['num_dia'];
        $datosDiasSemana[$dia] = [
            'cant' => (float) $r['total_cant'],
            'importe' => (float) $r['total_importe']
        ];
    }
}

// -------------------------------------------------------------
// 3. CONSULTA HISTÓRICA MENSUAL (Estacionalidad Mensual / Temporadas)
// -------------------------------------------------------------
$sqlMensualQuery = "
    SELECT 
        DATE_FORMAT(dv.fecha_ingreso, '%Y-%m') as mes_anio,
        SUM(v.cantidad) as total_cant,
        SUM(ROUND(v.cantidad * v.precio_venta, 2)) as total_importe
    FROM cc_det_ventas dv
    INNER JOIN cc_ventas v ON v.id_sucursal = dv.id_sucursal AND v.id_venta = dv.id_venta
    INNER JOIN cc_productos p ON p.id_sucursal = v.id_sucursal AND p.codigo = v.codigo
    WHERE dv.id_sucursal = $id_sucursal
      AND dv.estatus IN (1, 3)
      AND v.estatus <> 2
      $whereCat
      AND dv.fecha_ingreso >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(dv.fecha_ingreso, '%Y-%m')
    ORDER BY mes_anio ASC
";

$resMensual = mysqli_query($link, $sqlMensualQuery);
$datosMensual = [];
if ($resMensual) {
    while ($r = mysqli_fetch_assoc($resMensual)) {
        $datosMensual[] = [
            'mes' => $r['mes_anio'],
            'cant' => (float) $r['total_cant'],
            'importe' => (float) $r['total_importe']
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
        <title>Estadísticas de Ventas e Inteligencia de Compras</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            @import "../css/bootstrap.css";
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .nav-tabs .nav-link.active {
                font-weight: bold;
                border-bottom: 3px solid #0d6efd;
            }
        </style>
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/bootstrap-icons.css">
    </head>
    <body>
        <main>
            <div class="container pb-5">
                <?php require_once "../components/nav.php"; ?>

                <div class="bg-light p-4 rounded shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-0 text-dark"><i class="bi bi-graph-up-arrow text-primary"></i> Inteligencia de Compras y Estacionalidad</h2>
                            <small class="text-muted">Análisis estadístico de ventas para proyecciones de compra y temporadas</small>
                        </div>
                    </div>

                    <!-- Filtro Avanzado -->
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-body bg-white rounded">
                            <form method="POST" action="estadisticas_ventas.php" class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="fecha1" class="form-label fw-bold">Fecha Inicio:</label>
                                    <input type="date" class="form-control" name="fecha1" id="fecha1" value="<?php echo htmlspecialchars($fecha1, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="fecha2" class="form-label fw-bold">Fecha Fin:</label>
                                    <input type="date" class="form-control" name="fecha2" id="fecha2" value="<?php echo htmlspecialchars($fecha2, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="id_categoria" class="form-label fw-bold">Categoría:</label>
                                    <select name="id_categoria" id="id_categoria" class="form-select">
                                        <option value="0">-- Todas las categorías --</option>
                                        <?php foreach ($categoriasFiltro as $cat): ?>
                                            <option value="<?php echo $cat['id_categoria']; ?>" <?php echo $id_categoria_filtro == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="dias_cobertura" class="form-label fw-bold">Días de Cobertura (Compra):</label>
                                    <select name="dias_cobertura" id="dias_cobertura" class="form-select">
                                        <option value="3" <?php echo $dias_cobertura == 3 ? 'selected' : ''; ?>>3 Días (Fines de semana)</option>
                                        <option value="7" <?php echo $dias_cobertura == 7 ? 'selected' : ''; ?>>7 Días (1 Semana)</option>
                                        <option value="15" <?php echo $dias_cobertura == 15 ? 'selected' : ''; ?>>15 Días (Quincena)</option>
                                        <option value="30" <?php echo $dias_cobertura == 30 ? 'selected' : ''; ?>>30 Días (1 Mes)</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-funnel"></i> Aplicar Filtros
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tarjetas de Resumen KPI -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 text-uppercase small fw-bold">Periodo Analizado</h6>
                                    <h3 class="mb-0"><?php echo $dias_periodo; ?> días</h3>
                                    <small class="text-white-50"><?php echo $fecha1; ?> al <?php echo $fecha2; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 text-uppercase small fw-bold">Ventas Totales (Periodo)</h6>
                                    <h3 class="mb-0"><?php echo number_format($totKilosVendidos, 2); ?> kg</h3>
                                    <small class="text-white-50">$<?php echo number_format($totImporteVendido, 2); ?> acumulados</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 text-uppercase small fw-bold">Promedio Diario Global</h6>
                                    <h3 class="mb-0"><?php echo number_format($totKilosVendidos / $dias_periodo, 2); ?> kg/día</h3>
                                    <small class="text-white-50">Velocidad media de salida</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h6 class="text-dark-50 text-uppercase small fw-bold">Sugerencia Compra (<?php echo $dias_cobertura; ?> días)</h6>
                                    <h3 class="mb-0"><?php echo number_format($totalSugerenciaCompraKg, 2); ?> kg</h3>
                                    <small class="text-dark">Reabastecimiento estimado</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestañas de Navegación del Reporte -->
                    <ul class="nav nav-tabs mb-4" id="estadisticasTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="sugerencia-tab" data-bs-toggle="tab" data-bs-target="#sugerencia" type="button" role="tab">
                                <i class="bi bi-cart-check"></i> Sugerencias de Compra por Producto
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="semanal-tab" data-bs-toggle="tab" data-bs-target="#semanal" type="button" role="tab">
                                <i class="bi bi-calendar-week"></i> Demanda por Día de la Semana
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="mensual-tab" data-bs-toggle="tab" data-bs-target="#mensual" type="button" role="tab">
                                <i class="bi bi-calendar3"></i> Tendencia Histórica Mensual
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="estadisticasTabsContent">
                        <!-- TAB 1: Sugerencia de Compra por Producto -->
                        <div class="tab-pane fade show active" id="sugerencia" role="tabpanel">
                            <div class="table-responsive bg-white p-3 rounded shadow-sm">
                                <table id="tabla_sugerencias" class="display" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Categoría</th>
                                            <th class="text-end">Venta Total (kg)</th>
                                            <th class="text-end">Venta Diaria (kg/día)</th>
                                            <th class="text-end">Stock Actual (kg)</th>
                                            <th class="text-end">Necesidad (<?php echo $dias_cobertura; ?> días)</th>
                                            <th class="text-end">Sugerencia Compra (kg)</th>
                                            <th class="text-center">Estado Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($listaVelocidad as $item): ?>
                                            <?php
                                            $coberturaStockDias = ($item['prom_diario'] > 0) ? ($item['stock_actual'] / $item['prom_diario']) : 999;
                                            if ($coberturaStockDias <= 2) {
                                                $badgeClass = 'bg-danger';
                                                $estadoTxt = 'CRÍTICO';
                                            } elseif ($coberturaStockDias <= $dias_cobertura) {
                                                $badgeClass = 'bg-warning text-dark';
                                                $estadoTxt = 'REABASTECER';
                                            } else {
                                                $badgeClass = 'bg-success';
                                                $estadoTxt = 'ÓPTIMO';
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['codigo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><strong><?php echo htmlspecialchars($item['descripcion'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                                <td><?php echo htmlspecialchars($item['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end"><?php echo number_format($item['total_cant'], 2); ?></td>
                                                <td class="text-end fw-bold text-primary"><?php echo number_format($item['prom_diario'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($item['stock_actual'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($item['necesidad'], 2); ?></td>
                                                <td class="text-end fw-bold text-success" style="font-size: 1.05rem;">
                                                    <?php echo number_format($item['sugerencia_compra'], 2); ?> kg
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $estadoTxt; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: Venta por Día de la Semana -->
                        <div class="tab-pane fade" id="semanal" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="bg-white p-3 rounded shadow-sm">
                                        <h5 class="card-title text-center mb-3">Distribución de Ventas por Día de Semana</h5>
                                        <canvas id="chartDiasSemana" height="250"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive bg-white p-3 rounded shadow-sm">
                                        <h5 class="card-title mb-3">Desglose por Día</h5>
                                        <table class="table table-bordered align-middle">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Día</th>
                                                    <th class="text-end">Kilos Vendidos</th>
                                                    <th class="text-end">Ventas ($)</th>
                                                    <th class="text-center">% del Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($datosDiasSemana as $numDia => $d): ?>
                                                    <?php
                                                    $pctDia = ($totKilosVendidos > 0) ? ($d['cant'] / $totKilosVendidos) * 100 : 0;
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo $nombresDias[$numDia]; ?></strong></td>
                                                        <td class="text-end"><?php echo number_format($d['cant'], 2); ?> kg</td>
                                                        <td class="text-end">$<?php echo number_format($d['importe'], 2); ?></td>
                                                        <td class="text-center">
                                                            <div class="progress" style="height: 18px;">
                                                                <div class="progress-bar bg-info" style="width: <?php echo min(100, $pctDia); ?>%;">
                                                                    <?php echo number_format($pctDia, 1); ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: Histórico Mensual -->
                        <div class="tab-pane fade" id="mensual" role="tabpanel">
                            <div class="bg-white p-4 rounded shadow-sm">
                                <h5 class="card-title text-center mb-4">Comportamiento Histórico de Ventas (Últimos 12 Meses)</h5>
                                <canvas id="chartMensual" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                $('#tabla_sugerencias').DataTable({
                    language: {
                        "emptyTable": "No hay datos en el rango seleccionado",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ productos",
                        "infoEmpty": "Mostrando 0 a 0 de 0",
                        "lengthMenu": "Mostrar _MENU_",
                        "search": "Buscar producto:",
                        "paginate": {"first": "Primero", "last": "Último", "next": "Siguiente", "previous": "Anterior"}
                    },
                    pageLength: 25,
                    order: [[7, 'desc']] // Ordenar por mayor sugerencia de compra
                });

                // Chart Días de la Semana
                const ctxDias = document.getElementById('chartDiasSemana').getContext('2d');
                new Chart(ctxDias, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_values($nombresDias)); ?>,
                        datasets: [{
                            label: 'Kilos Vendidos',
                            data: <?php echo json_encode(array_map(function($d) { return round($d['cant'], 2); }, array_values($datosDiasSemana))); ?>,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Chart Histórico Mensual
                const ctxMensual = document.getElementById('chartMensual').getContext('2d');
                const labelsMensual = <?php echo json_encode(array_map(function($m) { return $m['mes']; }, $datosMensual)); ?>;
                const cantMensual = <?php echo json_encode(array_map(function($m) { return round($m['cant'], 2); }, $datosMensual)); ?>;

                new Chart(ctxMensual, {
                    type: 'line',
                    data: {
                        labels: labelsMensual,
                        datasets: [{
                            label: 'Volumen Vendido (kg)',
                            data: cantMensual,
                            fill: true,
                            backgroundColor: 'rgba(25, 135, 84, 0.15)',
                            borderColor: 'rgba(25, 135, 84, 1)',
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } }
                    }
                });
            });
        </script>
    </body>
</html>
