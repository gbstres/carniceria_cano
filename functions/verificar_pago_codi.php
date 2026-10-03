<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/zoho_config.php';

$id_venta = isset($_POST['id_venta']) ? (int)$_POST['id_venta'] : 0;
$monto    = isset($_POST['monto']) ? (float)$_POST['monto'] : 0.0;

if ($id_venta <= 0) {
    echo json_encode(['pagado' => false, 'error' => 'ID de venta no válido']);
    exit;
}

// 1. Obtener Access Token de Zoho Mail
$accessToken = get_zoho_access_token();

if (!$accessToken) {
    echo json_encode(['pagado' => false, 'error' => 'No se pudo autenticar con Zoho Mail API']);
    exit;
}

// 2. Consultar mensajes en Zoho Mail buscando notificaciones de CoDi / Transferencia
$accountId = ZOHO_ACCOUNT_EMAIL;
$searchKey = urlencode("codi");
$url = "https://mail.zoho.com/api/accounts/{$accountId}/messages/view?searchKey={$searchKey}&status=unseen";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$accessToken}",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$pagado = false;
$detalle = null;

if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    if (isset($data['data']) && is_array($data['data'])) {
        foreach ($data['data'] as $msg) {
            $summary = strtolower($msg['summary'] ?? '');
            $subject = strtolower($msg['subject'] ?? '');
            
            // Verificar si el correo menciona la referencia de venta o el monto
            if (strpos($summary, (string)$id_venta) !== false || strpos($subject, (string)$id_venta) !== false) {
                $pagado = true;
                $detalle = $msg;
                break;
            }
        }
    }
}

echo json_encode([
    'pagado'   => $pagado,
    'id_venta' => $id_venta,
    'monto'    => $monto,
    'detalle'  => $detalle
]);
