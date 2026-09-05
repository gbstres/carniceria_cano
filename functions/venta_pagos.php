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

function obtenerPagosVenta($link, $id_sucursal, $id_venta) {
    $pagos = [];
    $sql = "SELECT tipo_pago, importe FROM cc_ventas_pagos WHERE id_sucursal = " . (int) $id_sucursal . " AND id_venta = " . (int) $id_venta . " ORDER BY tipo_pago";
    $resultado = mysqli_query($link, $sql);
    while ($resultado && $pago = mysqli_fetch_assoc($resultado)) {
        $pagos[(int) $pago['tipo_pago']] = round((float) $pago['importe'], 2);
    }
    return $pagos;
}

function ventaEsPagoMixto($link, $id_sucursal, $id_venta) {
    return count(obtenerPagosVenta($link, $id_sucursal, $id_venta)) > 1;
}

function ajustarPagoUnicoVenta($link, $id_sucursal, $id_venta, $ajuste, $id_usuario, $fecha, $hora) {
    $pagos = obtenerPagosVenta($link, $id_sucursal, $id_venta);
    if (count($pagos) !== 1) return;
    $tipo_pago = (int) array_key_first($pagos);
    $nuevo_importe = max(0, round((float) $pagos[$tipo_pago] + (float) $ajuste, 2));
    $sql = "UPDATE cc_ventas_pagos SET importe = ?, id_usuario = ?, fecha_ingreso = ?, hora_ingreso = ? WHERE id_sucursal = ? AND id_venta = ? AND tipo_pago = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "dissiii", $nuevo_importe, $id_usuario, $fecha, $hora, $id_sucursal, $id_venta, $tipo_pago);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
}

function procesarCambioEstadoVenta($link, $id_sucursal, $id_venta, $id_consecutivo, $movimiento, $id_usuario, $fecha, $hora) {
    $id_sucursal = (int) $id_sucursal;
    $id_venta = (int) $id_venta;
    $id_consecutivo = (int) $id_consecutivo;
    $movimiento = (int) $movimiento;
    $es_mixto = ventaEsPagoMixto($link, $id_sucursal, $id_venta);
    $condicion = $es_mixto
        ? "id_sucursal=$id_sucursal AND id_venta=$id_venta AND estatus " . ($movimiento === 2 ? "<> 2" : "= 2")
        : "id_sucursal=$id_sucursal AND id_venta=$id_venta AND id_consecutivo=$id_consecutivo";
    $partidas = [];
    $resultado = mysqli_query($link, "SELECT id_consecutivo, ROUND(cantidad * precio_venta, 2) importe FROM cc_ventas WHERE $condicion");
    while ($resultado && $partida = mysqli_fetch_assoc($resultado)) $partidas[] = $partida;
    if (!$partidas) return ['es_mixto' => $es_mixto, 'partidas' => [], 'ajuste' => 0];
    $sql = "UPDATE cc_ventas SET estatus=$movimiento, fecha_act='" . mysqli_real_escape_string($link, $fecha) . "', hora_act='" . mysqli_real_escape_string($link, $hora) . "', id_usuario_act=" . (int) $id_usuario . " WHERE $condicion";
    if (!mysqli_query($link, $sql)) throw new Exception(mysqli_error($link));
    $ajuste = 0;
    foreach ($partidas as $partida) $ajuste += (float) $partida['importe'];
    if ($movimiento === 2) $ajuste *= -1;
    if (!$es_mixto) ajustarPagoUnicoVenta($link, $id_sucursal, $id_venta, $ajuste, $id_usuario, $fecha, $hora);
    return ['es_mixto' => $es_mixto, 'partidas' => $partidas, 'ajuste' => round($ajuste, 2)];
}
