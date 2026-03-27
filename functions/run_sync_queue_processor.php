<?php

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (PHP_SAPI !== 'cli' && (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true)) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'No autorizado',
    ]);
    exit;
}

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/config_2.php";
require_once __DIR__ . "/sync_queue.php";

if (!isset($cc_sync_emit_json_header)) {
    $cc_sync_emit_json_header = true;
}

if (PHP_SAPI !== 'cli' && $cc_sync_emit_json_header) {
    header('Content-Type: application/json; charset=utf-8');
}

$limit = 50;
if (PHP_SAPI === 'cli') {
    global $argv;
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $limit = (int) $argv[1];
    }
} elseif (isset($_REQUEST['limit']) && is_numeric($_REQUEST['limit'])) {
    $limit = (int) $_REQUEST['limit'];
}

$items = cc_sync_fetch_pending($link, $limit);
$summary = [
    'ok' => true,
    'processed' => 0,
    'done' => 0,
    'failed' => 0,
    'items' => [],
];

foreach ($items as $item) {
    $summary['processed']++;

    try {
        cc_sync_process_item($link, $link2, $item);
        cc_sync_mark_done($link, (int) $item['id_sync']);
        $summary['done']++;
        $summary['items'][] = [
            'id_sync' => (int) $item['id_sync'],
            'entity_type' => $item['entity_type'],
            'action_type' => $item['action_type'],
            'status' => 'done',
        ];
    } catch (Throwable $e) {
        cc_sync_mark_error($link, (int) $item['id_sync'], $e->getMessage());
        $summary['failed']++;
        $summary['items'][] = [
            'id_sync' => (int) $item['id_sync'],
            'entity_type' => $item['entity_type'],
            'action_type' => $item['action_type'],
            'status' => 'error',
            'error' => $e->getMessage(),
        ];
    }
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
