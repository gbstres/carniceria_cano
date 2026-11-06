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
require_once "sync_queue.php";

date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];
$id_usuario = (int) $_SESSION["id"];
$fecha_hoy = date('Y-m-d');
$hora_hoy = date('H:i:s');

$movimiento = (int) ($_POST['movimiento'] ?? 0);

// MOVIMIENTO 1: GUARDAR O ACTUALIZAR RECETA COMPLETA
if ($movimiento === 1) {
    $codigo_p = trim((string) ($_POST['codigo_p'] ?? ''));
    $insumos_json = $_POST['insumos'] ?? '[]';

    if ($codigo_p === '') {
        echo json_encode(['exito' => 0, 'mensaje' => 'Código de producto elaborado vacío']);
        exit;
    }

    $insumos = is_array($insumos_json) ? $insumos_json : json_decode($insumos_json, true);
    if (!is_array($insumos) || empty($insumos)) {
        echo json_encode(['exito' => 0, 'mensaje' => 'Debes agregar al menos un insumo a la receta']);
        exit;
    }

    $codigoPEsc = mysqli_real_escape_string($link, $codigo_p);

    // Iniciar transacción
    mysqli_begin_transaction($link);
    try {
        // Borrar receta anterior de este producto
        mysqli_query($link, "DELETE FROM cc_recetas_produccion WHERE id_sucursal = $id_sucursal AND codigo_p = '$codigoPEsc'");

        // Insertar nuevos insumos
        $stmt = mysqli_prepare($link, "
            INSERT INTO cc_recetas_produccion 
            (id_sucursal, codigo_p, codigo_d, porcentaje, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new Exception("Error al preparar consulta: " . mysqli_error($link));
        }

        foreach ($insumos as $ins) {
            $codD = trim((string) ($ins['codigo_d'] ?? ''));
            $pct = round((float) ($ins['porcentaje'] ?? 0), 2);
            if ($codD === '' || $pct <= 0) continue;

            mysqli_stmt_bind_param($stmt, "issdisisss",
                $id_sucursal, $codigo_p, $codD, $pct, $id_usuario, $fecha_hoy, $hora_hoy, $id_usuario, $fecha_hoy, $hora_hoy
            );
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al guardar insumo $codD: " . mysqli_stmt_error($stmt));
            }
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($link);

        echo json_encode(['exito' => 1, 'mensaje' => 'Receta de producción guardada correctamente.']);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($link);
        echo json_encode(['exito' => 0, 'mensaje' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// MOVIMIENTO 2: ELIMINAR RECETA COMPLETA
if ($movimiento === 2) {
    $codigo_p = trim((string) ($_POST['codigo_p'] ?? ''));
    if ($codigo_p === '') {
        echo json_encode(['exito' => 0, 'mensaje' => 'Código de producto no especificado']);
        exit;
    }

    $codigoPEsc = mysqli_real_escape_string($link, $codigo_p);
    $del = mysqli_query($link, "DELETE FROM cc_recetas_produccion WHERE id_sucursal = $id_sucursal AND codigo_p = '$codigoPEsc'");

    if ($del) {
        echo json_encode(['exito' => 1, 'mensaje' => 'Receta eliminada correctamente.']);
    } else {
        echo json_encode(['exito' => 0, 'mensaje' => 'Error al eliminar: ' . mysqli_error($link)]);
    }
    exit;
}

echo json_encode(['exito' => 0, 'mensaje' => 'Movimiento no válido']);
