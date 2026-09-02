<?php

function guardarPagosVenta($link, $id_sucursal, $id_venta, $tipo_pago, $importe_efectivo, $importe_transferencia, $importe_tarjeta, $id_usuario, $fecha, $hora) {
    $id_sucursal = (int) $id_sucursal;
    $id_venta = (int) $id_venta;
    $tipo_pago = (int) $tipo_pago;
    $importe_efectivo = round((float) $importe_efectivo, 2);
    $importe_transferencia = round((float) $importe_transferencia, 2);
    $importe_tarjeta = round((float) $importe_tarjeta, 2);
    $id_usuario = (int) $id_usuario;
    mysqli_query($link, "DELETE FROM cc_ventas_pagos WHERE id_sucursal = $id_sucursal AND id_venta = $id_venta");
    $pagos = [];
    if ($importe_efectivo > 0) $pagos[1] = $importe_efectivo;
    if ($importe_transferencia > 0) $pagos[2] = $importe_transferencia;
    if ($importe_tarjeta > 0) $pagos[3] = $importe_tarjeta;
    if (!$pagos && $tipo_pago > 0) {
        $total = mysqli_fetch_assoc(mysqli_query($link, "SELECT COALESCE(SUM(ROUND(cantidad * precio_venta, 2)), 0) total FROM cc_ventas WHERE id_sucursal = $id_sucursal AND id_venta = $id_venta AND estatus <> 2"));
        $pagos[$tipo_pago] = round((float) $total['total'], 2);
    }
    $sql = "INSERT INTO cc_ventas_pagos (id_sucursal, id_venta, tipo_pago, importe, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?)";
    foreach ($pagos as $tipo => $importe) {
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "iiidiss", $id_sucursal, $id_venta, $tipo, $importe, $id_usuario, $fecha, $hora);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    }
}

function validarPagosVenta($link, $id_sucursal, $id_venta, $tipo_pago, $importe_efectivo, $importe_transferencia, $importe_tarjeta) {
    $total = mysqli_fetch_assoc(mysqli_query($link, "SELECT COALESCE(SUM(ROUND(cantidad * precio_venta, 2)), 0) total FROM cc_ventas WHERE id_sucursal = " . (int) $id_sucursal . " AND id_venta = " . (int) $id_venta . " AND estatus <> 2"));
    $total_venta = round((float) $total['total'], 2);
    $suma = round((float) $importe_efectivo + (float) $importe_transferencia + (float) $importe_tarjeta, 2);
    if ((int) $tipo_pago === 4 && abs($suma - $total_venta) > 0.009) throw new Exception('La suma de efectivo, transferencia y tarjeta debe coincidir con el total de la venta.');
    return $total_venta;
}
