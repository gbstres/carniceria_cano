<?php

session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

$_REQUEST['limit'] = 5000;

require_once __DIR__ . "/run_sync_queue_processor.php";
