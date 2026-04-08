<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo disponible desde CLI.\n");
}

require_once __DIR__ . "/config.php";

$migrationsDir = realpath(__DIR__ . "/../db/migrations");
if ($migrationsDir === false || !is_dir($migrationsDir)) {
    fwrite(STDERR, "No existe la carpeta de migraciones.\n");
    exit(1);
}

$files = glob($migrationsDir . DIRECTORY_SEPARATOR . "*.sql");
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

if (empty($files)) {
    fwrite(STDOUT, "No hay archivos SQL para aplicar.\n");
    exit(0);
}

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "No se pudo leer: " . basename($file) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "Aplicando: " . basename($file) . "\n");

    if (!mysqli_multi_query($link, $sql)) {
        fwrite(STDERR, "Error en " . basename($file) . ": " . mysqli_error($link) . "\n");
        exit(1);
    }

    do {
        if ($result = mysqli_store_result($link)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($link) && mysqli_next_result($link));

    if (mysqli_errno($link)) {
        fwrite(STDERR, "Error en " . basename($file) . ": " . mysqli_error($link) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: " . basename($file) . "\n");
}

fwrite(STDOUT, "Migraciones aplicadas correctamente.\n");
exit(0);
