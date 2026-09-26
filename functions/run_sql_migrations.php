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

// Crear tabla de bitácora de migraciones si no existe
$createMigrationsTableSql = "CREATE TABLE IF NOT EXISTS cc_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!mysqli_query($link, $createMigrationsTableSql)) {
    fwrite(STDERR, "Error al crear la tabla cc_migrations: " . mysqli_error($link) . "\n");
    exit(1);
}

// Obtener migraciones ya aplicadas
$executedMigrations = [];
$res = mysqli_query($link, "SELECT migration FROM cc_migrations");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $executedMigrations[$row['migration']] = true;
    }
    mysqli_free_result($res);
}

$files = glob($migrationsDir . DIRECTORY_SEPARATOR . "*.sql");
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

if (empty($files)) {
    fwrite(STDOUT, "No hay archivos SQL para aplicar.\n");
    exit(0);
}

$appliedCount = 0;
$skippedCount = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (isset($executedMigrations[$filename])) {
        fwrite(STDOUT, "Omitido (ya aplicado): " . $filename . "\n");
        $skippedCount++;
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "No se pudo leer: " . $filename . "\n");
        exit(1);
    }

    fwrite(STDOUT, "Aplicando: " . $filename . "...\n");

    if (!mysqli_multi_query($link, $sql)) {
        fwrite(STDERR, "Error en " . $filename . ": " . mysqli_error($link) . "\n");
        exit(1);
    }

    do {
        if ($result = mysqli_store_result($link)) {
            mysqli_free_result($result);
        }
    } while (mysqli_more_results($link) && mysqli_next_result($link));

    if (mysqli_errno($link)) {
        fwrite(STDERR, "Error en " . $filename . ": " . mysqli_error($link) . "\n");
        exit(1);
    }

    $escapedFilename = mysqli_real_escape_string($link, $filename);
    $insertSql = "INSERT INTO cc_migrations (migration, executed_at) VALUES ('$escapedFilename', NOW())";
    if (!mysqli_query($link, $insertSql)) {
        fwrite(STDERR, "Error registrando migración " . $filename . ": " . mysqli_error($link) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: " . $filename . "\n");
    $appliedCount++;
}

fwrite(STDOUT, "\nProceso finalizado. Migraciones aplicadas: $appliedCount, omitidas: $skippedCount.\n");
exit(0);
