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
    'proveedores_insertados' => 0,
];

$sqlClientes = "INSERT INTO cc_saldos_clientes
    (id_sucursal, id_cliente, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso)
    SELECT c.id_sucursal, c.id_cliente, 0, 0, 0, ?, ?, ?
    FROM cc_clientes c
    LEFT JOIN cc_saldos_clientes s
        ON s.id_sucursal = c.id_sucursal
       AND s.id_cliente = c.id_cliente
    WHERE s.id_cliente IS NULL";

if ($stmtClientes = mysqli_prepare($link, $sqlClientes)) {
    mysqli_stmt_bind_param($stmtClientes, "iss", $idUsuario, $fecha, $hora);
    mysqli_stmt_execute($stmtClientes);
    $summary['clientes_insertados'] = mysqli_stmt_affected_rows($stmtClientes);
    mysqli_stmt_close($stmtClientes);
}

$sqlProveedores = "INSERT INTO cc_saldos_proveedores
    (id_sucursal, id_proveedor, efectivo_hoy, efectivo_ayer, efectivo_mes, id_usuario, fecha_ingreso, hora_ingreso)
    SELECT p.id_sucursal, p.id_proveedor, 0, 0, 0, ?, ?, ?
    FROM cc_proveedores p
    LEFT JOIN cc_saldos_proveedores s
        ON s.id_sucursal = p.id_sucursal
       AND s.id_proveedor = p.id_proveedor
    WHERE s.id_proveedor IS NULL";

if ($stmtProveedores = mysqli_prepare($link, $sqlProveedores)) {
    mysqli_stmt_bind_param($stmtProveedores, "iss", $idUsuario, $fecha, $hora);
    mysqli_stmt_execute($stmtProveedores);
    $summary['proveedores_insertados'] = mysqli_stmt_affected_rows($stmtProveedores);
    mysqli_stmt_close($stmtProveedores);
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
