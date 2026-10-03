<?php
/**
 * Helper para generación de Códigos QR bancarios estándar EMVCo / SPEI (México)
 * Proyecto: Carnicería Cano
 */

define('SPEI_CLABE', '012180015930849331');
define('SPEI_BANCO', 'BBVA');
define('SPEI_BENEFICIARIO', 'GERARDO BAUTISTA SERNA');

function emv_tlv_format($tag, $value) {
    $length = sprintf("%02d", strlen($value));
    return sprintf("%02s%s%s", $tag, $length, $value);
}

function emv_crc16_ccitt($str) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($str); $i++) {
        $crc ^= (ord($str[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(sprintf("%04X", $crc));
}

/**
 * Genera la cadena de datos estándar EMVCo para transferencia SPEI
 */
function generar_payload_emvco_spei($clabe, $beneficiario, $monto, $concepto, $referencia) {
    $payload = "";
    $payload .= emv_tlv_format("00", "01"); // Payload Format Indicator
    $payload .= emv_tlv_format("01", "12"); // Dynamic QR

    // Tag 28: Información del comerciante / SPEI México
    $speiInfo = "";
    $speiInfo .= emv_tlv_format("00", "mx.com.spei");
    $speiInfo .= emv_tlv_format("01", $clabe);
    $speiInfo .= emv_tlv_format("02", substr($beneficiario, 0, 25));
    $payload .= emv_tlv_format("28", $speiInfo);

    $payload .= emv_tlv_format("52", "0000"); // Category code
    $payload .= emv_tlv_format("53", "484");  // MXN Currency
    if ($monto > 0) {
        $payload .= emv_tlv_format("54", sprintf("%.2f", $monto));
    }
    $payload .= emv_tlv_format("58", "MX");
    $payload .= emv_tlv_format("59", substr($beneficiario, 0, 25));
    $payload .= emv_tlv_format("60", "MEXICO");

    // Tag 62: Datos adicionales (Referencia y Concepto)
    $addData = "";
    if (!empty($referencia)) {
        $addData .= emv_tlv_format("05", substr((string)$referencia, 0, 7));
    }
    if (!empty($concepto)) {
        $addData .= emv_tlv_format("08", substr((string)$concepto, 0, 40));
    }
    if (!empty($addData)) {
        $payload .= emv_tlv_format("62", $addData);
    }

    // Tag 63: Suma de comprobación CRC16
    $payload .= "6304";
    $crc = emv_crc16_ccitt($payload);
    return $payload . $crc;
}
