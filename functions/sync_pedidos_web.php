<?php
// functions/sync_pedidos_web.php
// Sincronización bidireccional de Pedidos Web entre GCP y Servidores Locales

if (!function_exists('cc_get_gcp_link')) {
    function cc_get_gcp_link(): ?mysqli {
        global $link2;
        if (isset($link2) && ($link2 instanceof mysqli) && @mysqli_ping($link2)) {
            return $link2;
        }

        $server = defined('DB_SERVER_REMOTE') ? DB_SERVER_REMOTE : '34.172.184.194';
        $user   = defined('DB_USERNAME_REMOTE') ? DB_USERNAME_REMOTE : 'usuario_remoto';
        $pass   = defined('DB_PASSWORD_REMOTE') ? DB_PASSWORD_REMOTE : 'carniceria_cano';
        $name   = defined('DB_NAME_REMOTE') ? DB_NAME_REMOTE : 'carniceria_cano';

        $conn = mysqli_init();
        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 4);
        if (@$conn->real_connect($server, $user, $pass, $name)) {
            mysqli_set_charset($conn, 'utf8');
            return $conn;
        }
        return null;
    }
}

/**
 * Extrae y sincroniza pedidos web desde GCP hacia la base de datos local.
 *
 * @param mysqli $linkLocal Conexión local a MySQL
 * @param mysqli|null $linkRemote Conexión remota a GCP MySQL
 * @param int $idSucursal ID de sucursal local (0 o 9 para todas)
 * @return array Resultado de la sincronización
 */
function cc_sync_pedidos_desde_gcp(mysqli $linkLocal, ?mysqli $linkRemote = null, int $idSucursal = 0): array {
    if (!$linkRemote) {
        $linkRemote = cc_get_gcp_link();
    }

    if (!$linkRemote) {
        return [
            'success' => false,
            'message' => 'No se pudo conectar con el servidor central de GCP (tiempo de espera agotado o sin internet).'
        ];
    }

    require_once __DIR__ . '/../storefront_logic.php';
    storefront_ensure_order_tables($linkLocal);

    // Condición de sucursal:
    // Si la sucursal es 9 (Tienda Online) o 0 (admin general), sincronizamos todas.
    // Si es una sucursal física (1 a 8), traemos los pedidos asignados a esta sucursal.
    $whereSucursal = ($idSucursal > 0 && $idSucursal != 9)
        ? "WHERE p.id_sucursal = " . (int)$idSucursal
        : "WHERE 1=1";

    // Traer pedidos pendientes (nuevo, en_preparacion) o recientes de los últimos 7 días
    $sqlRemote = "SELECT p.* FROM cc_pedidos_web p 
                  $whereSucursal 
                  AND (p.estatus IN ('nuevo', 'en_preparacion') OR p.fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                  ORDER BY p.id_pedido ASC";

    $resRemote = mysqli_query($linkRemote, $sqlRemote);
    if (!$resRemote) {
        return [
            'success' => false,
            'message' => 'Error al consultar pedidos en GCP: ' . mysqli_error($linkRemote)
        ];
    }

    $syncedOrders = 0;
    $newOrders = 0;
    $updatedOrders = 0;
    $syncedItems = 0;

    while ($order = mysqli_fetch_assoc($resRemote)) {
        $idPedido = (int)$order['id_pedido'];

        // Comprobar si ya existe localmente y si cambió de estatus
        $chkExist = mysqli_query($linkLocal, "SELECT id_pedido, estatus FROM cc_pedidos_web WHERE id_pedido = $idPedido LIMIT 1");
        $existing = ($chkExist && mysqli_num_rows($chkExist) > 0) ? mysqli_fetch_assoc($chkExist) : null;
        if ($chkExist) {
            mysqli_free_result($chkExist);
        }

        if (!$existing) {
            $newOrders++;
        } elseif ($existing['estatus'] !== $order['estatus']) {
            $updatedOrders++;
        }

        // 1. Sincronizar cliente web si existe
        if (!empty($order['id_cliente_web'])) {
            $idCW = (int)$order['id_cliente_web'];
            $resCli = mysqli_query($linkRemote, "SELECT * FROM cc_web_clientes WHERE id_cliente_web = $idCW LIMIT 1");
            if ($resCli && ($cli = mysqli_fetch_assoc($resCli))) {
                $nombreEsc = mysqli_real_escape_string($linkLocal, (string)$cli['nombre']);
                $telEsc    = mysqli_real_escape_string($linkLocal, (string)$cli['telefono']);
                $emailEsc  = mysqli_real_escape_string($linkLocal, (string)($cli['email'] ?? ''));
                $verif     = (int)($cli['verificado'] ?? 1);
                $fReg      = mysqli_real_escape_string($linkLocal, (string)($cli['fecha_registro'] ?? date('Y-m-d H:i:s')));

                mysqli_query($linkLocal, "INSERT INTO cc_web_clientes 
                    (id_cliente_web, nombre, telefono, email, verificado, fecha_registro)
                    VALUES ($idCW, '$nombreEsc', '$telEsc', '$emailEsc', $verif, '$fReg')
                    ON DUPLICATE KEY UPDATE 
                    nombre = VALUES(nombre), telefono = VALUES(telefono), email = VALUES(email), verificado = VALUES(verificado)");
                mysqli_free_result($resCli);
            }
        }

        // 2. Sincronizar dirección web si existe
        if (!empty($order['id_direccion'])) {
            $idDir = (int)$order['id_direccion'];
            $resDir = mysqli_query($linkRemote, "SELECT * FROM cc_web_direcciones WHERE id_direccion = $idDir LIMIT 1");
            if ($resDir && ($dir = mysqli_fetch_assoc($resDir))) {
                $aliasEsc = mysqli_real_escape_string($linkLocal, (string)($dir['alias'] ?? ''));
                $dirEsc   = mysqli_real_escape_string($linkLocal, (string)($dir['direccion_completa'] ?? ''));
                $refEsc   = mysqli_real_escape_string($linkLocal, (string)($dir['referencias_ubicacion'] ?? ''));
                $lat      = !empty($dir['latitud']) ? (float)$dir['latitud'] : "NULL";
                $lng      = !empty($dir['longitud']) ? (float)$dir['longitud'] : "NULL";
                $dist     = (float)($dir['distancia_km'] ?? 0);
                $fCre     = mysqli_real_escape_string($linkLocal, (string)($dir['fecha_creacion'] ?? date('Y-m-d H:i:s')));
                $idCW     = (int)($dir['id_cliente_web'] ?? 0);

                mysqli_query($linkLocal, "INSERT INTO cc_web_direcciones
                    (id_direccion, id_cliente_web, alias, direccion_completa, referencias_ubicacion, latitud, longitud, distancia_km, fecha_creacion)
                    VALUES ($idDir, $idCW, '$aliasEsc', '$dirEsc', '$refEsc', $lat, $lng, $dist, '$fCre')
                    ON DUPLICATE KEY UPDATE
                    direccion_completa = VALUES(direccion_completa), referencias_ubicacion = VALUES(referencias_ubicacion),
                    latitud = VALUES(latitud), longitud = VALUES(longitud), distancia_km = VALUES(distancia_km)");
                mysqli_free_result($resDir);
            }
        }

        // 3. Upsert pedido en local
        $cols = [];
        $vals = [];
        $updates = [];
        foreach ($order as $col => $val) {
            $cols[] = "`$col`";
            if ($val === null) {
                $vals[] = "NULL";
            } else {
                $vals[] = "'" . mysqli_real_escape_string($linkLocal, (string)$val) . "'";
            }
            if ($col !== 'id_pedido') {
                $updates[] = "`$col` = VALUES(`$col`)";
            }
        }

        $sqlOrderUpsert = "INSERT INTO cc_pedidos_web (" . implode(", ", $cols) . ") 
                           VALUES (" . implode(", ", $vals) . ") 
                           ON DUPLICATE KEY UPDATE " . implode(", ", $updates);

        if (mysqli_query($linkLocal, $sqlOrderUpsert)) {
            $syncedOrders++;
        }

        // 4. Sincronizar partidas (detalles) del pedido
        $resItems = mysqli_query($linkRemote, "SELECT * FROM cc_det_pedidos_web WHERE id_pedido = $idPedido");
        if ($resItems) {
            mysqli_query($linkLocal, "DELETE FROM cc_det_pedidos_web WHERE id_pedido = $idPedido");
            while ($item = mysqli_fetch_assoc($resItems)) {
                $idDet = (int)$item['id_detalle'];
                $cod   = mysqli_real_escape_string($linkLocal, (string)$item['codigo']);
                $desc  = mysqli_real_escape_string($linkLocal, (string)$item['descripcion']);
                $pu    = (float)$item['precio_unitario'];
                $cant  = (float)$item['cantidad'];
                $sub   = (float)$item['subtotal'];
                $com   = mysqli_real_escape_string($linkLocal, (string)($item['comentario'] ?? ''));

                $sqlItem = "INSERT INTO cc_det_pedidos_web 
                    (id_pedido, codigo, descripcion, precio_unitario, cantidad, subtotal, comentario)
                    VALUES ($idPedido, '$cod', '$desc', $pu, $cant, $sub, '$com')";
                mysqli_query($linkLocal, $sqlItem);
                $syncedItems++;
            }
            mysqli_free_result($resItems);
        }
    }
    mysqli_free_result($resRemote);

    return [
        'success' => true,
        'orders_synced' => $syncedOrders,
        'new_orders' => $newOrders,
        'updated_orders' => $updatedOrders,
        'items_synced' => $syncedItems,
        'message' => "Sincronización exitosa: $newOrders nuevos, $updatedOrders actualizados."
    ];
}

/**
 * Actualiza el estatus de un pedido en GCP cuando la sucursal local lo cambia.
 */
function cc_sync_notificar_estatus_a_gcp(int $orderId, string $newStatus): bool {
    $linkRemote = cc_get_gcp_link();
    if (!$linkRemote) {
        return false;
    }
    $statusEsc = mysqli_real_escape_string($linkRemote, $newStatus);
    $upd = mysqli_query($linkRemote, "UPDATE cc_pedidos_web SET estatus = '$statusEsc' WHERE id_pedido = " . (int)$orderId);
    return (bool)$upd;
}
