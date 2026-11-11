<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $returnTo = $_SERVER['REQUEST_URI'] ?? '/reportes/bitacora_stock.php';
    header('location: ../login/login.php?return_to=' . rawurlencode($returnTo));
    exit;
}

require_once '../functions/config.php';
require_once '../functions/sync_queue.php';
date_default_timezone_set('America/Mexico_City');

$idSucursalSesion = (int) ($_SESSION['id_sucursal'] ?? 0);
$esAdministrador = function_exists('tienePermiso') ? tienePermiso('ver') : ((int)($_SESSION['rol'] ?? 0) === 1 || (int)($_SESSION['rol'] ?? 0) === 2);

$fechaInicio = $_GET['fecha_inicio'] ?? ($_POST['fecha_inicio'] ?? date('Y-m-01'));
$fechaFin = $_GET['fecha_fin'] ?? ($_POST['fecha_fin'] ?? date('Y-m-d'));
$filtroTipo = trim($_GET['tipo'] ?? ($_POST['tipo'] ?? ''));
$filtroCodigo = trim($_GET['codigo'] ?? ($_POST['codigo'] ?? ''));
$filtroSucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : (isset($_POST['id_sucursal']) ? (int)$_POST['id_sucursal'] : ($esAdministrador ? 0 : $idSucursalSesion));

if (!$esAdministrador && $filtroSucursal !== $idSucursalSesion) {
    $filtroSucursal = $idSucursalSesion;
}

// Sucursales disponibles
$sucursales = [];
$resSuc = mysqli_query($link, "SELECT id_sucursal, desc_sucursal FROM cc_sucursales WHERE activo = 1 ORDER BY id_sucursal ASC");
if ($resSuc) {
    while ($r = mysqli_fetch_assoc($resSuc)) {
        $sucursales[] = $r;
    }
}

// Construir query de consulta
$where = [];
$where[] = "b.fecha BETWEEN '" . mysqli_real_escape_string($link, $fechaInicio) . "' AND '" . mysqli_real_escape_string($link, $fechaFin) . "'";

if ($filtroSucursal > 0) {
    $where[] = "b.id_sucursal = " . (int)$filtroSucursal;
}

if ($filtroTipo !== '' && in_array($filtroTipo, ['PRODUCTO', 'CATEGORIA'], true)) {
    $where[] = "b.tipo = '" . mysqli_real_escape_string($link, $filtroTipo) . "'";
}

if ($filtroCodigo !== '') {
    $where[] = "b.id_item = '" . mysqli_real_escape_string($link, $filtroCodigo) . "'";
}

$whereSql = implode(' AND ', $where);

$sqlBitacora = "
    SELECT b.*, s.desc_sucursal 
    FROM cc_bitacora_stock b
    LEFT JOIN cc_sucursales s ON s.id_sucursal = b.id_sucursal
    WHERE $whereSql
    ORDER BY b.fecha DESC, b.hora DESC, b.id_bitacora DESC
";

$resBitacora = mysqli_query($link, $sqlBitacora);
$movimientos = [];

if ($resBitacora) {
    while ($row = mysqli_fetch_assoc($resBitacora)) {
        $movimientos[] = $row;
    }
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Bitácora de stock</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>
            @import "../css/bootstrap.css";
            .badge-producto {
                background-color: #0d6efd;
            }
            .badge-categoria {
                background-color: #6f42c1;
            }
            .ajuste-positivo {
                color: #198754;
                font-weight: bold;
            }
            .ajuste-negativo {
                color: #dc3545;
                font-weight: bold;
            }
            .motivo-box {
                max-width: 320px;
                word-wrap: break-word;
                white-space: normal;
                font-size: 0.9rem;
            }
            @media print {
                .no-print {
                    display: none !important;
                }
            }
        </style>
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <div class="no-print">
                    <?php require_once "../components/nav.php"; ?>
                </div>

                <div class="bg-light p-4 rounded">
                    <div class="col-sm-8 mx-auto">
                        <h1 class="text-center">Bitácora de stock</h1>
                    </div>
                    <br>

                    <!-- Barra de filtros y botones (idéntico al estilo de stock.php) -->
                    <div class="card mb-4 shadow-sm no-print">
                        <div class="card-body">
                            <form method="GET" action="bitacora_stock.php" class="row g-3 align-items-end">
                                <div class="col-md-3 col-sm-6">
                                    <label for="fecha_inicio" class="form-label fw-bold">Fecha Inicio:</label>
                                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <label for="fecha_fin" class="form-label fw-bold">Fecha Fin:</label>
                                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <?php if ($esAdministrador): ?>
                                    <div class="col-md-3 col-sm-6">
                                        <label for="id_sucursal" class="form-label fw-bold">Sucursal:</label>
                                        <select class="form-select" id="id_sucursal" name="id_sucursal">
                                            <option value="0">Todas las sucursales</option>
                                            <?php foreach ($sucursales as $suc): ?>
                                                <option value="<?php echo $suc['id_sucursal']; ?>" <?php echo ($filtroSucursal === (int)$suc['id_sucursal']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($suc['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="id_sucursal" value="<?php echo $idSucursalSesion; ?>">
                                <?php endif; ?>
                                <div class="<?php echo $esAdministrador ? 'col-md-3 col-sm-6' : 'col-md-6 col-sm-6'; ?>">
                                    <label for="tipo" class="form-label fw-bold">Tipo:</label>
                                    <select class="form-select" id="tipo" name="tipo">
                                        <option value="">Todos</option>
                                        <option value="PRODUCTO" <?php echo ($filtroTipo === 'PRODUCTO') ? 'selected' : ''; ?>>Productos</option>
                                        <option value="CATEGORIA" <?php echo ($filtroTipo === 'CATEGORIA') ? 'selected' : ''; ?>>Categorías</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> Consultar
                                        </button>
                                        <a href="bitacora_stock.php" class="btn btn-outline-secondary" title="Limpiar filtros">
                                            <i class="bi bi-x-circle"></i> Limpiar
                                        </a>
                                        <button type="button" class="btn btn-secondary" onclick="window.print()">
                                            <i class="bi bi-printer"></i> Imprimir
                                        </button>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="stock.php" class="btn btn-outline-primary">
                                            <i class="bi bi-box-seam me-1"></i> Reporte de Stock
                                        </a>
                                        <a href="../mantenimientos/productos.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-tag me-1"></i> Productos
                                        </a>
                                        <a href="../mantenimientos/categorias.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-grid me-1"></i> Categorías
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de Bitácora con clase display de DataTables -->
                    <div class="table-responsive">
                        <table id="tabla_bitacora" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha / Hora</th>
                                    <th>Sucursal</th>
                                    <th>Tipo</th>
                                    <th>Código / ID</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Stock Ant.</th>
                                    <th class="text-end">Stock Nuevo</th>
                                    <th class="text-end">Ajuste</th>
                                    <th>Comentario / Motivo</th>
                                    <th>Usuario que actualizó</th>
                                    <th>Origen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $mov): 
                                    $dif = (float) $mov['diferencia'];
                                    $signo = $dif > 0 ? '+' : '';
                                    $claseDif = $dif > 0 ? 'ajuste-positivo' : ($dif < 0 ? 'ajuste-negativo' : '');
                                ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $mov['id_bitacora']; ?></td>
                                    <td style="white-space: nowrap;">
                                        <strong><?php echo htmlspecialchars($mov['fecha'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($mov['hora'], ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($mov['desc_sucursal'] ?? ('Sucursal #' . $mov['id_sucursal']), ENT_QUOTES, 'UTF-8'); ?></small></td>
                                    <td>
                                        <?php if ($mov['tipo'] === 'PRODUCTO'): ?>
                                            <span class="badge badge-producto">PRODUCTO</span>
                                        <?php else: ?>
                                            <span class="badge badge-categoria">CATEGORÍA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($mov['id_item'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($mov['descripcion'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end"><?php echo number_format($mov['stock_anterior'], 3); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($mov['stock_nuevo'], 3); ?></td>
                                    <td class="text-end <?php echo $claseDif; ?>">
                                        <?php echo $signo . number_format($dif, 3); ?>
                                    </td>
                                    <td>
                                        <div class="motivo-box p-1 bg-white border rounded">
                                            <i class="bi bi-chat-quote text-muted"></i>
                                            <?php echo nl2br(htmlspecialchars($mov['motivo'], ENT_QUOTES, 'UTF-8')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-fill text-primary"></i> 
                                        <strong><?php echo htmlspecialchars($mov['usuario'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <br><small class="text-muted">ID: <?php echo $mov['id_usuario']; ?></small>
                                    </td>
                                    <td><small class="badge bg-secondary"><?php echo htmlspecialchars($mov['origen'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#tabla_bitacora').DataTable({
                    order: [[0, 'desc']],
                    pageLength: 25,
                    language: {
                        emptyTable: "No hay registros de modificaciones de stock en el período seleccionado.",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        infoEmpty: "Mostrando 0 a 0 de 0 registros",
                        infoFiltered: "(filtrado de _MAX_ registros en total)",
                        lengthMenu: "Mostrar _MENU_ registros",
                        loadingRecords: "Cargando...",
                        processing: "Procesando...",
                        search: "Buscar:",
                        zeroRecords: "Sin registros encontrados",
                        paginate: {
                            first: "Primero",
                            last: "Último",
                            next: "Siguiente",
                            previous: "Anterior"
                        }
                    }
                });
            });
        </script>
    </body>
</html>
