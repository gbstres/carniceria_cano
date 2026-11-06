<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['exito' => 0, 'mensaje' => 'Sesión no iniciada']);
    exit;
}
require_once "config.php";

$id_sucursal = (int) ($_SESSION["id_sucursal"] ?? 0);
$accion = trim((string) ($_REQUEST['accion'] ?? ''));

// 1. Buscar cualquier producto o insumo para agregar como ingrediente a la receta
if ($accion === 'buscar_insumos') {
    $busqueda = trim((string) ($_POST['busqueda'] ?? $_GET['busqueda'] ?? ''));
    $busquedaEsc = mysqli_real_escape_string($link, $busqueda);

    $sql = "SELECT p.codigo, p.descripcion, p.almacen, c.desc_categoria, p.para_venta
            FROM cc_productos p
            LEFT JOIN cc_categorias c ON p.id_sucursal = c.id_sucursal AND p.id_categoria = c.id_categoria
            WHERE p.id_sucursal = $id_sucursal
              AND (p.codigo LIKE '%$busquedaEsc%' OR p.descripcion LIKE '%$busquedaEsc%')
            ORDER BY p.descripcion ASC
            LIMIT 30";

    $res = mysqli_query($link, $sql);
    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $lista[] = [
                'id' => (string) $row['codigo'],
                'name' => (string) $row['descripcion'],
                'stock' => (float) $row['almacen'],
                'categoria' => (string) ($row['desc_categoria'] ?? 'Sin categoría'),
                'para_venta' => (int) ($row['para_venta'] ?? 1),
            ];
        }
    }
    echo json_encode($lista);
    exit;
}

// 2. Obtener lista de productos elaborados configurados (para autocompletar en Producción)
if ($accion === 'buscar' || isset($_POST['busqueda'])) {
    $busqueda = trim((string) ($_POST['busqueda'] ?? $_GET['busqueda'] ?? ''));
    $busquedaEsc = mysqli_real_escape_string($link, $busqueda);

    // Prioriza productos con receta en cc_recetas_produccion, o derivados en cc_derivados
    $sql = "SELECT DISTINCT p.codigo, p.descripcion, p.almacen
            FROM cc_productos p
            WHERE p.id_sucursal = $id_sucursal
              AND (p.codigo LIKE '%$busquedaEsc%' OR p.descripcion LIKE '%$busquedaEsc%')
              AND (
                  EXISTS (SELECT 1 FROM cc_recetas_produccion r WHERE r.id_sucursal = p.id_sucursal AND r.codigo_p = p.codigo)
                  OR EXISTS (SELECT 1 FROM cc_derivados d WHERE d.id_sucursal = p.id_sucursal AND d.codigo_p = p.codigo)
              )
            ORDER BY p.descripcion ASC";

    $res = mysqli_query($link, $sql);
    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $lista[] = [
                'id' => (string) $row['codigo'],
                'name' => (string) $row['descripcion'],
                'stock' => (float) $row['almacen'],
            ];
        }
    }
    echo json_encode($lista);
    exit;
}

// 3. Obtener receta e insumos de un producto elaborado
if ($accion === 'obtener_receta' || isset($_POST['codigo_p'])) {
    $codigo_p = trim((string) ($_POST['codigo_p'] ?? $_GET['codigo_p'] ?? ''));
    $codigoEsc = mysqli_real_escape_string($link, $codigo_p);

    if ($codigo_p === '') {
        echo json_encode(['exito' => 0, 'mensaje' => 'Código de producto vacío']);
        exit;
    }

    // Consultar datos del producto
    $sqlProd = mysqli_query($link, "SELECT codigo, descripcion, almacen FROM cc_productos WHERE id_sucursal = $id_sucursal AND codigo = '$codigoEsc' LIMIT 1");
    $producto = mysqli_fetch_assoc($sqlProd);

    if (!$producto) {
        echo json_encode(['exito' => 0, 'mensaje' => 'Producto no encontrado en esta sucursal']);
        exit;
    }

    // Primero consultar en cc_recetas_produccion (tabla nueva e independiente)
    $sqlInsumos = mysqli_query($link, "
        SELECT r.codigo_d, p.descripcion, r.porcentaje, p.almacen, c.desc_categoria
        FROM cc_recetas_produccion r
        INNER JOIN cc_productos p ON r.id_sucursal = p.id_sucursal AND r.codigo_d = p.codigo
        LEFT JOIN cc_categorias c ON p.id_sucursal = c.id_sucursal AND p.id_categoria = c.id_categoria
        WHERE r.id_sucursal = $id_sucursal AND r.codigo_p = '$codigoEsc'
        ORDER BY r.porcentaje DESC
    ");

    $origen = 'recetas_produccion';

    // Si no tiene en recetas_produccion, buscar fallback en cc_derivados (legacy)
    if (!$sqlInsumos || mysqli_num_rows($sqlInsumos) === 0) {
        $origen = 'derivados';
        $sqlInsumos = mysqli_query($link, "
            SELECT d.codigo_d, p.descripcion, d.porcentaje, p.almacen, c.desc_categoria
            FROM cc_derivados d
            INNER JOIN cc_productos p ON d.id_sucursal = p.id_sucursal AND d.codigo_d = p.codigo
            LEFT JOIN cc_categorias c ON p.id_sucursal = c.id_sucursal AND p.id_categoria = c.id_categoria
            WHERE d.id_sucursal = $id_sucursal AND d.codigo_p = '$codigoEsc'
            ORDER BY d.porcentaje DESC
        ");
    }

    $insumos = [];
    $sumaPorcentaje = 0.0;
    while ($rowIns = mysqli_fetch_assoc($sqlInsumos)) {
        $pct = (float) $rowIns['porcentaje'];
        $sumaPorcentaje += $pct;
        $insumos[] = [
            'codigo_d' => (string) $rowIns['codigo_d'],
            'descripcion' => (string) $rowIns['descripcion'],
            'porcentaje' => $pct,
            'almacen' => (float) $rowIns['almacen'],
            'categoria' => (string) ($rowIns['desc_categoria'] ?? ''),
        ];
    }

    if (empty($insumos)) {
        echo json_encode([
            'exito' => 0,
            'mensaje' => 'El producto "' . $producto['descripcion'] . '" no tiene receta configurada. Configúrala primero en "Admin -> Recetas de Producción".'
        ]);
        exit;
    }

    echo json_encode([
        'exito' => 1,
        'origen' => $origen,
        'producto' => [
            'codigo' => (string) $producto['codigo'],
            'descripcion' => (string) $producto['descripcion'],
            'almacen' => (float) $producto['almacen'],
        ],
        'insumos' => $insumos,
        'suma_porcentaje' => $sumaPorcentaje,
    ]);
    exit;
}

echo json_encode(['exito' => 0, 'mensaje' => 'Acción no válida']);
