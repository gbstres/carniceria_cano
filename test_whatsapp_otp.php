<?php
require_once __DIR__ . '/functions/whatsapp_helper.php';

// Si se recibe por GET o POST el parámetro numero
$numeroPrueba = $_GET['numero'] ?? $_POST['numero'] ?? '';

if (!empty($numeroPrueba)) {
    $otpPrueba = rand(100000, 999999);
    $resultado = enviarCodigoOTP($numeroPrueba, $otpPrueba);
    
    header('Content-Type: application/json');
    echo json_encode([
        'estado' => 'Procesado',
        'numero_destino' => $numeroPrueba,
        'otp_generado' => $otpPrueba,
        'resultado_api' => $resultado
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba de Envío WhatsApp OTP - Carnicería Cano</title>
    <style>
        body { font-family: sans-serif; padding: 30px; background: #f4f6f9; }
        .card { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #333; }
        input[type=text] { width: 100%; padding: 10px; margin: 10px 0 20px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; background: #25D366; color: white; border: none; padding: 12px; font-size: 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #1eb857; }
    </style>
</head>
<body>
    <div class="card">
        <h2>📲 Prueba OTP WhatsApp</h2>
        <p>Ingresa tu número de celular a 10 dígitos para probar el envío:</p>
        <form method="GET">
            <label>Número de WhatsApp:</label>
            <input type="text" name="numero" placeholder="Ej: 5512345678" required>
            <button type="submit">Enviar OTP de Prueba</button>
        </form>
    </div>
</body>
</html>
