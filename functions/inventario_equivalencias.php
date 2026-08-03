<?php

/**
 * Devuelve el único código que debe recibir el movimiento de inventario.
 * La equivalencia homologa el código sin modificar la cantidad del movimiento.
 */
function cc_codigo_inventario(mysqli $link, int $idSucursal, string $codigo): string
{
    $stmt = mysqli_prepare($link, "
        SELECT codigo_destino
        FROM cc_equivalencias_productos
        WHERE id_sucursal = ?
          AND codigo_origen = ?
          AND activo = 1
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('No se pudo consultar la equivalencia: ' . mysqli_error($link));
    }

    mysqli_stmt_bind_param($stmt, 'is', $idSucursal, $codigo);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $equivalencia = mysqli_fetch_assoc($resultado);
    mysqli_free_result($resultado);
    mysqli_stmt_close($stmt);

    return $equivalencia ? (string) $equivalencia['codigo_destino'] : $codigo;
}

