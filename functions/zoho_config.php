<?php
/**
 * Configuración y helper para Zoho Mail API v2 mediante OAuth 2.0
 * Proyecto: Carnicería Cano
 */

define('ZOHO_CLIENT_ID', '1000.DT5KXEWX97TJKNHE62644ODDFHQNPV');
define('ZOHO_CLIENT_SECRET', '1b7785519cdf64e8678145e74b6e69f176bbd495b5');
define('ZOHO_REFRESH_TOKEN', '1000.fc99fdb547ea2bd146eb95776491b506.25b075ad1bf7a6b5f928bec79c548d7d');
define('ZOHO_ACCOUNT_EMAIL', 'info@abstrings.com');

/**
 * Genera un Access Token fresco utilizando el Refresh Token
 *
 * @return string|null Access Token de Zoho o null si ocurre un error
 */
function get_zoho_access_token() {
    $url = "https://accounts.zoho.com/oauth/v2/token";
    $params = [
        'refresh_token' => ZOHO_REFRESH_TOKEN,
        'client_id'     => ZOHO_CLIENT_ID,
        'client_secret' => ZOHO_CLIENT_SECRET,
        'grant_type'    => 'refresh_token'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    return null;
}

/**
 * Realiza una prueba de conexión obteniendo el Access Token
 *
 * @return array Estado de la prueba y token generado
 */
function test_zoho_connection() {
    $token = get_zoho_access_token();
    if ($token) {
        return [
            'success' => true,
            'message' => 'Conexión con Zoho OAuth 2.0 exitosa. Access Token obtenido correctamente.',
            'access_token' => $token
        ];
    }
    return [
        'success' => false,
        'message' => 'Error al renovar el Access Token con Zoho'
    ];
}
