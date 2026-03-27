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
require_once __DIR__ . "/config_2.php";

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

function cc_backfill_log(string $message): void
{
    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
        if (function_exists('flush')) {
            flush();
        }
    }
}

function cc_backfill_table_exists(mysqli $link, string $table): bool
{
    $tableEscaped = mysqli_real_escape_string($link, $table);
    $result = mysqli_query($link, "SHOW TABLES LIKE '$tableEscaped'");
    if (!$result) {
        return false;
    }

    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);

    return $exists;
}

function cc_backfill_columns(mysqli $link, string $table): array
{
    $columns = [];
    $result = mysqli_query($link, "SHOW COLUMNS FROM `$table`");
    if (!$result) {
        return $columns;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $extra = strtoupper((string) ($row['Extra'] ?? ''));
        if (strpos($extra, 'GENERATED') !== false) {
            continue;
        }
        $columns[] = $row['Field'];
    }
    mysqli_free_result($result);

    return $columns;
}

function cc_backfill_common_columns(mysqli $localLink, mysqli $remoteLink, string $table): array
{
    $localColumns = cc_backfill_columns($localLink, $table);
    $remoteColumns = cc_backfill_columns($remoteLink, $table);

    if (empty($localColumns) || empty($remoteColumns)) {
        return [];
    }

    return array_values(array_intersect($localColumns, $remoteColumns));
}

function cc_backfill_value(mysqli $link, $value): string
{
    if ($value === null) {
        return "NULL";
    }

    return "'" . mysqli_real_escape_string($link, (string) $value) . "'";
}

function cc_backfill_normalize_row(array $row): array
{
    if (array_key_exists('id_usuario_act', $row) && $row['id_usuario_act'] === null) {
        $row['id_usuario_act'] = $row['id_usuario'] ?? 0;
    }

    if (array_key_exists('fecha_act', $row) && $row['fecha_act'] === null) {
        $row['fecha_act'] = $row['fecha_ingreso'] ?? '0000-00-00';
    }

    if (array_key_exists('hora_act', $row) && $row['hora_act'] === null) {
        $row['hora_act'] = $row['hora_ingreso'] ?? '00:00:00';
    }

    return $row;
}

function cc_backfill_identity(array $row): array
{
    $identity = [];
    foreach (['id_sucursal', 'id_cliente', 'id_proveedor', 'codigo', 'id_pago', 'id_venta', 'id_compra', 'id_gasto', 'id_entrada', 'id_cierre', 'clave'] as $key) {
        if (array_key_exists($key, $row)) {
            $identity[$key] = $row[$key];
        }
    }

    return $identity;
}

$idSucursal = 0;
$includeSaldos = false;
$onlyTable = null;
$limitRows = 0;
$offsetRows = 0;
if (PHP_SAPI === 'cli') {
    global $argv;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $idSucursal = (int) $argv[1];
    }
    foreach ($argv as $arg) {
        if ($arg === '--include-saldos') {
            $includeSaldos = true;
        } elseif (strpos($arg, '--only=') === 0) {
            $onlyTable = substr($arg, 7);
        } elseif (strpos($arg, '--limit=') === 0) {
            $limitRows = (int) substr($arg, 8);
        } elseif (strpos($arg, '--offset=') === 0) {
            $offsetRows = (int) substr($arg, 9);
        }
    }
} elseif (isset($_REQUEST['id_sucursal']) && is_numeric($_REQUEST['id_sucursal'])) {
    $idSucursal = (int) $_REQUEST['id_sucursal'];
    $includeSaldos = isset($_REQUEST['include_saldos']) && (string) $_REQUEST['include_saldos'] === '1';
    if (isset($_REQUEST['only'])) {
        $onlyTable = (string) $_REQUEST['only'];
    }
    if (isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit'])) {
        $limitRows = (int) $_REQUEST['limit'];
    }
    if (isset($_REQUEST['offset']) && is_numeric($_REQUEST['offset'])) {
        $offsetRows = (int) $_REQUEST['offset'];
    }
}

if ($idSucursal <= 0) {
    echo json_encode([
        'ok' => false,
        'error' => 'Debes indicar id_sucursal',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tables = [
    'cc_categorias',
    'cc_clientes',
    'cc_proveedores',
    'cc_productos',
    'cc_derivados',
    'cc_ventas',
    'cc_det_ventas',
    'cc_pagos_clientes',
    'cc_compras',
    'cc_det_compras',
    'cc_gastos',
    'cc_entradas',
    'cc_cierre',
    'cc_cierre_clientes',
];

if ($includeSaldos) {
    array_splice($tables, 5, 0, ['cc_saldos_clientes', 'cc_saldos_proveedores']);
}

if ($onlyTable !== null && $onlyTable !== '') {
    if (!in_array($onlyTable, $tables, true)) {
        echo json_encode([
            'ok' => false,
            'error' => 'Tabla no soportada en backfill: ' . $onlyTable,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $tables = [$onlyTable];
}

$summary = [
    'ok' => true,
    'id_sucursal' => $idSucursal,
    'include_saldos' => $includeSaldos,
    'only' => $onlyTable,
    'limit' => $limitRows,
    'offset' => $offsetRows,
    'tables_processed' => 0,
    'rows_processed' => 0,
    'tables' => [],
];

mysqli_begin_transaction($link2);

try {
    foreach ($tables as $table) {
        cc_backfill_log("Procesando tabla: $table");

        if (!cc_backfill_table_exists($link, $table)) {
            throw new RuntimeException("No existe la tabla local $table");
        }
        if (!cc_backfill_table_exists($link2, $table)) {
            throw new RuntimeException("No existe la tabla remota $table");
        }

        $columns = cc_backfill_common_columns($link, $link2, $table);
        if (empty($columns)) {
            throw new RuntimeException("No se pudieron determinar columnas comunes para $table");
        }

        if (!in_array('id_sucursal', $columns, true)) {
            throw new RuntimeException("La tabla $table no tiene id_sucursal");
        }

        $sqlSelect = "SELECT * FROM `$table` WHERE id_sucursal = $idSucursal";
        if ($limitRows > 0) {
            $sqlSelect .= " LIMIT " . max(0, $offsetRows) . ", " . $limitRows;
        }

        $result = mysqli_query($link, $sqlSelect);
        if (!$result) {
            throw new RuntimeException("Error leyendo $table local: " . mysqli_error($link));
        }

        $columnList = implode(',', array_map(fn($column) => "`$column`", $columns));
        $updateList = implode(',', array_map(fn($column) => "`$column` = VALUES(`$column`)", $columns));
        $rowsTable = 0;

        while ($row = mysqli_fetch_assoc($result)) {
            $row = cc_backfill_normalize_row($row);
            $identity = cc_backfill_identity($row);

            if (in_array($table, ['cc_saldos_clientes', 'cc_saldos_proveedores', 'cc_productos'], true)) {
                cc_backfill_log("Procesando registro en $table: " . json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            $values = [];
            foreach ($columns as $column) {
                $values[] = cc_backfill_value($link2, $row[$column]);
            }

            $sql = "INSERT INTO `$table` ($columnList)
                VALUES (" . implode(',', $values) . ")
                ON DUPLICATE KEY UPDATE $updateList";

            try {
                if (!mysqli_query($link2, $sql)) {
                    throw new RuntimeException("Error respaldando $table: " . mysqli_error($link2));
                }
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Error respaldando $table en registro " . json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ": " . $e->getMessage()
                );
            }

            $rowsTable++;
            $summary['rows_processed']++;

            if ($rowsTable % 10 === 0) {
                cc_backfill_log("Tabla $table en progreso. Filas procesadas: $rowsTable");
            }
        }

        mysqli_free_result($result);

        $summary['tables'][] = [
            'table' => $table,
            'rows' => $rowsTable,
        ];
        $summary['tables_processed']++;

        cc_backfill_log("Tabla $table completada. Filas: $rowsTable");
    }

    mysqli_commit($link2);
} catch (Throwable $e) {
    mysqli_rollback($link2);
    $summary['ok'] = false;
    $summary['error'] = $e->getMessage();
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
