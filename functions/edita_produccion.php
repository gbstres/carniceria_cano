<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo json_encode(['exito' => 0, 'mensaje' => 'Sesión expirada o no iniciada']);
    exit;
}
require_once "config.php";
require_once "sync_queue.php";

date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];
$id_usuario = (int) $_SESSION["id"];
$fecha_hoy = date('Y-m-d');
$hora_hoy = date('H:i:s');

$movimiento = (int) ($_POST['movimiento'] ?? 0);

// ==========================================================
// MOVIMIENTO 1: REGISTRAR LOTE DE PRODUCCIÓN
// ==========================================================
if ($movimiento === 1) {
    $codigo_p = trim((string) ($_POST['codigo_p'] ?? ''));
    $cantidad_producida = round((float) ($_POST['cantidad'] ?? 0), 3);
    $comentario = trim((string) ($_POST['comentario'] ?? ''));
    $insumos_json = $_POST['insumos'] ?? '[]';

    if ($codigo_p === '' || $cantidad_producida <= 0) {
        echo json_encode(['exito' => 0, 'mensaje' => 'Ingresa un producto válido y una cantidad mayor a cero.']);
        exit;
    }

    $insumos = is_array($insumos_json) ? $insumos_json : json_decode($insumos_json, true);
    if (!is_array($insumos) || empty($insumos)) {
        echo json_encode(['exito' => 0, 'mensaje' => 'No se recibieron los insumos de la producción.']);
        exit;
    }

    $codigoPEsc = mysqli_real_escape_string($link, $codigo_p);
    $prodRes = mysqli_query($link, "SELECT descripcion FROM cc_productos WHERE id_sucursal = $id_sucursal AND codigo = '$codigoPEsc' LIMIT 1");
    $prodRow = mysqli_fetch_assoc($prodRes);
    if (!$prodRow) {
        echo json_encode(['exito' => 0, 'mensaje' => 'El producto a elaborar no existe en esta sucursal.']);
        exit;
    }
    $descProducto = $prodRow['descripcion'];

    // Iniciar transacción
    mysqli_begin_transaction($link);

    try {
        // 1. Obtener siguiente id_produccion
        $idRes = mysqli_query($link, "SELECT COALESCE(MAX(id_produccion), 0) + 1 AS sig_id FROM cc_produccion WHERE id_sucursal = $id_sucursal FOR UPDATE");
        $idRow = mysqli_fetch_assoc($idRes);
        $id_produccion = (int) ($idRow['sig_id'] ?? 1);

        // 2. Insertar cabecera de producción
        $stmtHead = mysqli_prepare($link, "
            INSERT INTO cc_produccion 
            (id_sucursal, id_produccion, codigo, descripcion, cantidad, comentario, estatus, id_cierre, id_usuario, fecha_ingreso, hora_ingreso)
            VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?, ?, ?)
        ");
        if (!$stmtHead) {
            throw new Exception("Error al preparar cabecera: " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmtHead, "iissdsiss", 
            $id_sucursal, $id_produccion, $codigo_p, $descProducto, $cantidad_producida, $comentario, $id_usuario, $fecha_hoy, $hora_hoy
        );
        if (!mysqli_stmt_execute($stmtHead)) {
            throw new Exception("Error al registrar producción: " . mysqli_stmt_error($stmtHead));
        }
        mysqli_stmt_close($stmtHead);

        // 3. Insertar detalle de insumos y descontar stock de cada uno
        $stmtDet = mysqli_prepare($link, "
            INSERT INTO cc_produccion_detalle
            (id_sucursal, id_produccion, id_renglon, codigo_insumo, desc_insumo, porcentaje_teorico, cantidad_utilizada)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmtDet) {
            throw new Exception("Error al preparar detalle: " . mysqli_error($link));
        }

        $renglon = 0;
        foreach ($insumos as $ins) {
            $renglon++;
            $codigo_insumo = trim((string) ($ins['codigo_d'] ?? ''));
            $cant_usada = round((float) ($ins['cantidad_utilizada'] ?? 0), 3);
            $pct_teorico = round((float) ($ins['porcentaje'] ?? 0), 2);

            if ($codigo_insumo === '' || $cant_usada < 0) {
                continue;
            }

            $codigoInsEsc = mysqli_real_escape_string($link, $codigo_insumo);
            $insQuery = mysqli_query($link, "SELECT descripcion FROM cc_productos WHERE id_sucursal = $id_sucursal AND codigo = '$codigoInsEsc' LIMIT 1");
            $insRow = mysqli_fetch_assoc($insQuery);
            $descInsumo = $insRow ? $insRow['descripcion'] : 'Insumo ' . $codigo_insumo;

            // Guardar renglón
            mysqli_stmt_bind_param($stmtDet, "iiissdd", 
                $id_sucursal, $id_produccion, $renglon, $codigo_insumo, $descInsumo, $pct_teorico, $cant_usada
            );
            if (!mysqli_stmt_execute($stmtDet)) {
                throw new Exception("Error al registrar insumo $codigo_insumo: " . mysqli_stmt_error($stmtDet));
            }

            // Descontar inventario del insumo
            $updInsumo = mysqli_query($link, "
                UPDATE cc_productos 
                SET almacen = almacen - $cant_usada, 
                    fecha_act = '$fecha_hoy', 
                    hora_act = '$hora_hoy', 
                    id_usuario_act = $id_usuario 
                WHERE id_sucursal = $id_sucursal AND codigo = '$codigoInsEsc'
            ");
            if (!$updInsumo) {
                throw new Exception("Error al actualizar stock del insumo $codigo_insumo: " . mysqli_error($link));
            }

            // Encolar sincronización del producto insumo
            cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
                'codigo' => $codigo_insumo,
            ], [
                'tabla' => 'cc_productos',
                'motivo' => 'consumo_produccion',
                'id_produccion' => $id_produccion,
            ]);
        }
        mysqli_stmt_close($stmtDet);

        // 4. Aumentar inventario del producto elaborado
        $updElaborado = mysqli_query($link, "
            UPDATE cc_productos 
            SET almacen = almacen + $cantidad_producida, 
                fecha_act = '$fecha_hoy', 
                hora_act = '$hora_hoy', 
                id_usuario_act = $id_usuario 
            WHERE id_sucursal = $id_sucursal AND codigo = '$codigoPEsc'
        ");
        if (!$updElaborado) {
            throw new Exception("Error al actualizar stock del producto terminado: " . mysqli_error($link));
        }

        // 5. Encolar sincronización del producto terminado y de la producción
        cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
            'codigo' => $codigo_p,
        ], [
            'tabla' => 'cc_productos',
            'motivo' => 'entrada_produccion',
            'id_produccion' => $id_produccion,
        ]);

        cc_sync_enqueue($link, $id_sucursal, 'produccion', 'upsert', [
            'id_produccion' => $id_produccion,
        ], [
            'tabla' => 'cc_produccion',
            'codigo' => $codigo_p,
            'cantidad' => $cantidad_producida,
        ]);

        mysqli_commit($link);

        echo json_encode([
            'exito' => 1,
            'mensaje' => 'Lote de producción #' . $id_produccion . ' registrado correctamente.',
            'id_produccion' => $id_produccion
        ]);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($link);
        echo json_encode([
            'exito' => 0,
            'mensaje' => 'Error al procesar el lote: ' . $e->getMessage()
        ]);
        exit;
    }
}

// ==========================================================
// MOVIMIENTO 2: CANCELAR LOTE DE PRODUCCIÓN (REVERTIR STOCK)
// ==========================================================
if ($movimiento === 2) {
    $id_produccion = (int) ($_POST['id_produccion'] ?? 0);

    if ($id_produccion <= 0) {
        echo json_encode(['exito' => 0, 'mensaje' => 'ID de producción inválido.']);
        exit;
    }

    mysqli_begin_transaction($link);

    try {
        // Consultar cabecera
        $headQuery = mysqli_query($link, "
            SELECT codigo, cantidad, estatus, id_cierre 
            FROM cc_produccion 
            WHERE id_sucursal = $id_sucursal AND id_produccion = $id_produccion 
            FOR UPDATE
        ");
        $head = mysqli_fetch_assoc($headQuery);

        if (!$head) {
            throw new Exception("Lote de producción no encontrado.");
        }
        if ((int) $head['estatus'] === 2) {
            throw new Exception("Este lote ya se encuentra cancelado.");
        }
        if ((int) $head['id_cierre'] > 0) {
            throw new Exception("No se puede cancelar un lote que ya forma parte de un cierre de día.");
        }

        $codigo_p = $head['codigo'];
        $cantidad_producida = (float) $head['cantidad'];
        $codigoPEsc = mysqli_real_escape_string($link, $codigo_p);

        // 1. Revertir producto elaborado (restar lo que se sumó)
        $updElab = mysqli_query($link, "
            UPDATE cc_productos 
            SET almacen = almacen - $cantidad_producida,
                fecha_act = '$fecha_hoy',
                hora_act = '$hora_hoy',
                id_usuario_act = $id_usuario
            WHERE id_sucursal = $id_sucursal AND codigo = '$codigoPEsc'
        ");
        if (!$updElab) {
            throw new Exception("Error al revertir stock del producto elaborado: " . mysqli_error($link));
        }

        // 2. Revertir insumos (devolver al stock lo que se descontó)
        $detQuery = mysqli_query($link, "
            SELECT codigo_insumo, cantidad_utilizada 
            FROM cc_produccion_detalle 
            WHERE id_sucursal = $id_sucursal AND id_produccion = $id_produccion
        ");
        while ($detRow = mysqli_fetch_assoc($detQuery)) {
            $codIns = mysqli_real_escape_string($link, $detRow['codigo_insumo']);
            $cantUsada = (float) $detRow['cantidad_utilizada'];

            $updIns = mysqli_query($link, "
                UPDATE cc_productos 
                SET almacen = almacen + $cantUsada,
                    fecha_act = '$fecha_hoy',
                    hora_act = '$hora_hoy',
                    id_usuario_act = $id_usuario
                WHERE id_sucursal = $id_sucursal AND codigo = '$codIns'
            ");
            if (!$updIns) {
                throw new Exception("Error al devolver stock del insumo $codIns: " . mysqli_error($link));
            }

            cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
                'codigo' => $detRow['codigo_insumo'],
            ], [
                'tabla' => 'cc_productos',
                'motivo' => 'cancelacion_produccion',
                'id_produccion' => $id_produccion,
            ]);
        }

        // 3. Marcar lote como cancelado (estatus = 2)
        $updHead = mysqli_query($link, "
            UPDATE cc_produccion 
            SET estatus = 2,
                id_usuario_act = $id_usuario,
                fecha_act = '$fecha_hoy',
                hora_act = '$hora_hoy'
            WHERE id_sucursal = $id_sucursal AND id_produccion = $id_produccion
        ");
        if (!$updHead) {
            throw new Exception("Error al actualizar estatus de producción: " . mysqli_error($link));
        }

        cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
            'codigo' => $codigo_p,
        ], [
            'tabla' => 'cc_productos',
            'motivo' => 'cancelacion_produccion',
            'id_produccion' => $id_produccion,
        ]);

        cc_sync_enqueue($link, $id_sucursal, 'produccion', 'upsert', [
            'id_produccion' => $id_produccion,
        ], [
            'tabla' => 'cc_produccion',
            'estatus' => 2,
        ]);

        mysqli_commit($link);

        echo json_encode([
            'exito' => 1,
            'mensaje' => 'Lote #' . $id_produccion . ' cancelado y stock restablecido exitosamente.'
        ]);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($link);
        echo json_encode([
            'exito' => 0,
            'mensaje' => 'No se pudo cancelar el lote: ' . $e->getMessage()
        ]);
        exit;
    }
}

// ==========================================================
// MOVIMIENTO 3: CONSULTAR DETALLE DE UN LOTE
// ==========================================================
if ($movimiento === 3) {
    $id_produccion = (int) ($_POST['id_produccion'] ?? 0);

    if ($id_produccion <= 0) {
        echo json_encode(['exito' => 0, 'mensaje' => 'ID inválido']);
        exit;
    }

    $headRes = mysqli_query($link, "
        SELECT p.*, u.username
        FROM cc_produccion p
        LEFT JOIN cc_users u ON p.id_usuario = u.id_usuario
        WHERE p.id_sucursal = $id_sucursal AND p.id_produccion = $id_produccion
        LIMIT 1
    ");
    $head = mysqli_fetch_assoc($headRes);

    if (!$head) {
        echo json_encode(['exito' => 0, 'mensaje' => 'Lote no encontrado']);
        exit;
    }

    $detRes = mysqli_query($link, "
        SELECT codigo_insumo, desc_insumo, porcentaje_teorico, cantidad_utilizada
        FROM cc_produccion_detalle
        WHERE id_sucursal = $id_sucursal AND id_produccion = $id_produccion
        ORDER BY id_renglon ASC
    ");
    $detalles = [];
    while ($r = mysqli_fetch_assoc($detRes)) {
        $detalles[] = [
            'codigo_insumo' => (string) $r['codigo_insumo'],
            'desc_insumo' => (string) $r['desc_insumo'],
            'porcentaje_teorico' => (float) $r['porcentaje_teorico'],
            'cantidad_utilizada' => (float) $r['cantidad_utilizada'],
        ];
    }

    echo json_encode([
        'exito' => 1,
        'cabecera' => $head,
        'detalles' => $detalles
    ]);
    exit;
}

echo json_encode(['exito' => 0, 'mensaje' => 'Movimiento no reconocido']);
