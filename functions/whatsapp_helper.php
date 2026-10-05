<?php
/**
 * Helper para enviar mensajes de WhatsApp a través de Evolution API (GCP)
 */

if (!defined('WA_API_URL')) {
    define('WA_API_URL', 'http://34.172.184.194:8080');
}
if (!defined('WA_API_KEY')) {
    define('WA_API_KEY', 'CarniceriaCano2026ClaveSegura');
}
if (!defined('WA_INSTANCE')) {
    // Instancia limpia sin espacios
    define('WA_INSTANCE', 'pruebas');
}

/**
 * Envía un mensaje de texto / OTP por WhatsApp
 * 
 * @param string $numero Número de teléfono a 10 dígitos (ej: 5512345678) o con código de país (ej: 5215512345678)
 * @param string $mensaje Texto a enviar
 * @return array Respuesta de la API
 */
function enviarWhatsApp($numero, $mensaje) {
    // Formatear el número para México a 10 dígitos (ej: 5512345678 -> 5215512345678)
    $numeroLimpio = preg_replace('/[^0-9]/', '', $numero);
    if (strlen($numeroLimpio) === 10) {
        $numeroLimpio = '521' . $numeroLimpio;
    }

    $url = rtrim(WA_API_URL, '/') . '/message/sendText/' . rawurlencode(WA_INSTANCE);

    $payload = [
        'number' => $numeroLimpio,
        'text' => $mensaje
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . WA_API_KEY
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    return ['success' => true, 'raw' => json_decode($response, true)];
}

/**
 * Genera y envía un código OTP de prueba
 * 
 * @param string $numero
 * @param string $codigoOTP
 * @return array
 */
function enviarCodigoOTP($numero, $codigoOTP) {
    $mensaje = "🔐 *Carnicería Cano*\n\nTu código de verificación es: *{$codigoOTP}*\n\nEste código vencerá en 5 minutos. No lo compartas con nadie.";
    return enviarWhatsApp($numero, $mensaje);
}
