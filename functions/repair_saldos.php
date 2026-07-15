<?php

if (PHP_SAPI !== 'cli') {
    session_start();
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'error' => 'No autorizado',
        ]);
        exit;
    }
}

require_once __DIR__ . "/config.php";

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

date_default_timezone_set("America/Mexico_City");

$fecha = date('Y-m-d');
$hora = date('H:i:s');
$idUsuario = 0;

if (PHP_SAPI !== 'cli') {
    $idUsuario = (int) ($_SESSION["id"] ?? 0);
}

$summary = [
    'ok' => true,
    'clientes_insertados' => 0,
    'clientes_actualizados' => 0,
    'proveedores_insertados' => 0,
    'proveedores_actualizados' => 0,
];

$sqlClientes = "INSERT INTO cc_saldos_clientes
    (id_sucursal, id_cliente, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
    SELECT c.id_sucursal, c.id_cliente, 0, 0, 0, ?, ?, ?, ?, ?, ?
    FROM cc_clientes c
    LEFT JOIN cc_saldos_clientes s
        ON s.id_sucursal = c.id_sucursal
       AND s.id_cliente = c.id_cliente
    WHERE s.id_cliente IS NULL";

if ($stmtClientes = mysqli_prepare($link, $sqlClientes)) {
    mysqli_stmt_bind_param($stmtClientes, "ississ", $idUsuario, $fecha, $hora, $idUsuario, $fecha, $hora);
    mysqli_stmt_execute($stmtClientes);
    $summary['clientes_insertados'] = mysqli_stmt_affected_rows($stmtClientes);
    mysqli_stmt_close($stmtClientes);
}

$sqlClientesAuditoria = "UPDATE cc_saldos_clientes
    SET id_usuario_act = COALESCE(id_usuario_act, id_usuario, 0),
        fecha_act = COALESCE(fecha_act, fecha_ingreso, ?),
        hora_act = COALESCE(hora_act, hora_ingreso, ?)
    WHERE id_usuario_act IS NULL
       OR fecha_act IS NULL
       OR hora_act IS NULL";

if ($stmtClientesAuditoria = mysqli_prepare($link, $sqlClientesAuditoria)) {
    mysqli_stmt_bind_param($stmtClientesAuditoria, "ss", $fecha, $hora);
    mysqli_stmt_execute($stmtClientesAuditoria);
    $summary['clientes_actualizados'] = mysqli_stmt_affected_rows($stmtClientesAuditoria);
    mysqli_stmt_close($stmtClientesAuditoria);
}

$sqlProveedores = "INSERT INTO cc_saldos_proveedores
    (id_sucursal, id_proveedor, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
    SELECT p.id_sucursal, p.id_proveedor, 0, 0, 0, ?, ?, ?, ?, ?, ?
    FROM cc_proveedores p
    LEFT JOIN cc_saldos_proveedores s
        ON s.id_sucursal = p.id_sucursal
       AND s.id_proveedor = p.id_proveedor
    WHERE s.id_proveedor IS NULL";

if ($stmtProveedores = mysqli_prepare($link, $sqlProveedores)) {
    mysqli_stmt_bind_param($stmtProveedores, "ississ", $idUsuario, $fecha, $hora, $idUsuario, $fecha, $hora);
    mysqli_stmt_execute($stmtProveedores);
    $summary['proveedores_insertados'] = mysqli_stmt_affected_rows($stmtProveedores);
    mysqli_stmt_close($stmtProveedores);
}

$sqlProveedoresAuditoria = "UPDATE cc_saldos_proveedores
    SET id_usuario_act = COALESCE(id_usuario_act, id_usuario, 0),
        fecha_act = COALESCE(fecha_act, fecha_ingreso, ?),
        hora_act = COALESCE(hora_act, hora_ingreso, ?)
    WHERE id_usuario_act IS NULL
       OR fecha_act IS NULL
       OR hora_act IS NULL";

if ($stmtProveedoresAuditoria = mysqli_prepare($link, $sqlProveedoresAuditoria)) {
    mysqli_stmt_bind_param($stmtProveedoresAuditoria, "ss", $fecha, $hora);
    mysqli_stmt_execute($stmtProveedoresAuditoria);
    $summary['proveedores_actualizados'] = mysqli_stmt_affected_rows($stmtProveedoresAuditoria);
    mysqli_stmt_close($stmtProveedoresAuditoria);
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
