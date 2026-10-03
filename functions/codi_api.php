<?php
/**
 * Módulo de Integración con el Servicio Web REST API de CoDi® (Banxico / Banco Acreditar)
 * Proyecto: Carnicería Cano
 */

// Configuración de Credenciales CoDi® de Banxico
define('CODI_ENV', 'sandbox'); // 'production' o 'sandbox'
define('CODI_DEV_ID', '8922398334/11'); // Identificador de Dispositivo/Comercio asignado por Banxico
define('CODI_API_KEY', ''); // Clave de API / Secret Token si utiliza Gateway Bancario (ej. BBVA CoDi API)
define('CODI_CERT_PATH', __DIR__ . '/certs/codi_cert.pem'); // Ruta al certificado mTLS (.pem)
define('CODI_KEY_PATH', __DIR__ . '/certs/codi_key.pem');   // Ruta a la llave privada mTLS (.key)
define('CODI_KEY_PASSPHRASE', ''); // Contraseña de la llave privada si aplica

define('BANXICO_CODI_PROD_URL', 'https://www.banxico.org.mx/CoDi/v1/cobros');
define('BANXICO_CODI_DEV_URL',  'https://www.banxico.org.mx/CoDiDev/v1/cobros');

/**
 * Solicita una petición de cobro dinámico a la API Oficial de CoDi (Banxico)
 * 
 * @param float $monto Importe exacto de la venta (ej. 52.00)
 * @param string $concepto Concepto del cobro (ej. "CC 5")
 * @param int|string $referencia Referencia numérica (ej. 52)
 * @return array ['success' => bool, 'payload' => string|null, 'message' => string]
 */
function solicitar_cobro_codi_banxico($monto, $concepto, $referencia) {
    $montoFormatted = number_format((float)$monto, 2, '.', '');
    $referenciaInt = (int)$referencia;
    
    // Verificar si existen los certificados de producción o sandbox para mTLS
    $hasCert = file_exists(CODI_CERT_PATH) && file_exists(CODI_KEY_PATH);

    if (!empty(CODI_API_KEY) || $hasCert) {
        $endpoint = (CODI_ENV === 'production') ? BANXICO_CODI_PROD_URL : BANXICO_CODI_DEV_URL;
        
        $requestBody = [
            'v' => [
                'DEV' => CODI_DEV_ID,
                'TYP' => 19, // Tipo de mensaje 19: Cobro Presencial Dinámico
                'am'  => (float)$montoFormatted,
                'cn'  => substr($concepto, 0, 40),
                'ref' => $referenciaInt
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        if (!empty(CODI_API_KEY)) {
            $headers[] = 'Authorization: Bearer ' . CODI_API_KEY;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($hasCert) {
            curl_setopt($ch, CURLOPT_SSLCERT, CODI_CERT_PATH);
            curl_setopt($ch, CURLOPT_SSLKEY, CODI_KEY_PATH);
            if (!empty(CODI_KEY_PASSPHRASE)) {
                curl_setopt($ch, CURLOPT_KEYPASSWD, CODI_KEY_PASSPHRASE);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['CRY']) || isset($data['payload'])) {
                $signedPayload = isset($data['CRY']) ? json_encode($data) : $data['payload'];
                return [
                    'success' => true,
                    'payload' => $signedPayload,
                    'message' => 'Cobro dinámico CoDi firmado exitosamente por Banxico.'
                ];
            }
        }
        
        return [
            'success' => false,
            'payload' => null,
            'message' => 'Error de respuesta API CoDi (' . $httpCode . '): ' . ($curlError ?: $response)
        ];
    }

    // Si aún no se configuran certificados API de Banxico
    return [
        'success' => false,
        'payload' => null,
        'message' => 'Credenciales de la API CoDi de Banxico no configuradas. Se requiere certificado mTLS o API Key en codi_api.php.'
    ];
}
