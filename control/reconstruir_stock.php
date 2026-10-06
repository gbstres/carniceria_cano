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
$resumenProductos = [];
$resumenCategorias = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id_cierre'])) {
    $id_cierre = (int) $_POST['id_cierre'];

    if ($id_cierre > 0) {
        $resCierre = mysqli_query($link, "SELECT fecha_ingreso, hora_ingreso FROM cc_cierre WHERE id_sucursal = $id_sucursal AND id_cierre = $id_cierre LIMIT 1");
        $rowCierre = mysqli_fetch_assoc($resCierre);
        $fecha_cierre = $rowCierre['fecha_ingreso'] ?? date('Y-m-d');
        $hora_cierre = $rowCierre['hora_ingreso'] ?? '00:00:00';

        // 1. Recalcular Stock de Productos
        $sqlProd = "
        UPDATE cc_productos p
        LEFT JOIN cc_cierre_stock cs 
            ON p.id_sucursal = cs.id_sucursal 
           AND p.codigo = cs.codigo COLLATE utf8_spanish_ci 
           AND cs.id_cierre = $id_cierre 
           AND cs.tipo = 'PRODUCTO'
        LEFT JOIN (
            SELECT codigo, SUM(cantidad) AS cant_vendida
            FROM cc_ventas
            WHERE id_sucursal = $id_sucursal 
              AND TIMESTAMP(fecha_ingreso, hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
              AND estatus <> 2
            GROUP BY codigo
        ) v ON p.codigo = v.codigo COLLATE utf8_spanish_ci
        LEFT JOIN (
            SELECT codigo, SUM(cantidad) AS cant_comprada
            FROM cc_compras
            WHERE id_sucursal = $id_sucursal 
              AND TIMESTAMP(fecha_ingreso, hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
              AND estatus <> 2
            GROUP BY codigo
        ) c ON p.codigo = c.codigo COLLATE utf8_spanish_ci
        LEFT JOIN (
            SELECT codigo, SUM(cantidad) AS cant_entrada
            FROM cc_entradas
            WHERE id_sucursal = $id_sucursal 
              AND TIMESTAMP(fecha_ingreso, hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
              AND estatus <> 2
            GROUP BY codigo
        ) e ON p.codigo = e.codigo COLLATE utf8_spanish_ci
        SET p.almacen = ROUND(COALESCE(cs.stock, 0) + COALESCE(c.cant_comprada, 0) + COALESCE(e.cant_entrada, 0) - COALESCE(v.cant_vendida, 0), 3)
        WHERE p.id_sucursal = $id_sucursal;
        ";

        $updProd = mysqli_query($link, $sqlProd);

        // 2. Recalcular Stock de Categorías Centralizadas
        $sqlCat = "
        UPDATE cc_categorias cat
        LEFT JOIN cc_cierre_stock cs 
            ON cat.id_sucursal = cs.id_sucursal 
           AND CAST(cat.id_categoria AS CHAR) = cs.codigo COLLATE utf8_spanish_ci 
           AND cs.id_cierre = $id_cierre 
           AND cs.tipo = 'CATEGORIA'
        LEFT JOIN (
            SELECT p.id_categoria, SUM(v.cantidad) AS cant_vendida
            FROM cc_ventas v
            INNER JOIN cc_productos p ON v.id_sucursal = p.id_sucursal AND v.codigo = p.codigo COLLATE utf8_spanish_ci
            WHERE v.id_sucursal = $id_sucursal 
              AND TIMESTAMP(v.fecha_ingreso, v.hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
              AND v.estatus <> 2
              AND p.centralizar_almacen = 2
            GROUP BY p.id_categoria
        ) v ON cat.id_categoria = v.id_categoria
        LEFT JOIN (
            SELECT p.id_categoria, SUM(c.cantidad) AS cant_comprada
            FROM cc_compras c
            INNER JOIN cc_productos p ON c.id_sucursal = p.id_sucursal AND c.codigo = p.codigo COLLATE utf8_spanish_ci
            WHERE c.id_sucursal = $id_sucursal 
              AND TIMESTAMP(c.fecha_ingreso, c.hora_ingreso) > TIMESTAMP('$fecha_cierre', '$hora_cierre')
              AND c.estatus <> 2
              AND p.centralizar_almacen = 2
            GROUP BY p.id_categoria
        ) c ON cat.id_categoria = c.id_categoria
        SET cat.almacen = ROUND(COALESCE(cs.stock, 0) + COALESCE(c.cant_comprada, 0) - COALESCE(v.cant_vendida, 0), 3)
        WHERE cat.id_sucursal = $id_sucursal;
        ";

        $updCat = mysqli_query($link, $sqlCat);

        if ($updProd && $updCat) {
            $mensaje = "El stock de productos y categorías se reconstruyó con éxito a partir del Cierre ID $id_cierre (Fecha: $fecha_cierre $hora_cierre).";

            // Encolar sincronización para GCP
            $sqlAllP = mysqli_query($link, "SELECT codigo FROM cc_productos WHERE id_sucursal = $id_sucursal");
            while ($rP = mysqli_fetch_assoc($sqlAllP)) {
                cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', ['codigo' => (string) $rP['codigo']], ['tabla' => 'cc_productos', 'motivo' => 'reconstruccion_stock']);
            }
            $sqlAllC = mysqli_query($link, "SELECT id_categoria FROM cc_categorias WHERE id_sucursal = $id_sucursal");
            while ($rC = mysqli_fetch_assoc($sqlAllC)) {
                cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', ['id_categoria' => (int) $rC['id_categoria']], ['tabla' => 'cc_categorias', 'motivo' => 'reconstruccion_stock']);
            }

            // Consultar resultados para la tabla informativa
            $qP = mysqli_query($link, "SELECT codigo, descripcion, almacen FROM cc_productos WHERE id_sucursal = $id_sucursal AND almacen > 0 ORDER BY codigo");
            while ($r = mysqli_fetch_assoc($qP)) {
                $resumenProductos[] = $r;
            }
            $qC = mysqli_query($link, "SELECT id_categoria, desc_categoria, almacen FROM cc_categorias WHERE id_sucursal = $id_sucursal AND almacen > 0 ORDER BY id_categoria");
            while ($r = mysqli_fetch_assoc($qC)) {
                $resumenCategorias[] = $r;
            }
        } else {
            $error = "Ocurrió un error al reconstruir el inventario: " . mysqli_error($link);
        }
    } else {
        $error = "Por favor selecciona un cierre válido.";
    }
}

// Cargar lista de cierres recientes para el combo
$cierres = [];
$qCierres = mysqli_query($link, "SELECT id_cierre, fecha_ingreso, hora_ingreso, comentarios FROM cc_cierre WHERE id_sucursal = $id_sucursal GROUP BY id_cierre ORDER BY id_cierre DESC LIMIT 30");
while ($row = mysqli_fetch_assoc($qCierres)) {
    $cierres[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reconstruir Stock - Carnicería Cano</title>
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

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-arrow-repeat"></i> Reconstruir e Igualar Inventario desde Cierre</h4>
            </div>
            <div class="card-body">
                <?php if ($mensaje): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>¡Éxito!</strong> <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <p class="text-muted">
                    Esta herramienta toma las existencias exactas guardadas al momento de un cierre de caja previo y le suma las compras y resta las ventas registradas posteriormente a la hora exacta de ese cierre.
                </p>

                <form method="post" action="reconstruir_stock.php" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="id_cierre" class="form-label font-weight-bold">Selecciona el Cierre de Inicio:</label>
                        <select name="id_cierre" id="id_cierre" class="form-select" required>
                            <option value="">-- Seleccionar Cierre --</option>
                            <?php foreach ($cierres as $c): ?>
                                <option value="<?php echo $c['id_cierre']; ?>">
                                    Cierre ID: <?php echo $c['id_cierre']; ?> | Fecha: <?php echo $c['fecha_ingreso']; ?> <?php echo $c['hora_ingreso']; ?> <?php echo $c['comentarios'] ? '('.$c['comentarios'].')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('¿Seguro que deseas recalcular todo el stock a partir de este cierre?');">
                            Reconstruir Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($resumenProductos) || !empty($resumenCategorias)): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Categorías Centralizadas</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Categoría</th>
                                        <th class="text-end">Stock Recalculado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumenCategorias as $cat): ?>
                                        <tr>
                                            <td><?php echo $cat['id_categoria']; ?></td>
                                            <td><?php echo htmlspecialchars($cat['desc_categoria']); ?></td>
                                            <td class="text-end font-weight-bold text-success"><?php echo number_format((float)$cat['almacen'], 3); ?> kg</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">Productos en Almacén</h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th class="text-end">Stock Recalculado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumenProductos as $prod): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($prod['codigo']); ?></td>
                                            <td><?php echo htmlspecialchars($prod['descripcion']); ?></td>
                                            <td class="text-end font-weight-bold text-primary"><?php echo number_format((float)$prod['almacen'], 3); ?> kg</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
