<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $returnTo = $_SERVER['REQUEST_URI'] ?? '/reportes/ventas_por_sucursal.php';
    header('location: ../login/login.php?return_to=' . rawurlencode($returnTo));
    exit;
}

require_once '../functions/config.php';
date_default_timezone_set('America/Mexico_City');

if (!tienePermiso('ver')) {
    http_response_code(403);
    exit('No tienes permiso para consultar este reporte.');
}

function ventasSucFecha($valor, $default)
{
    $fecha = DateTime::createFromFormat('Y-m-d', trim((string) $valor));
    return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : $default;
}

function ventasSucH($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function ventasSucQuery(mysqli $link, $sql)
{
    $result = mysqli_query($link, $sql);
    if (!$result) {
        throw new RuntimeException(mysqli_error($link));
    }
    return $result;
}

function ventasSucMatriz(mysqli $link)
{
    $result = ventasSucQuery($link, "SELECT clave FROM cc_claves WHERE nombre_clave='MATRIZ_ID_SUCURSAL' ORDER BY orden,clave LIMIT 1");
    $row = mysqli_fetch_assoc($result);
    $id = (int) ($row['clave'] ?? 0);
    if ($id <= 0) {
        $result = ventasSucQuery($link, "SELECT id_sucursal FROM cc_sucursales WHERE UPPER(TRIM(desc_sucursal))='MATRIZ' ORDER BY id_sucursal LIMIT 1");
        $row = mysqli_fetch_assoc($result);
        $id = (int) ($row['id_sucursal'] ?? 0);
    }
    if ($id <= 0) {
        throw new RuntimeException('No está configurada la sucursal Matriz.');
    }
    return $id;
}

$hoy = date('Y-m-d');
$fecha1 = ventasSucFecha($_GET['fecha1'] ?? $hoy, $hoy);
$fecha2 = ventasSucFecha($_GET['fecha2'] ?? $hoy, $hoy);
if ($fecha1 > $fecha2) {
    [$fecha1, $fecha2] = [$fecha2, $fecha1];
}
$agrupacion = ($_GET['agrupacion'] ?? 'producto') === 'categoria' ? 'categoria' : 'producto';
$filtro = trim((string) ($_GET['filtro'] ?? ''));
$opcionesFiltro = [];
$sucursales = $filas = $totales = [];
$totalPeso = $totalVenta = 0.0;
$error = '';

try {
    $idMatriz = ventasSucMatriz($link);
    $result = ventasSucQuery($link, "SELECT id_sucursal,desc_sucursal FROM cc_sucursales WHERE activo=1 AND id_sucursal<>$idMatriz AND UPPER(desc_sucursal) NOT LIKE '%PRUEBA%' ORDER BY desc_sucursal,id_sucursal");
    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int) $row['id_sucursal'];
        $sucursales[$id] = $row['desc_sucursal'];
        $totales[$id] = ['peso' => 0.0, 'venta' => 0.0];
    }

    if ($sucursales) {
        $ids = implode(',', array_map('intval', array_keys($sucursales)));
        if ($agrupacion === 'categoria') {
            $result = ventasSucQuery($link, "SELECT DISTINCT
                    CONCAT(IF(cg.id_categoria_global IS NULL,'L:','G:'),COALESCE(CAST(cg.id_categoria_global AS CHAR),UPPER(TRIM(c.desc_categoria)))) clave,
                    COALESCE(cg.nombre,c.desc_categoria,'SIN CATEGORÍA') descripcion
                FROM cc_productos p
                LEFT JOIN cc_categorias c ON c.id_sucursal=p.id_sucursal AND c.id_categoria=p.id_categoria
                LEFT JOIN cc_categorias_homologacion h ON h.id_sucursal=c.id_sucursal AND h.id_categoria=c.id_categoria
                LEFT JOIN cc_categorias_globales cg ON cg.id_categoria_global=h.id_categoria_global
                WHERE p.id_sucursal IN ($ids) ORDER BY descripcion,clave");
            while ($row = mysqli_fetch_assoc($result)) {
                $opcionesFiltro[$row['clave']] = $row['descripcion'];
            }
            if ($filtro !== '' && !isset($opcionesFiltro[$filtro])) {
                $filtro = '';
            }
            $condicionFiltro = $filtro !== '' ? " AND CONVERT(CONCAT(IF(cg.id_categoria_global IS NULL,'L:','G:'),COALESCE(CAST(cg.id_categoria_global AS CHAR),UPPER(TRIM(c.desc_categoria)))) USING utf8) COLLATE utf8_spanish_ci = CONVERT(? USING utf8) COLLATE utf8_spanish_ci" : '';
            $sql = "SELECT CONCAT(IF(cg.id_categoria_global IS NULL,'L:','G:'),COALESCE(CAST(cg.id_categoria_global AS CHAR),UPPER(TRIM(c.desc_categoria)))) clave,
                    COALESCE(cg.nombre,c.desc_categoria,'SIN CATEGORÍA') descripcion,dv.id_sucursal,
                    SUM(v.cantidad) peso,SUM(ROUND(v.cantidad*v.precio_venta,2)) venta
                FROM cc_det_ventas dv
                INNER JOIN cc_ventas v ON v.id_sucursal=dv.id_sucursal AND v.id_venta=dv.id_venta
                LEFT JOIN cc_productos p ON p.id_sucursal=v.id_sucursal AND p.codigo=v.codigo
                LEFT JOIN cc_categorias c ON c.id_sucursal=p.id_sucursal AND c.id_categoria=p.id_categoria
                LEFT JOIN cc_categorias_homologacion h ON h.id_sucursal=c.id_sucursal AND h.id_categoria=c.id_categoria
                LEFT JOIN cc_categorias_globales cg ON cg.id_categoria_global=h.id_categoria_global
                WHERE dv.id_sucursal IN ($ids) AND dv.fecha_ingreso BETWEEN ? AND ? AND dv.estatus IN (1,3) AND v.estatus<>2 $condicionFiltro
                GROUP BY clave,descripcion,dv.id_sucursal ORDER BY descripcion,clave,dv.id_sucursal";
        } else {
            $result = ventasSucQuery($link, "SELECT p.codigo,MAX(p.descripcion) descripcion FROM cc_productos p WHERE p.id_sucursal IN ($ids) GROUP BY p.codigo ORDER BY descripcion,p.codigo");
            while ($row = mysqli_fetch_assoc($result)) {
                $opcionesFiltro[$row['codigo']] = $row['codigo'] . ' - ' . $row['descripcion'];
            }
            if ($filtro !== '' && !isset($opcionesFiltro[$filtro])) {
                $filtro = '';
            }
            $condicionFiltro = $filtro !== '' ? ' AND v.codigo = ?' : '';
            $sql = "SELECT CONCAT('P:',v.codigo) clave,CONCAT(v.codigo,' - ',COALESCE(MAX(p.descripcion),'PRODUCTO SIN CATÁLOGO')) descripcion,
                    dv.id_sucursal,SUM(v.cantidad) peso,SUM(ROUND(v.cantidad*v.precio_venta,2)) venta
                FROM cc_det_ventas dv
                INNER JOIN cc_ventas v ON v.id_sucursal=dv.id_sucursal AND v.id_venta=dv.id_venta
                LEFT JOIN cc_productos p ON p.id_sucursal=v.id_sucursal AND p.codigo=v.codigo
                WHERE dv.id_sucursal IN ($ids) AND dv.fecha_ingreso BETWEEN ? AND ? AND dv.estatus IN (1,3) AND v.estatus<>2 $condicionFiltro
                GROUP BY v.codigo,dv.id_sucursal ORDER BY descripcion,v.codigo,dv.id_sucursal";
        }
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            throw new RuntimeException(mysqli_error($link));
        }
        if ($filtro !== '') {
            mysqli_stmt_bind_param($stmt, 'sss', $fecha1, $fecha2, $filtro);
        } else {
            mysqli_stmt_bind_param($stmt, 'ss', $fecha1, $fecha2);
        }
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException(mysqli_stmt_error($stmt));
        }
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $clave = $row['clave'];
            $id = (int) $row['id_sucursal'];
            $peso = (float) $row['peso'];
            $venta = (float) $row['venta'];
            if (!isset($filas[$clave])) {
                $filas[$clave] = ['descripcion' => $row['descripcion'], 'sucursales' => [], 'peso' => 0.0, 'venta' => 0.0];
            }
            $filas[$clave]['sucursales'][$id] = ['peso' => $peso, 'venta' => $venta];
            $filas[$clave]['peso'] += $peso;
            $filas[$clave]['venta'] += $venta;
            $totales[$id]['peso'] += $peso;
            $totales[$id]['venta'] += $venta;
            $totalPeso += $peso;
            $totalVenta += $venta;
        }
        mysqli_stmt_close($stmt);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Carnicería Cano"><meta name="author" content="Gerardo Bautista"><link rel="shortcut icon" href="../img/logo_1.png">
<title>Ventas por sucursal</title>
<style>@import "../css/bootstrap.css";.reporte th,.reporte td{white-space:nowrap;vertical-align:middle}.reporte thead th{text-align:center}.reporte .concepto{position:sticky;left:0;z-index:1;background:#fff}.reporte thead .concepto,.reporte tfoot .concepto{background:#f8f9fa;z-index:2}</style>
<link href="../css/navbar.css" rel="stylesheet">
</head><body>
<main><div class="container">
<?php require_once '../components/nav.php'; ?>
<div class="container-fluid py-3">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Ventas por sucursal</h1><div class="text-muted">Peso e importe de todas las sucursales, excepto Matriz.</div></div><button class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button></div>
<form method="get" class="row g-3 align-items-end filtros mb-4">
<div class="col-sm-6 col-md-2"><label class="form-label" for="fecha1">Fecha inicial</label><input class="form-control" type="date" id="fecha1" name="fecha1" value="<?php echo ventasSucH($fecha1); ?>" required></div>
<div class="col-sm-6 col-md-2"><label class="form-label" for="fecha2">Fecha final</label><input class="form-control" type="date" id="fecha2" name="fecha2" value="<?php echo ventasSucH($fecha2); ?>" required></div>
<div class="col-sm-6 col-md-2"><label class="form-label" for="agrupacion">Agrupar por</label><select class="form-select" id="agrupacion" name="agrupacion" onchange="this.form.filtro.value='';this.form.submit()"><option value="producto" <?php echo $agrupacion==='producto'?'selected':''; ?>>Producto</option><option value="categoria" <?php echo $agrupacion==='categoria'?'selected':''; ?>>Categoría</option></select></div>
<div class="col-sm-6 col-md-4"><label class="form-label" for="filtro"><?php echo $agrupacion==='producto'?'Producto':'Categoría'; ?></label><select class="form-select" id="filtro" name="filtro"><option value=""><?php echo $agrupacion==='producto'?'Todos los productos':'Todas las categorías'; ?></option><?php foreach ($opcionesFiltro as $claveOpcion=>$descripcionOpcion) { ?><option value="<?php echo ventasSucH($claveOpcion); ?>" <?php echo $filtro===$claveOpcion?'selected':''; ?>><?php echo ventasSucH($descripcionOpcion); ?></option><?php } ?></select></div>
<div class="col-sm-6 col-md-2"><button class="btn btn-primary w-100" type="submit">Consultar</button></div></form>
<?php if ($error !== '') { ?><div class="alert alert-danger">No fue posible generar el reporte: <?php echo ventasSucH($error); ?></div>
<?php } elseif (!$sucursales) { ?><div class="alert alert-warning">No hay sucursales activas para mostrar.</div>
<?php } else { ?><div class="table-responsive"><table class="table table-sm table-bordered table-hover reporte"><thead class="table-light"><tr><th rowspan="2" class="concepto text-start"><?php echo $agrupacion==='producto'?'Producto':'Categoría'; ?></th><?php foreach ($sucursales as $nombre) { ?><th colspan="2"><?php echo ventasSucH($nombre); ?></th><?php } ?><th colspan="2">Total sucursales</th></tr><tr><?php foreach ($sucursales as $nombre) { ?><th>Peso</th><th>Venta</th><?php } ?><th>Peso</th><th>Venta</th></tr></thead><tbody>
<?php if (!$filas) { ?><tr><td colspan="<?php echo 3+count($sucursales)*2; ?>" class="text-center text-muted py-4">No hay ventas en el rango seleccionado.</td></tr><?php } ?>
<?php foreach ($filas as $fila) { ?><tr><th class="concepto"><?php echo ventasSucH($fila['descripcion']); ?></th><?php foreach ($sucursales as $id=>$nombre) { $dato=$fila['sucursales'][$id]??['peso'=>0,'venta'=>0]; ?><td class="text-end"><?php echo number_format($dato['peso'],3); ?></td><td class="text-end">$<?php echo number_format($dato['venta'],2); ?></td><?php } ?><td class="text-end fw-bold"><?php echo number_format($fila['peso'],3); ?></td><td class="text-end fw-bold">$<?php echo number_format($fila['venta'],2); ?></td></tr><?php } ?></tbody>
<tfoot class="table-light fw-bold"><tr><th class="concepto">Totales</th><?php foreach ($sucursales as $id=>$nombre) { ?><td class="text-end"><?php echo number_format($totales[$id]['peso'],3); ?></td><td class="text-end">$<?php echo number_format($totales[$id]['venta'],2); ?></td><?php } ?><td class="text-end"><?php echo number_format($totalPeso,3); ?></td><td class="text-end">$<?php echo number_format($totalVenta,2); ?></td></tr></tfoot></table></div><?php } ?>
</div></div></main>
<script src="../js/bootstrap.bundle.min.js"></script>
</body></html>
