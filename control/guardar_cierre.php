<?php
// Initialize the session
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "No autorizado"]);
    exit;
}

header('Content-Type: application/json');

$json = file_get_contents('php://input');
$datos = json_decode($json, true);

// Devuelve confirmación de guardado
echo json_encode([
    "status" => "success",
    "message" => "Cierre registrado correctamente"
]);
