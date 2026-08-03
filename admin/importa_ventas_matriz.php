<?php
// admin/importa_ventas_matriz.php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";    // LOCAL
require_once "../functions/config_2.php";  // GCP
require_once "../functions/sync_queue.php";
date_default_timezone_set("America/Mexico_City");

$idSucursalLocal = (int) ($_SESSION["id_sucursal"] ?? 0);
$idUsuario = (int) ($_SESSION["id"] ?? 0);
if ($idSucursalLocal <= 0)
    die("Sucursal inválida.");

function getFolioPrefixMatriz(int $idSucursalMatriz): string {
    return 'M' . $idSucursalMatriz . 'V';
}

$fecha = $_POST['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
    $fecha = date('Y-m-d');

function qOrFail($link, string $sql) {
    $r = mysqli_query($link, $sql);
    if (!$r)
        throw new Exception(mysqli_error($link));
    return $r;
}

function asegurarEsquemaImportacionMatriz(mysqli $link): void {
    $rs = mysqli_query($link, "SHOW COLUMNS FROM cc_det_compras LIKE 'folio_externo'");
    if (!$rs) {
        throw new Exception("No se pudo revisar cc_det_compras: " . mysqli_error($link));
    }

    $existe = mysqli_num_rows($rs) > 0;
    mysqli_free_result($rs);

    if (!$existe) {
        qOrFail($link, "ALTER TABLE cc_det_compras ADD COLUMN folio_externo VARCHAR(30) NULL DEFAULT NULL AFTER id_compra");
    }

    qOrFail($link, "CREATE TABLE IF NOT EXISTS cc_equivalencias_productos (
        id_sucursal INT NOT NULL,
        codigo_origen VARCHAR(10) NOT NULL,
        codigo_destino VARCHAR(10) NOT NULL,
        factor DECIMAL(10,4) NOT NULL DEFAULT 1,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        id_usuario INT NOT NULL DEFAULT 0,
        fecha_ingreso DATE NOT NULL,
        hora_ingreso TIME NOT NULL,
        id_usuario_act INT NOT NULL DEFAULT 0,
        fecha_act DATE NOT NULL,
        hora_act TIME NOT NULL,
        PRIMARY KEY (id_sucursal, codigo_origen),
        KEY idx_equivalencias_destino (id_sucursal, codigo_destino),
        KEY idx_equivalencias_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci");

    $fecha = date('Y-m-d');
    $hora = date('H:i:s');
    $rsClave = mysqli_query($link, "SELECT 1 FROM cc_claves WHERE nombre_clave = 'MATRIZ_ID_SUCURSAL' LIMIT 1");
    if (!$rsClave) {
        throw new Exception("No se pudo revisar cc_claves: " . mysqli_error($link));
    }
    $existeClave = mysqli_num_rows($rsClave) > 0;
    mysqli_free_result($rsClave);

    if (!$existeClave) {
        qOrFail($link, "
            INSERT INTO cc_claves
                (nombre_clave, clave, descripcion, descripcion_corta, orden, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
            VALUES
                ('MATRIZ_ID_SUCURSAL', 3, 'Sucursal origen matriz para importar ventas', '3', 1, 0, '$fecha', '$hora', 0, '$fecha', '$hora')
        ");
    }
}

function getMatrizIdSucursal(mysqli $link): int {
    $rs = mysqli_query($link, "
        SELECT clave
        FROM cc_claves
        WHERE nombre_clave = 'MATRIZ_ID_SUCURSAL'
        ORDER BY orden ASC, clave ASC
        LIMIT 1
    ");
    if (!$rs) {
        throw new Exception("No se pudo leer la clave MATRIZ_ID_SUCURSAL: " . mysqli_error($link));
    }
    $row = mysqli_fetch_assoc($rs);
    mysqli_free_result($rs);

    $idSucursalMatriz = (int) ($row['clave'] ?? 0);
    if ($idSucursalMatriz <= 0) {
        throw new Exception("La clave MATRIZ_ID_SUCURSAL no tiene una sucursal válida.");
    }

    return $idSucursalMatriz;
}

/**
 * Proveedor LOCAL que representa a la MATRIZ.
 * Aquí usamos: cc_proveedores.clave_cliente (porque ese hace match con cc_clientes.clave_proveedor en matriz)
 * Ajusta el WHERE si tú tienes forma de identificar a la matriz (ej: nombre_proveedor='MATRIZ').
 */
function getProveedorMatrizLocal($link, int $idSucursalLocal): array {

    // ✅ OPCIÓN 1 (recomendada): si tienes un proveedor con nombre "MATRIZ"
    // Descomenta esta y comenta la opción 2.
    /*
      $rs = mysqli_query($link, "SELECT id_proveedor, nombre_proveedor, clave_cliente
      FROM cc_proveedores
      WHERE id_sucursal = $idSucursalLocal
      AND activo = 1
      AND UPPER(nombre_proveedor) LIKE '%MATRIZ%'
      ORDER BY id_proveedor ASC
      LIMIT 1");
     */

    // ✅ OPCIÓN 2 (fallback): el primer proveedor activo
    $rs = mysqli_query($link, "SELECT id_proveedor, nombre_proveedor, clave_cliente
                              FROM cc_proveedores
                              WHERE id_sucursal = $idSucursalLocal
                                AND activo = 1
                              ORDER BY id_proveedor ASC
                              LIMIT 1");

    if (!$rs)
        throw new Exception("Error proveedores local: " . mysqli_error($link));
    $row = mysqli_fetch_assoc($rs);
    mysqli_free_result($rs);

    if (!$row)
        throw new Exception("No existe proveedor (matriz) en esta sucursal. Crea el proveedor con su clave_cliente.");

    return [
        'id_proveedor' => (int) $row['id_proveedor'],
        'nombre' => (string) $row['nombre_proveedor'],
        'clave_cliente' => (string) $row['clave_cliente'], // ✅ clave que usaremos para buscar al cliente en matriz
    ];
}

/**
 * Preview de ventas en MATRIZ (GCP) que corresponden a esta sucursal,
 * usando match: matriz.cc_clientes.clave_proveedor = local.cc_proveedores.clave_cliente
 */
function getVentasMatrizPreview($link, $link2, string $fecha, string $claveClienteLocal, int $idSucursalLocal, int $idSucursalMatriz): array {

    $folioPrefix = getFolioPrefixMatriz($idSucursalMatriz);
    $fechaEsc = mysqli_real_escape_string($link2, $fecha);
    $claveEsc = mysqli_real_escape_string($link2, $claveClienteLocal);

    // clientes de matriz que representan a esta sucursal
    $rsCli = mysqli_query($link2, "
        SELECT id_cliente, nombre, apellido_paterno, apellido_materno
        FROM cc_clientes
        WHERE id_sucursal = $idSucursalMatriz
          AND clave_proveedor = '$claveEsc'
          AND activo = 1
    ");
    if (!$rsCli)
        throw new Exception("GCP clientes: " . mysqli_error($link2));

    $idsClientes = [];
    $clienteLabel = "";
    while ($r = mysqli_fetch_assoc($rsCli)) {
        $idsClientes[] = (int) $r['id_cliente'];
        if ($clienteLabel === "") {
            $clienteLabel = trim($r['nombre'] . " " . $r['apellido_paterno'] . " " . $r['apellido_materno']);
        }
    }
    mysqli_free_result($rsCli);

    if (empty($idsClientes))
        return [];

    $in = implode(",", $idsClientes);

    // encabezados ventas matriz
    $rsV = mysqli_query($link2, "
        SELECT
            dv.id_venta,
            dv.id_cliente,
            dv.tipo_pago,
            dv.pagado,
            dv.estatus,
            dv.fecha_ingreso,
            dv.hora_ingreso,
            dv.fecha_act,
            dv.hora_act
        FROM cc_det_ventas dv
        WHERE dv.id_sucursal = $idSucursalMatriz
          AND dv.id_cliente IN ($in)
          AND (dv.fecha_ingreso = '$fechaEsc' OR dv.fecha_act = '$fechaEsc')
        ORDER BY dv.id_venta ASC
    ");
    if (!$rsV)
        throw new Exception("GCP det_ventas: " . mysqli_error($link2));

    $ventas = [];
    while ($dv = mysqli_fetch_assoc($rsV)) {
        $idVenta = (int) $dv['id_venta'];

        $rsPart = mysqli_query($link2, "
            SELECT
                codigo,
                cantidad,
                precio_venta
            FROM cc_ventas
            WHERE id_sucursal = $idSucursalMatriz
              AND id_venta = $idVenta
              AND estatus = 0
            ORDER BY id_consecutivo ASC
        ");
        if (!$rsPart)
            throw new Exception("GCP detalle venta: " . mysqli_error($link2));

        $partidasOriginales = [];
        while ($p = mysqli_fetch_assoc($rsPart)) {
            $partidasOriginales[] = [
                'codigo' => (string) $p['codigo'],
                'cantidad' => (float) $p['cantidad'],
                'precio_compra' => (float) $p['precio_venta'],
            ];
        }
        mysqli_free_result($rsPart);

        $partidasImportar = aplicarEquivalenciasProductos(
            $link,
            $idSucursalLocal,
            expandirPartidasConDerivados($link, $idSucursalLocal, $partidasOriginales)
        );

        $detalle = [];
        $total = 0.0;
        $piezas = 0.0;
        foreach ($partidasImportar as $p) {
            $codigo = (string) $p['codigo'];
            $cantidad = (float) $p['cantidad'];
            $precioCompra = (float) $p['precio_compra'];
            $producto = getProductoLocal($link, $idSucursalLocal, $codigo);
            $detalle[] = [
                'codigo' => $codigo,
                'descripcion' => (string) ($producto['descripcion'] ?? 'No existe en sucursal'),
                'cantidad' => $cantidad,
                'precio_compra' => $precioCompra,
            ];
            $piezas += $cantidad;
            $total += $precioCompra * $cantidad;
        }

        $ventas[] = [
            'id_venta' => $idVenta,
            'clave_externa' => $folioPrefix . $idVenta,
            'cliente' => $clienteLabel,
            'tipo_pago' => (int) $dv['tipo_pago'],
            'pagado' => (int) $dv['pagado'],
            'estatus' => (int) $dv['estatus'],
            'fecha' => (string) ($dv['fecha_act'] ?: $dv['fecha_ingreso']),
            'hora' => (string) ($dv['hora_act'] ?: $dv['hora_ingreso']),
            'piezas' => $piezas,
            'total' => $total,
            'detalle' => $detalle,
        ];
    }
    mysqli_free_result($rsV);

    return $ventas;
}

function yaImportadaLocal($link, int $idSucursalLocal, string $folioExterno): bool {
    $ce = mysqli_real_escape_string($link, $folioExterno);
    $rs = mysqli_query($link, "SELECT 1 FROM cc_det_compras
                              WHERE id_sucursal = $idSucursalLocal
                                AND folio_externo = '$ce'
                              LIMIT 1");
    if (!$rs)
        throw new Exception("Local check importada: " . mysqli_error($link));
    $row = mysqli_fetch_row($rs);
    mysqli_free_result($rs);
    return (bool) $row;
}

function importarVenta($link, $link2, int $idSucursalLocal, int $idUsuario, int $idProveedorLocal, int $idVentaMat, int $idSucursalMatriz): void {

    $folioExterno = getFolioPrefixMatriz($idSucursalMatriz) . $idVentaMat;
    $folioEsc = mysqli_real_escape_string($link, $folioExterno);

// ✅ ya importada (AHORA se valida en el encabezado cc_det_compras)
    $chk = mysqli_query($link, "SELECT 1
                            FROM cc_det_compras
                            WHERE id_sucursal = $idSucursalLocal
                              AND folio_externo = '$folioEsc'
                            LIMIT 1");
    if (!$chk)
        throw new Exception("Local check importada: " . mysqli_error($link));
    $ya = mysqli_fetch_row($chk);
    mysqli_free_result($chk);
    if ($ya)
        return;

    // id_compra nuevo
    $rsMax = qOrFail($link, "SELECT COALESCE(MAX(id_compra),0) AS mx
                            FROM cc_det_compras
                            WHERE id_sucursal = $idSucursalLocal");
    $mx = mysqli_fetch_assoc($rsMax);
    mysqli_free_result($rsMax);
    $idCompraLocal = (int) $mx['mx'] + 1;

    // tipo_pago desde matriz
    $rsDV = mysqli_query($link2, "SELECT tipo_pago
                                 FROM cc_det_ventas
                                 WHERE id_sucursal = $idSucursalMatriz AND id_venta = $idVentaMat
                                 LIMIT 1");
    if (!$rsDV)
        throw new Exception("GCP tipo_pago: " . mysqli_error($link2));
    $dv = mysqli_fetch_assoc($rsDV);
    mysqli_free_result($rsDV);
    $tipoPago = (int) ($dv['tipo_pago'] ?? 0);

    // 1) Partidas originales desde matriz
    $rsPart = mysqli_query($link2, "
        SELECT codigo, cantidad, precio_venta
        FROM cc_ventas
        WHERE id_sucursal = $idSucursalMatriz
          AND id_venta = $idVentaMat
          AND estatus = 0
        ORDER BY id_consecutivo ASC
    ");
    if (!$rsPart)
        throw new Exception("GCP partidas: " . mysqli_error($link2));

    $partidas = [];
    while ($p = mysqli_fetch_assoc($rsPart)) {
        $partidas[] = [
            'codigo' => (string) $p['codigo'],
            'cantidad' => (float) $p['cantidad'],
            'precio_compra' => (float) $p['precio_venta'], // costo para sucursal
        ];
    }
    mysqli_free_result($rsPart);

    if (empty($partidas)) {
        // sin partidas, no generamos compra
        return;
    }

    // 2) Expandir por derivados (si aplica) y resolver equivalencias de inventario.
    $partidasExp = expandirPartidasConDerivados($link, $idSucursalLocal, $partidas);
    $partidasExp = aplicarEquivalenciasProductos($link, $idSucursalLocal, $partidasExp);

    // 3) Validar que todos los códigos existan en cc_productos (si falta 1, no generar compra)
    $faltantes = [];
    foreach ($partidasExp as $p) {
        $codigo = (string) $p['codigo'];
        $prod = getProductoLocal($link, $idSucursalLocal, $codigo);
        if (!$prod)
            $faltantes[] = $codigo;
    }
    if (!empty($faltantes)) {
        // IMPORTANTe: no generamos compra
        // Si quieres, aquí puedes registrar en una tabla de log de importación fallida.
        throw new Exception("No se generó la compra. Códigos no existentes en sucursal: " . implode(", ", array_unique($faltantes)));
    }

    // 4) Insertar encabezado compra con estatus=1 (tu regla)
    $hoy = date('Y-m-d');
    $ahora = date('H:i:s');

    qOrFail($link, "
  INSERT INTO cc_det_compras
    (id_sucursal, id_compra, folio_externo, estatus, id_cierre, id_proveedor, id_empleado, tipo_pago,
     id_usuario, fecha_ingreso, hora_ingreso)
  VALUES
    ($idSucursalLocal, $idCompraLocal, '$folioEsc', 1, 0, $idProveedorLocal, 0, $tipoPago,
     $idUsuario, '$hoy', '$ahora')
");
    cc_sync_enqueue($link, $idSucursalLocal, 'compra_detalle', 'upsert', [
        'id_compra' => $idCompraLocal,
    ], [
        'tabla' => 'cc_det_compras',
        'motivo' => 'importacion_matriz',
        'folio_externo' => $folioExterno,
        'id_venta_matriz' => $idVentaMat,
    ]);

    // 5) Insertar partidas + actualizar stock
    $consec = 1;
    foreach ($partidasExp as $p) {
        $codigo = mysqli_real_escape_string($link, (string) $p['codigo']);
        $cantidad = (float) $p['cantidad'];
        $precioCompra = (float) $p['precio_compra'];

        qOrFail($link, "
          INSERT INTO cc_compras
            (id_sucursal, id_compra, id_consecutivo, codigo, precio_compra, cantidad, clave_externa, estatus,
             id_usuario, fecha_ingreso, hora_ingreso)
          VALUES
            ($idSucursalLocal, $idCompraLocal, $consec, '$codigo', $precioCompra, $cantidad, '$folioEsc', 0,
             $idUsuario, '$hoy', '$ahora')
        ");
        cc_sync_enqueue($link, $idSucursalLocal, 'compra', 'upsert', [
            'id_compra' => $idCompraLocal,
            'id_consecutivo' => $consec,
        ], [
            'tabla' => 'cc_compras',
            'motivo' => 'importacion_matriz',
            'folio_externo' => $folioExterno,
            'id_venta_matriz' => $idVentaMat,
        ]);

        // ✅ actualizar stock según reglas
        aplicarStock($link, $idSucursalLocal, (string) $p['codigo'], $cantidad, $idCompraLocal, $folioExterno, $idVentaMat);

        $consec++;
    }
}

/**
 * Obtiene producto local por código. Regresa null si no existe.
 */
function getProductoLocal(mysqli $link, int $idSucursalLocal, string $codigo): ?array {
    $st = mysqli_prepare($link, "
        SELECT codigo, descripcion, centralizar_almacen, id_categoria
        FROM cc_productos
        WHERE id_sucursal = ?
          AND codigo = ?
        LIMIT 1
    ");
    if (!$st)
        throw new Exception("Prepare getProductoLocal: " . mysqli_error($link));
    mysqli_stmt_bind_param($st, "is", $idSucursalLocal, $codigo);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $row = mysqli_fetch_assoc($res) ?: null;
    mysqli_free_result($res);
    mysqli_stmt_close($st);
    return $row;
}

function getEquivalenciaProducto(mysqli $link, int $idSucursalLocal, string $codigo): ?array {
    $st = mysqli_prepare($link, "
        SELECT codigo_destino, factor
        FROM cc_equivalencias_productos
        WHERE id_sucursal = ?
          AND codigo_origen = ?
          AND activo = 1
        LIMIT 1
    ");
    if (!$st) {
        throw new Exception("Prepare getEquivalenciaProducto: " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($st, "is", $idSucursalLocal, $codigo);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $row = mysqli_fetch_assoc($res) ?: null;
    mysqli_free_result($res);
    mysqli_stmt_close($st);
    return $row;
}

function aplicarEquivalenciasProductos(mysqli $link, int $idSucursalLocal, array $partidas): array {
    $out = [];

    foreach ($partidas as $p) {
        $codigoOrigen = (string) $p['codigo'];
        $codigoInventario = $codigoOrigen;
        $equivalencia = getEquivalenciaProducto($link, $idSucursalLocal, $codigoOrigen);

        if ($equivalencia !== null) {
            $codigoInventario = (string) $equivalencia['codigo_destino'];
        }

        // La equivalencia homologa únicamente el código local. La cantidad y el
        // precio se conservan exactamente como vienen de la venta de matriz.
        $out[] = [
            'codigo' => $codigoInventario,
            'cantidad' => (float) $p['cantidad'],
            'precio_compra' => (float) $p['precio_compra'],
            'origen' => $p['origen'] ?? $codigoOrigen,
            'codigo_operacion' => $codigoOrigen,
        ];
    }

    return $out;
}

/**
 * Derivados: si codigo es codigo_p, regresa lista de derivados (codigo_d, porcentaje).
 * OJO: en tu tabla derivados los códigos son int(10). Aquí convertimos.
 */
function getDerivados(mysqli $link, int $idSucursalLocal, string $codigo): array {
    $codigoInt = (int) $codigo; // si tus códigos tienen ceros a la izquierda, revisamos después
    if ($codigoInt <= 0)
        return [];

    $st = mysqli_prepare($link, "
        SELECT codigo_d, porcentaje
        FROM cc_derivados
        WHERE id_sucursal = ?
          AND codigo_p = ?
        ORDER BY codigo_d ASC
    ");
    if (!$st)
        throw new Exception("Prepare getDerivados: " . mysqli_error($link));
    mysqli_stmt_bind_param($st, "ii", $idSucursalLocal, $codigoInt);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);

    $out = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = [
            'codigo_d' => (string) $r['codigo_d'], // lo usaremos contra cc_productos.codigo (varchar)
            'porcentaje' => (float) $r['porcentaje'],
        ];
    }
    mysqli_free_result($res);
    mysqli_stmt_close($st);
    return $out;
}

/**
 * Expande partidas con derivados:
 * - Si codigo tiene derivados como codigo_p -> genera líneas para cada codigo_d con qty * (porcentaje/100)
 * - Si no -> se queda igual
 */
function expandirPartidasConDerivados(mysqli $link, int $idSucursalLocal, array $partidas): array {
    // $partidas: [ ['codigo'=>..., 'cantidad'=>..., 'precio_compra'=>...], ... ]
    $out = [];

    foreach ($partidas as $p) {
        $codigo = (string) $p['codigo'];
        $qty = (float) $p['cantidad'];
        $precio = (float) $p['precio_compra'];

        $der = getDerivados($link, $idSucursalLocal, $codigo);
        if (empty($der)) {
            $out[] = ['codigo' => $codigo, 'cantidad' => $qty, 'precio_compra' => $precio, 'origen' => $codigo];
            continue;
        }

        foreach ($der as $d) {
            $share = ((float) $d['porcentaje']) / 100.0;
            if ($share <= 0)
                continue;

            $qtyD = $qty * $share;

            // precio_compra unitario: lo dejamos igual (si la cantidad se reparte proporcionalmente,
            // el costo total también queda proporcional). Si algún día quieres prorrateo distinto, lo cambiamos.
            $out[] = [
                'codigo' => (string) $d['codigo_d'],
                'cantidad' => $qtyD,
                'precio_compra' => $precio,
                'origen' => $codigo,
            ];
        }
    }

    // opcional: agrupar por código (sumar cantidades) para no insertar repetidos
    $grp = [];
    foreach ($out as $x) {
        $k = $x['codigo'] . '|' . number_format((float) $x['precio_compra'], 2, '.', '');
        if (!isset($grp[$k]))
            $grp[$k] = $x;
        else
            $grp[$k]['cantidad'] += (float) $x['cantidad'];
    }

    return array_values($grp);
}

/**
 * Aplica la suma de stock según centralizar_almacen.
 */
function aplicarStock(mysqli $link, int $idSucursalLocal, string $codigo, float $cantidad, int $idCompraLocal, string $folioExterno, int $idVentaMat): void {
    $prod = getProductoLocal($link, $idSucursalLocal, $codigo);
    if (!$prod)
        throw new Exception("Producto no existe (stock): $codigo");

    $cent = (int) $prod['centralizar_almacen'];
    $idCat = (int) $prod['id_categoria'];

    if ($cent === 1) {
        $st = mysqli_prepare($link, "
            UPDATE cc_productos
            SET almacen = almacen + ?
            WHERE id_sucursal = ?
              AND codigo = ?
            LIMIT 1
        ");
        if (!$st)
            throw new Exception("Prepare update producto almacen: " . mysqli_error($link));
        mysqli_stmt_bind_param($st, "dis", $cantidad, $idSucursalLocal, $codigo);
        mysqli_stmt_execute($st);
        if (mysqli_stmt_affected_rows($st) <= 0) {
            mysqli_stmt_close($st);
            throw new Exception("No se pudo actualizar almacen producto: $codigo");
        }
        mysqli_stmt_close($st);
        cc_sync_enqueue($link, $idSucursalLocal, 'producto', 'upsert', [
            'codigo' => $codigo,
        ], [
            'tabla' => 'cc_productos',
            'motivo' => 'importacion_matriz_stock',
            'id_compra' => $idCompraLocal,
            'folio_externo' => $folioExterno,
            'id_venta_matriz' => $idVentaMat,
        ]);
    } elseif ($cent === 2) {
        $st = mysqli_prepare($link, "
            UPDATE cc_categorias
            SET almacen = almacen + ?
            WHERE id_sucursal = ?
              AND id_categoria = ?
            LIMIT 1
        ");
        if (!$st)
            throw new Exception("Prepare update categoria almacen: " . mysqli_error($link));
        mysqli_stmt_bind_param($st, "dii", $cantidad, $idSucursalLocal, $idCat);
        mysqli_stmt_execute($st);
        if (mysqli_stmt_affected_rows($st) <= 0) {
            mysqli_stmt_close($st);
            throw new Exception("No se pudo actualizar almacen categoría id_categoria=$idCat (código $codigo)");
        }
        mysqli_stmt_close($st);
        cc_sync_enqueue($link, $idSucursalLocal, 'categoria', 'upsert', [
            'id_categoria' => $idCat,
        ], [
            'tabla' => 'cc_categorias',
            'motivo' => 'importacion_matriz_stock',
            'id_compra' => $idCompraLocal,
            'folio_externo' => $folioExterno,
            'id_venta_matriz' => $idVentaMat,
            'codigo' => $codigo,
        ]);
    } else {
        // Si llega un valor distinto (por error de datos), mejor fallar para no descontrolar almacenes.
        throw new Exception("centralizar_almacen inválido ($cent) para código $codigo");
    }
}

/* ============================
 * Flujo
 * ============================ */
$msg = "";
$error = "";
$ventasPreview = [];
$provMatriz = null;
$idSucursalMatriz = 0;

try {
    asegurarEsquemaImportacionMatriz($link);
    $idSucursalMatriz = getMatrizIdSucursal($link);
    $provMatriz = getProveedorMatrizLocal($link, $idSucursalLocal);

    if (isset($_POST['accion']) && $_POST['accion'] === 'preview') {
        $ventasPreview = getVentasMatrizPreview($link, $link2, $fecha, $provMatriz['clave_cliente'], $idSucursalLocal, $idSucursalMatriz);
    }

    if (isset($_POST['accion']) && $_POST['accion'] === 'importar') {
        if (!isset($_POST['venta']) || !is_array($_POST['venta'])) {
            throw new Exception("No seleccionaste ninguna venta para importar.");
        }

        mysqli_begin_transaction($link);
        try {
            $count = 0;
            foreach ($_POST['venta'] as $idVenta => $val) {
                if ((int) $val !== 1)
                    continue;
                $idVenta = (int) $idVenta;
                if ($idVenta <= 0)
                    continue;

                importarVenta($link, $link2, $idSucursalLocal, $idUsuario, (int) $provMatriz['id_proveedor'], $idVenta, $idSucursalMatriz);
                $count++;
            }
            mysqli_commit($link);
            $msg = "Importación OK. Se procesaron: $count venta(s). (Las ya importadas se omiten automáticamente).";
        } catch (Throwable $e) {
            mysqli_rollback($link);
            throw $e;
        }

        $ventasPreview = getVentasMatrizPreview($link, $link2, $fecha, $provMatriz['clave_cliente'], $idSucursalLocal, $idSucursalMatriz);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
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
        <title>Importar ventas de Matriz</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>

        <style>
            @import "../css/bootstrap.css";
            @import "../css/jquery.dataTables.min.css";
            @import "../css/gijgo.min.css";
            .badge-importada {
                font-size: 12px;
            }
            .btn-space {
                margin-right: 10px;
            }
            .detalle-importacion-lista .codigo {
                font-weight: 600;
            }
            .detalle-importacion-lista .sin-producto {
                color: #b02a37;
            }
            .detalle-importacion-lista .list-group-item {
                padding: 10px 12px;
            }
        </style>
        <link href="../css/navbar.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
<?php require_once "../components/nav.php" ?>

                <div class="bg-light p-4 rounded">
                    <h1 class="text-center mb-3">Importar ventas de Matriz (GCP) → Compras (Sucursal)</h1>

                        <?php if ($error): ?>
                        <div class="alert alert-danger">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                        <?php if ($msg): ?>
                        <div class="alert alert-success">
                        <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                        </div>
<?php endif; ?>

                    <div class="alert alert-secondary">
                        <b>Sucursal:</b> <?= (int) $idSucursalLocal ?> |
                        <b>Matriz (origen):</b> id_sucursal=<?= (int) $idSucursalMatriz ?> |
                        <b>Proveedor local (Matriz):</b> <?= htmlspecialchars($provMatriz['nombre'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> |
                        <b>Clave cliente (match):</b> <?= htmlspecialchars($provMatriz['clave_cliente'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <!-- PREVIEW -->
                    <form method="post" class="row g-3" action="#">
                        <input type="hidden" name="accion" value="preview">
                        <div class="col-6">
                            <label class="form-label">Seleccione la fecha:</label>
                            <input name="fecha" id="datepicker" width="276" autocomplete="off" readonly
                                   value="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?>"/>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary btn-space" type="submit">
                                Previsualizar ventas a importar
                            </button>
                        </div>
                    </form>

                    <hr>

                    <!-- IMPORT -->
                    <form method="post" action="#" onsubmit="return confirm('¿Importar las ventas seleccionadas?');">
                        <input type="hidden" name="accion" value="importar">
                        <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="table-responsive">
                            <table id="ventas" class="display" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Sel</th>
                                        <th>ID Venta</th>
                                        <th>Clave externa</th>
                                        <th>Cliente (en matriz)</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Detalle a importar</th>
                                        <th>Piezas</th>
                                        <th>Total</th>
                                        <th>Tipo pago</th>
                                        <th>Pagado</th>
                                        <th>Estatus</th>
                                        <th>¿Importada?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($ventasPreview)): ?>
                                        <?php foreach ($ventasPreview as $v): ?>
                                            <?php
                                            $ya = false;
                                            try {
                                                $ya = yaImportadaLocal($link, $idSucursalLocal, $v['clave_externa']);
                                            } catch (Throwable $e) {
                                                
                                            }
                                            ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if ($ya): ?>
                                                        <input type="checkbox" disabled>
                                                    <?php else: ?>
                                                        <input type="checkbox" name="venta[<?= (int) $v['id_venta'] ?>]" value="1" class="form-check-input">
        <?php endif; ?>
                                                </td>
                                                <td><?= (int) $v['id_venta'] ?></td>
                                                <td><?= htmlspecialchars($v['clave_externa'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($v['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($v['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($v['hora'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#detalleImportacion<?= (int) $v['id_venta'] ?>">
                                                        Ver detalle
                                                    </button>
                                                </td>
                                                <td><?= number_format((float) $v['piezas'], 3) ?></td>
                                                <td><?= number_format((float) $v['total'], 2) ?></td>
                                                <td><?= (int) $v['tipo_pago'] ?></td>
                                                <td><?= (int) $v['pagado'] ?></td>
                                                <td><?= (int) $v['estatus'] ?></td>
                                                <td class="text-center">
                                                    <?php if ($ya): ?>
                                                        <span class="badge bg-success badge-importada">Sí</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark badge-importada">No</span>
                                            <?php endif; ?>
                                                </td>
                                            </tr>
    <?php endforeach; ?>
<?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($ventasPreview)): ?>
                            <?php foreach ($ventasPreview as $v): ?>
                                <div class="modal fade" id="detalleImportacion<?= (int) $v['id_venta'] ?>" tabindex="-1"
                                     aria-labelledby="detalleImportacionLabel<?= (int) $v['id_venta'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="detalleImportacionLabel<?= (int) $v['id_venta'] ?>">
                                                    Detalle a importar - Venta <?= (int) $v['id_venta'] ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <b>Clave externa:</b> <?= htmlspecialchars($v['clave_externa'], ENT_QUOTES, 'UTF-8') ?> |
                                                    <b>Piezas:</b> <?= number_format((float) $v['piezas'], 3) ?> |
                                                    <b>Total:</b> <?= number_format((float) $v['total'], 2) ?>
                                                </div>

                                                <?php if (!empty($v['detalle'])): ?>
                                                    <ul class="list-group detalle-importacion-lista">
                                                        <?php foreach ($v['detalle'] as $d): ?>
                                                            <?php $sinProducto = ($d['descripcion'] === 'No existe en sucursal'); ?>
                                                            <li class="list-group-item <?= $sinProducto ? 'sin-producto' : '' ?>">
                                                                <div>
                                                                    <span class="codigo"><?= htmlspecialchars($d['codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                    -
                                                                    <?= htmlspecialchars($d['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                                                                </div>
                                                                <div>
                                                                    Cantidad: <?= number_format((float) $d['cantidad'], 3) ?>
                                                                    |
                                                                    Precio: <?= number_format((float) $d['precio_compra'], 2) ?>
                                                                </div>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <div class="alert alert-warning mb-0">Sin partidas.</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="mt-3">
                            <button class="btn btn-success" type="submit">
                                Importar seleccionadas
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
                        $(document).ready(function () {
                            $('#ventas').DataTable({
                                language: {
                                    "emptyTable": "No hay ventas para esa fecha / match",
                                    "info": "Mostrando _START_ a _END_ de _TOTAL_",
                                    "infoEmpty": "Mostrando 0 a 0 de 0",
                                    "infoFiltered": "(Filtrado de _MAX_ total)",
                                    "lengthMenu": "Mostrar _MENU_",
                                    "loadingRecords": "Cargando...",
                                    "processing": "Procesando...",
                                    "search": "Buscar:",
                                    "zeroRecords": "Sin resultados",
                                    "paginate": {"first": "Primero", "last": "Último", "next": "Siguiente", "previous": "Anterior"}
                                },
                                pageLength: 25,
                                order: [[1, 'asc']]
                            });
                        });

                        $('#datepicker').datepicker({
                            uiLibrary: 'bootstrap5',
                            format: 'yyyy-mm-dd'
                        });
        </script>
    </body>
</html>
