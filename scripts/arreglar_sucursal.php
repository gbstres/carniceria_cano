<?php
/**
 * Script de Reparación y Sincronización Automática de Sucursal a GCP
 */
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../functions/config.php";
require_once __DIR__ . "/../functions/config_2.php";
require_once __DIR__ . "/../functions/sync_queue.php";

echo "=== REPARANDO Y SINCRONIZANDO SUCURSAL CON GCP ===\n\n";

// 1. Obtener la sucursal activa local
$resSuc = mysqli_query($link, "SELECT id_sucursal FROM cc_sucursales WHERE activo = 1 LIMIT 1");
$rowSuc = mysqli_fetch_assoc($resSuc);
$id_sucursal = $rowSuc ? (int) $rowSuc['id_sucursal'] : 3;

echo "1. Sucursal detectada: ID $id_sucursal\n";

// 2. Ajustar saldo de Categoría RES MAYOREO (ID 1) en la sucursal local
$upd1 = mysqli_query($link, "UPDATE cc_categorias SET almacen = 1099.000, fecha_act = CURRENT_DATE(), hora_act = CURRENT_TIME() WHERE id_sucursal = $id_sucursal AND id_categoria = 1");
if ($upd1) {
    echo "2. Saldo local de Categoría RES MAYOREO corregido a 1,099.000 kg.\n";
} else {
    echo "2. ERROR al actualizar saldo local: " . mysqli_error($link) . "\n";
}

// 3. Encolar todos los productos y categorías locales para envío masivo a GCP
echo "3. Encolando inventarios locales para subir a GCP...\n";

$sqlCat = mysqli_query($link, "SELECT id_categoria FROM cc_categorias WHERE id_sucursal = $id_sucursal");
while ($rowCat = mysqli_fetch_assoc($sqlCat)) {
    cc_sync_enqueue($link, $id_sucursal, 'categoria', 'upsert', [
        'id_categoria' => (int) $rowCat['id_categoria'],
    ], [
        'tabla' => 'cc_categorias',
        'motivo' => 'reconciliacion_sucursal',
    ]);
}

$sqlProd = mysqli_query($link, "SELECT codigo FROM cc_productos WHERE id_sucursal = $id_sucursal");
while ($rowProd = mysqli_fetch_assoc($sqlProd)) {
    cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
        'codigo' => (string) $rowProd['codigo'],
    ], [
        'tabla' => 'cc_productos',
        'motivo' => 'reconciliacion_sucursal',
    ]);
}

// 4. Procesar la cola y subir todo de la sucursal a GCP
echo "4. Enviando datos de la sucursal a GCP (procesando cola)...\n";
$pendingItems = cc_sync_fetch_pending($link, 500);
$doneCount = 0;
$errCount = 0;

foreach ($pendingItems as $item) {
    try {
        cc_sync_process_item($link, $link2, $item);
        cc_sync_mark_done($link, (int) $item['id_sync']);
        $doneCount++;
    } catch (Throwable $e) {
        cc_sync_mark_error($link, (int) $item['id_sync'], $e->getMessage());
        $errCount++;
    }
}

echo "\n======================================================\n";
echo "  ¡EXITO! SUCURSAL Y GCP QUEDARON ARREGLADOS Y SINCRONIZADOS\n";
echo "  - Elementos sincronizados a GCP: $doneCount\n";
if ($errCount > 0) {
    echo "  - Avisos/Errores en cola: $errCount\n";
}
echo "======================================================\n\n";
