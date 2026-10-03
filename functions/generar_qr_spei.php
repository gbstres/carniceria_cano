<?php
header('Content-Type: application/json');
require_once __DIR__ . '/spei_qr_helper.php';
require_once __DIR__ . '/codi_api.php';

$monto       = isset($_GET['monto']) ? (float)$_GET['monto'] : (isset($_POST['monto']) ? (float)$_POST['monto'] : 0.0);
$id_venta    = isset($_GET['id_venta']) ? (int)$_GET['id_venta'] : (isset($_POST['id_venta']) ? (int)$_POST['id_venta'] : 1);
$id_sucursal = isset($_GET['id_sucursal']) ? (int)$_GET['id_sucursal'] : (isset($_POST['id_sucursal']) ? (int)$_POST['id_sucursal'] : 1);

if ($id_venta <= 0) $id_venta = 1;
if ($id_sucursal <= 0) $id_sucursal = 1;

$concepto   = "CC " . $id_sucursal;
$referencia = (string)$id_venta;

// 1. Intentar obtener Payload CoDi® Dinámico firmado mediante API REST de Banxico
$codiApiResult = solicitar_cobro_codi_banxico($monto, $concepto, $referencia);
$codiSigned = false;
$codiPayload = null;

if ($codiApiResult['success'] && !empty($codiApiResult['payload'])) {
    $codiSigned = true;
    $codiPayload = $codiApiResult['payload'];
} else {
    // Si la API CoDi aún no tiene llaves/certificados en codi_api.php,
    // usamos el payload estático de registro/comercio asignado a Gerardo Bautista Serna
    $codiPayload = '{"CRY": "wlVwtALPkueZM9orK1539tVUd+pFv8rNDKUbf4MIhCA=","ic": {"IDC": "3541ba0308","SER": 2,"ENC": "L5i8uXeE9bP9o5WS29Qca46i6BZI6plrDQVOqpK+lVyjRCsxdugpKAVwAW1bddFpqABFHOiGqSWRIh/OfX+b0P5juLU+2AovlIUvVVAvQGqtnCgK0iBKhHRitjoMuEtkSNq7zhKbnOh+qUu215RREaXfmfBpX1Nt0h+kkgrQgNmxXnhkUnx2xnMCvSTccHswQv+k8n5CXXn88WqxJQiFGP3+Vm6bHfOF5lQqDMajMKEdMsi6c9GALnEXGHwogym0Nx2OcSWJlB0BPwBvTf8PEw=="},"v": {"DEV": "8922398334/11"},"TYP": 19}';
}

$qrCodiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($codiPayload);

// 2. QR estático solo con la CLABE BBVA (18 dígitos)
$qrClabeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . SPEI_CLABE;

// 3. QR Dinámico EMVCo para transferencia bancaria SPEI (Monto exacto, Concepto y Referencia)
$emvPayload = generar_payload_emvco_spei(SPEI_CLABE, SPEI_BENEFICIARIO, $monto, $concepto, $referencia);
$qrDynamicUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($emvPayload);

echo json_encode([
    'clabe'          => SPEI_CLABE,
    'banco'          => SPEI_BANCO,
    'beneficiario'   => SPEI_BENEFICIARIO,
    'concepto'       => $concepto,
    'referencia'     => $referencia,
    'monto'          => sprintf("%.2f", $monto),
    'codi_signed'    => $codiSigned,
    'codi_message'   => $codiApiResult['message'],
    'qr_codi_url'    => $qrCodiUrl,
    'qr_clabe_url'   => $qrClabeUrl,
    'qr_dynamic_url' => $qrDynamicUrl
]);
