<?php

function cc_sync_queue_bootstrap(mysqli $link): bool
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return true;
    }

    $result = mysqli_query($link, "SHOW TABLES LIKE 'cc_sync_queue'");
    if (!$result) {
        return false;
    }

    $bootstrapped = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);

    return $bootstrapped;
}

function cc_sync_enqueue(mysqli $link, int $idSucursal, string $entityType, string $actionType, array $entityData, array $payload = []): bool
{
    if (!cc_sync_queue_bootstrap($link)) {
        return false;
    }

    $normalizedEntity = [];
    foreach ($entityData as $key => $value) {
        $normalizedEntity[(string) $key] = $value;
    }
    ksort($normalizedEntity);

    $eventPayload = [
        'entity' => $normalizedEntity,
        'payload' => $payload,
        'captured_at' => date('Y-m-d H:i:s'),
    ];

    $entityId = json_encode($normalizedEntity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $payloadJson = json_encode($eventPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($entityId === false || $payloadJson === false) {
        return false;
    }

    $dedupeKey = sha1($idSucursal . '|' . $entityType . '|' . $actionType . '|' . $entityId . '|' . $payloadJson);
    $entityIdEscaped = mysqli_real_escape_string($link, $entityId);
    $payloadEscaped = mysqli_real_escape_string($link, $payloadJson);
    $entityTypeEscaped = mysqli_real_escape_string($link, $entityType);
    $actionTypeEscaped = mysqli_real_escape_string($link, $actionType);
    $dedupeKeyEscaped = mysqli_real_escape_string($link, $dedupeKey);

    $sql = "INSERT INTO cc_sync_queue
        (id_sucursal, entity_type, entity_id, action_type, dedupe_key, payload, status, available_at)
        VALUES
        ($idSucursal, '$entityTypeEscaped', '$entityIdEscaped', '$actionTypeEscaped', '$dedupeKeyEscaped', '$payloadEscaped', 'pending', NOW())
        ON DUPLICATE KEY UPDATE
            payload = VALUES(payload),
            status = 'pending',
            available_at = NOW(),
            last_error = NULL,
            updated_at = NOW()";

    return mysqli_query($link, $sql) === true;
}

function cc_sync_get_entity_config(string $entityType): ?array
{
    $map = [
        'categoria' => [
            'table' => 'cc_categorias',
            'keys' => ['id_categoria'],
        ],
        'cliente' => [
            'table' => 'cc_clientes',
            'keys' => ['id_cliente'],
        ],
        'proveedor' => [
            'table' => 'cc_proveedores',
            'keys' => ['id_proveedor'],
        ],
        'producto' => [
            'table' => 'cc_productos',
            'keys' => ['codigo'],
        ],
        'derivado' => [
            'table' => 'cc_derivados',
            'keys' => ['codigo_p'],
        ],
        'cierre' => [
            'table' => 'cc_cierre',
            'keys' => ['id_cierre', 'clave'],
        ],
        'cierre_cliente' => [
            'table' => 'cc_cierre_clientes',
            'keys' => ['id_cierre', 'id_cliente'],
        ],
        'saldo_cliente' => [
            'table' => 'cc_saldos_clientes',
            'keys' => ['id_cliente'],
        ],
        'pago_cliente' => [
            'table' => 'cc_pagos_clientes',
            'keys' => ['id_cliente', 'id_pago'],
        ],
        'venta' => [
            'table' => 'cc_ventas',
            'keys' => ['id_venta', 'id_consecutivo'],
        ],
        'venta_detalle' => [
            'table' => 'cc_det_ventas',
            'keys' => ['id_venta'],
        ],
        'compra' => [
            'table' => 'cc_compras',
            'keys' => ['id_compra', 'id_consecutivo'],
        ],
        'compra_detalle' => [
            'table' => 'cc_det_compras',
            'keys' => ['id_compra'],
        ],
        'gasto' => [
            'table' => 'cc_gastos',
            'keys' => ['id_gasto'],
        ],
        'entrada' => [
            'table' => 'cc_entradas',
            'keys' => ['id_entrada'],
        ],
        'activo' => [
            'table' => 'cc_activos',
            'keys' => ['id_activo'],
        ],
        'activo_bitacora' => [
            'table' => 'cc_activos_bitacora',
            'keys' => ['id_bitacora'],
        ],
    ];

    return $map[$entityType] ?? null;
}

function cc_sync_enqueue_cierre_movimientos(mysqli $link, int $idSucursal, int $idCierre, int $estatus = 3): void
{
    $sqlVentasCierre = mysqli_query($link, "SELECT id_venta
            FROM cc_det_ventas
            WHERE id_sucursal = $idSucursal
              AND id_cierre = $idCierre
              AND estatus = $estatus");
    while ($sqlVentasCierre && $rowVenta = mysqli_fetch_assoc($sqlVentasCierre)) {
        cc_sync_enqueue($link, $idSucursal, 'venta_detalle', 'upsert', [
            'id_venta' => (int) $rowVenta['id_venta'],
        ], [
            'tabla' => 'cc_det_ventas',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }

    $sqlGastosCierre = mysqli_query($link, "SELECT id_gasto
            FROM cc_gastos
            WHERE id_sucursal = $idSucursal
              AND id_cierre = $idCierre
              AND estatus = $estatus");
    while ($sqlGastosCierre && $rowGasto = mysqli_fetch_assoc($sqlGastosCierre)) {
        cc_sync_enqueue($link, $idSucursal, 'gasto', 'upsert', [
            'id_gasto' => (int) $rowGasto['id_gasto'],
        ], [
            'tabla' => 'cc_gastos',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }

    $sqlEntradasCierre = mysqli_query($link, "SELECT id_entrada
            FROM cc_entradas
            WHERE id_sucursal = $idSucursal
              AND id_cierre = $idCierre
              AND estatus = $estatus");
    while ($sqlEntradasCierre && $rowEntrada = mysqli_fetch_assoc($sqlEntradasCierre)) {
        cc_sync_enqueue($link, $idSucursal, 'entrada', 'upsert', [
            'id_entrada' => (int) $rowEntrada['id_entrada'],
        ], [
            'tabla' => 'cc_entradas',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }

    $sqlPagosCierre = mysqli_query($link, "SELECT id_cliente, id_pago
            FROM cc_pagos_clientes
            WHERE id_sucursal = $idSucursal
              AND id_cierre = $idCierre
              AND estatus = $estatus");
    while ($sqlPagosCierre && $rowPago = mysqli_fetch_assoc($sqlPagosCierre)) {
        cc_sync_enqueue($link, $idSucursal, 'pago_cliente', 'upsert', [
            'id_cliente' => (int) $rowPago['id_cliente'],
            'id_pago' => (int) $rowPago['id_pago'],
        ], [
            'tabla' => 'cc_pagos_clientes',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }

    $sqlSaldosCliente = mysqli_query($link, "SELECT id_cliente
            FROM cc_saldos_clientes
            WHERE id_sucursal = $idSucursal");
    while ($sqlSaldosCliente && $rowSaldo = mysqli_fetch_assoc($sqlSaldosCliente)) {
        cc_sync_enqueue($link, $idSucursal, 'saldo_cliente', 'upsert', [
            'id_cliente' => (int) $rowSaldo['id_cliente'],
        ], [
            'tabla' => 'cc_saldos_clientes',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }

    $sqlProductos = mysqli_query($link, "SELECT codigo
            FROM cc_productos
            WHERE id_sucursal = $idSucursal");
    while ($sqlProductos && $rowProducto = mysqli_fetch_assoc($sqlProductos)) {
        cc_sync_enqueue($link, $idSucursal, 'producto', 'upsert', [
            'codigo' => (string) $rowProducto['codigo'],
        ], [
            'tabla' => 'cc_productos',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }

    $sqlCategorias = mysqli_query($link, "SELECT id_categoria
            FROM cc_categorias
            WHERE id_sucursal = $idSucursal");
    while ($sqlCategorias && $rowCategoria = mysqli_fetch_assoc($sqlCategorias)) {
        cc_sync_enqueue($link, $idSucursal, 'categoria', 'upsert', [
            'id_categoria' => (int) $rowCategoria['id_categoria'],
        ], [
            'tabla' => 'cc_categorias',
            'motivo' => 'cierre',
            'id_cierre' => $idCierre,
        ]);
    }
}

function cc_sync_fetch_pending(mysqli $link, int $limit = 50): array
{
    cc_sync_queue_bootstrap($link);

    $limit = max(1, min(10000, $limit));
    $result = mysqli_query($link, "SELECT * FROM cc_sync_queue
        WHERE status IN ('pending', 'error')
          AND available_at <= NOW()
        ORDER BY
          CASE entity_type
            WHEN 'categoria' THEN 1
            WHEN 'cliente' THEN 2
            WHEN 'proveedor' THEN 3
            WHEN 'producto' THEN 4
            WHEN 'derivado' THEN 5
            WHEN 'activo' THEN 6
            WHEN 'activo_bitacora' THEN 7
            WHEN 'saldo_cliente' THEN 14
            WHEN 'pago_cliente' THEN 15
            WHEN 'cierre' THEN 20
            WHEN 'cierre_cliente' THEN 21
            ELSE 10
          END ASC,
          id_sync ASC
        LIMIT $limit");

    $items = [];
    if (!$result) {
        return $items;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_free_result($result);

    return $items;
}

function cc_sync_mark_done(mysqli $link, int $idSync): void
{
    mysqli_query($link, "UPDATE cc_sync_queue
        SET status = 'done', last_error = NULL, updated_at = NOW()
        WHERE id_sync = " . (int) $idSync);
}

function cc_sync_mark_error(mysqli $link, int $idSync, string $message): void
{
    $error = mysqli_real_escape_string($link, mb_substr($message, 0, 5000));
    mysqli_query($link, "UPDATE cc_sync_queue
        SET status = 'error',
            attempts = attempts + 1,
            last_error = '$error',
            available_at = DATE_ADD(NOW(), INTERVAL 1 MINUTE),
            updated_at = NOW()
        WHERE id_sync = " . (int) $idSync);
}

function cc_sync_get_writable_columns(mysqli $link, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $result = mysqli_query($link, "SHOW COLUMNS FROM `$table`");
    if (!$result) {
        throw new RuntimeException("Local columns $table: " . mysqli_error($link));
    }

    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $extra = strtoupper((string) ($row['Extra'] ?? ''));
        if (strpos($extra, 'GENERATED') !== false) {
            continue;
        }
        $columns[] = (string) $row['Field'];
    }
    mysqli_free_result($result);

    if (empty($columns)) {
        throw new RuntimeException("No hay columnas escribibles en $table");
    }

    $cache[$table] = $columns;
    return $columns;
}

function cc_sync_load_local_rows(mysqli $link, string $table, int $idSucursal, array $entityData): array
{
    $where = ["id_sucursal = " . (int) $idSucursal];

    foreach ($entityData as $key => $value) {
        $keySafe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
        if ($keySafe === '') {
            continue;
        }

        if (is_numeric($value)) {
            $where[] = "`$keySafe` = " . (0 + $value);
        } else {
            $valueEscaped = mysqli_real_escape_string($link, (string) $value);
            $where[] = "`$keySafe` = '$valueEscaped'";
        }
    }

    $columns = cc_sync_get_writable_columns($link, $table);
    $columnList = implode(',', array_map(fn($column) => "`$column`", $columns));
    $sql = "SELECT $columnList FROM `$table` WHERE " . implode(' AND ', $where);
    $result = mysqli_query($link, $sql);
    if (!$result) {
        throw new RuntimeException("Local select $table: " . mysqli_error($link));
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);

    return $rows;
}

function cc_sync_delete_remote_rows(mysqli $linkRemote, string $table, int $idSucursal, array $entityData): void
{
    $where = ["id_sucursal = " . (int) $idSucursal];

    foreach ($entityData as $key => $value) {
        $keySafe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
        if ($keySafe === '') {
            continue;
        }

        if (is_numeric($value)) {
            $where[] = "`$keySafe` = " . (0 + $value);
        } else {
            $valueEscaped = mysqli_real_escape_string($linkRemote, (string) $value);
            $where[] = "`$keySafe` = '$valueEscaped'";
        }
    }

    $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $where);
    if (!mysqli_query($linkRemote, $sql)) {
        throw new RuntimeException("Remote delete $table: " . mysqli_error($linkRemote));
    }
}

function cc_sync_upsert_remote_rows(mysqli $linkRemote, string $table, array $rows): void
{
    foreach ($rows as $row) {
        if (array_key_exists('id_usuario_act', $row) && $row['id_usuario_act'] === null) {
            $row['id_usuario_act'] = $row['id_usuario'] ?? 0;
        }
        if (array_key_exists('fecha_act', $row) && $row['fecha_act'] === null) {
            $row['fecha_act'] = $row['fecha_ingreso'] ?? date('Y-m-d');
        }
        if (array_key_exists('hora_act', $row) && $row['hora_act'] === null) {
            $row['hora_act'] = $row['hora_ingreso'] ?? date('H:i:s');
        }

        $columns = array_keys($row);
        $columnList = implode(',', array_map(fn($column) => "`$column`", $columns));
        $values = [];

        foreach ($columns as $column) {
            $value = $row[$column];
            if ($value === null) {
                $values[] = "NULL";
            } else {
                $values[] = "'" . mysqli_real_escape_string($linkRemote, (string) $value) . "'";
            }
        }

        $updates = implode(',', array_map(fn($column) => "`$column` = VALUES(`$column`)", $columns));
        $sql = "INSERT INTO `$table` ($columnList)
            VALUES (" . implode(',', $values) . ")
            ON DUPLICATE KEY UPDATE $updates";

        if (!mysqli_query($linkRemote, $sql)) {
            throw new RuntimeException("Remote upsert $table: " . mysqli_error($linkRemote));
        }
    }
}

function cc_sync_process_item(mysqli $link, mysqli $linkRemote, array $item): void
{
    $entityType = (string) ($item['entity_type'] ?? '');
    $config = cc_sync_get_entity_config($entityType);
    if ($config === null) {
        throw new RuntimeException("Entidad no soportada: $entityType");
    }

    $entityData = json_decode((string) ($item['entity_id'] ?? '{}'), true);
    if (!is_array($entityData)) {
        throw new RuntimeException("entity_id inválido en cola");
    }

    $table = $config['table'];
    $idSucursal = (int) ($item['id_sucursal'] ?? 0);
    $actionType = (string) ($item['action_type'] ?? 'upsert');

    if ($actionType === 'delete') {
        cc_sync_delete_remote_rows($linkRemote, $table, $idSucursal, $entityData);
        return;
    }

    if ($entityType === 'derivado') {
        cc_sync_delete_remote_rows($linkRemote, $table, $idSucursal, $entityData);
    }

    $rows = cc_sync_load_local_rows($link, $table, $idSucursal, $entityData);
    if (empty($rows)) {
        // Si el registro ya no existe localmente, el origen de verdad indica que
        // tampoco debe quedar persistido en remoto.
        cc_sync_delete_remote_rows($linkRemote, $table, $idSucursal, $entityData);
        return;
    }

    cc_sync_upsert_remote_rows($linkRemote, $table, $rows);
}
