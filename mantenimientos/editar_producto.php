<?php
// Initialize session and verify login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once __DIR__ . "/../functions/config.php";
require_once __DIR__ . "/../functions/sync_queue.php";
date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];
$id_usuario = (int) $_SESSION["id"];
$codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : (isset($_POST['codigo']) ? trim((string) $_POST['codigo']) : '');

if ($codigo === '') {
    header("location: productos.php");
    exit;
}

$codigoEscaped = mysqli_real_escape_string($link, $codigo);
$feedbackType = '';
$feedbackMessage = '';

// Check if cc_productos_fotos table exists
function productoFotosTableExists(mysqli $link): bool
{
    $result = mysqli_query($link, "SHOW TABLES LIKE 'cc_productos_fotos'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);
    }
    return $exists;
}

$tableFotosExists = productoFotosTableExists($link);

// Upload directory helpers
function getProductoUploadDir(int $idSucursal, string $codigo): string
{
    $cleanCodigo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $codigo);
    return __DIR__ . '/../uploads/productos/' . $idSucursal . '/' . $cleanCodigo;
}

function getProductoRelativePath(int $idSucursal, string $codigo, string $filename): string
{
    $cleanCodigo = preg_replace('/[^a-zA-Z0-9_-]/', '_', $codigo);
    return 'uploads/productos/' . $idSucursal . '/' . $cleanCodigo . '/' . $filename;
}

function ensureDirExists(string $path): void
{
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

// Image processing helper: load image from file path or data
function loadGdImage(string $filePath, string $mimeType)
{
    $image = false;
    if ($mimeType === 'image/jpeg') {
        $image = @imagecreatefromjpeg($filePath);
    } elseif ($mimeType === 'image/png') {
        $image = @imagecreatefrompng($filePath);
    } elseif ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($filePath);
    }

    if (!$image) {
        return false;
    }

    // Auto-rotate if EXIF orientation is present (e.g. mobile photo)
    if (function_exists('exif_read_data') && $mimeType === 'image/jpeg') {
        $exif = @exif_read_data($filePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $rotated = imagerotate($image, 180, 0);
                    if ($rotated) { imagedestroy($image); $image = $rotated; }
                    break;
                case 6:
                    $rotated = imagerotate($image, -90, 0);
                    if ($rotated) { imagedestroy($image); $image = $rotated; }
                    break;
                case 8:
                    $rotated = imagerotate($image, 90, 0);
                    if ($rotated) { imagedestroy($image); $image = $rotated; }
                    break;
            }
        }
    }

    return $image;
}

// Center-crop and resample to exact target aspect ratio and dimensions
function createResampledCrop($sourceImage, int $targetWidth, int $targetHeight)
{
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    $targetRatio = $targetWidth / $targetHeight;
    $sourceRatio = $sourceWidth / $sourceHeight;

    if ($sourceRatio > $targetRatio) {
        $cropHeight = $sourceHeight;
        $cropWidth = (int) round($sourceHeight * $targetRatio);
        $srcX = (int) floor(($sourceWidth - $cropWidth) / 2);
        $srcY = 0;
    } else {
        $cropWidth = $sourceWidth;
        $cropHeight = (int) round($sourceWidth / $targetRatio);
        $srcX = 0;
        $srcY = (int) floor(($sourceHeight - $cropHeight) / 2);
    }

    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

    // Fill background with white in case of transparent PNG/WebP conversion
    $white = imagecolorallocate($targetImage, 255, 255, 255);
    imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $white);

    imagecopyresampled(
        $targetImage,
        $sourceImage,
        0, 0,
        $srcX, $srcY,
        $targetWidth, $targetHeight,
        $cropWidth, $cropHeight
    );

    return $targetImage;
}

// Save image in both high quality JPEG and modern WebP
function saveDualFormatImage($gdImage, string $destPathJpg, int $jpgQuality = 85): bool
{
    $savedJpg = imagejpeg($gdImage, $destPathJpg, $jpgQuality);
    
    // Also save alongside as WebP if supported
    if (function_exists('imagewebp')) {
        $destPathWebp = preg_replace('/\.jpe?g$/i', '.webp', $destPathJpg);
        if ($destPathWebp && $destPathWebp !== $destPathJpg) {
            @imagewebp($gdImage, $destPathWebp, $jpgQuality);
        }
    }

    return $savedJpg;
}

// -------------------------------------------------------------
// ACTION: Actualizar datos generales del producto
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_producto'])) {
    $descripcion = mb_strtoupper(trim($_POST["descripcion"] ?? ''));
    $precio_compra = (float) ($_POST["precio_compra"] ?? 0);
    $precio_venta = (float) ($_POST["precio_venta"] ?? 0);
    $almacen = (float) ($_POST["almacen"] ?? 0);
    $limite_stock = (float) ($_POST["limite_stock"] ?? 5);
    $id_categoria = (int) ($_POST["id_categoria"] ?? 0);
    $mayoreo = isset($_POST['mayoreo']) ? 1 : 0;
    $centralizar_almacen = trim($_POST["centralizar_almacen"] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    $id_usuario_act = $id_usuario;
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');

    $descSql = mysqli_real_escape_string($link, $descripcion);
    $caSql = mysqli_real_escape_string($link, $centralizar_almacen);

    $sqlActualizar = "
        UPDATE cc_productos
        SET
            descripcion = '$descSql',
            precio_compra = '$precio_compra',
            precio_venta = '$precio_venta',
            almacen = '$almacen',
            limite_stock = '$limite_stock',
            id_categoria = '$id_categoria',
            mayoreo = '$mayoreo',
            centralizar_almacen = '$caSql',
            activo = '$activo',
            fecha_act = '$fecha_act',
            hora_act = '$hora_act',
            id_usuario_act = '$id_usuario_act'
        WHERE codigo = '$codigoEscaped'
            AND id_sucursal = '$id_sucursal'
    ";

    if (mysqli_query($link, $sqlActualizar)) {
        cc_sync_enqueue($link, $id_sucursal, 'producto', 'upsert', [
            'codigo' => (string) $codigo,
        ], [
            'tabla' => 'cc_productos',
            'id_categoria' => (int) $id_categoria,
        ]);
        $feedbackType = 'success';
        $feedbackMessage = 'Los datos del producto se actualizaron correctamente.';
    } else {
        $feedbackType = 'danger';
        $feedbackMessage = 'Error al actualizar el producto: ' . mysqli_error($link);
    }
}

// -------------------------------------------------------------
// ACTION: Establecer foto principal
// -------------------------------------------------------------
if (isset($_GET['accion']) && $_GET['accion'] === 'marcar_principal' && $tableFotosExists) {
    $idFoto = isset($_GET['id_foto']) ? (int) $_GET['id_foto'] : 0;
    if ($idFoto > 0) {
        mysqli_begin_transaction($link);
        mysqli_query($link, "
            UPDATE cc_productos_fotos
            SET es_principal = 0
            WHERE id_sucursal = '$id_sucursal'
                AND codigo = '$codigoEscaped'
        ");
        mysqli_query($link, "
            UPDATE cc_productos_fotos
            SET es_principal = 1
            WHERE id_foto = '$idFoto'
                AND id_sucursal = '$id_sucursal'
                AND codigo = '$codigoEscaped'
        ");
        mysqli_commit($link);

        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header("Location: editar_producto.php?codigo=" . urlencode($codigo) . "&msg=principal_ok");
        exit;
    }
}

// -------------------------------------------------------------
// ACTION: Eliminar fotografía
// -------------------------------------------------------------
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar_foto' && $tableFotosExists) {
    $idFoto = isset($_GET['id_foto']) ? (int) $_GET['id_foto'] : 0;
    if ($idFoto > 0) {
        $fotoResult = mysqli_query($link, "
            SELECT ruta_small, ruta_large, es_principal
            FROM cc_productos_fotos
            WHERE id_foto = '$idFoto'
                AND id_sucursal = '$id_sucursal'
                AND codigo = '$codigoEscaped'
            LIMIT 1
        ");
        $foto = $fotoResult ? mysqli_fetch_assoc($fotoResult) : null;

        if ($foto !== null) {
            $eraPrincipal = (int) $foto['es_principal'] === 1;

            mysqli_query($link, "
                DELETE FROM cc_productos_fotos
                WHERE id_foto = '$idFoto'
                    AND id_sucursal = '$id_sucursal'
                    AND codigo = '$codigoEscaped'
            ");

            $smallPath = __DIR__ . '/../' . $foto['ruta_small'];
            $largePath = __DIR__ . '/../' . $foto['ruta_large'];
            if (is_file($smallPath)) { @unlink($smallPath); }
            if (is_file($largePath)) { @unlink($largePath); }
            
            // Also clean corresponding WebP files if they exist
            $smallWebp = preg_replace('/\.jpe?g$/i', '.webp', $smallPath);
            $largeWebp = preg_replace('/\.jpe?g$/i', '.webp', $largePath);
            if (is_file($smallWebp)) { @unlink($smallWebp); }
            if (is_file($largeWebp)) { @unlink($largeWebp); }

            // If the deleted photo was principal, promote the next available photo
            if ($eraPrincipal) {
                $sigResult = mysqli_query($link, "
                    SELECT id_foto
                    FROM cc_productos_fotos
                    WHERE id_sucursal = '$id_sucursal'
                        AND codigo = '$codigoEscaped'
                    ORDER BY orden ASC, id_foto ASC
                    LIMIT 1
                ");
                if ($sigResult && $sigRow = mysqli_fetch_assoc($sigResult)) {
                    $sigId = (int) $sigRow['id_foto'];
                    mysqli_query($link, "
                        UPDATE cc_productos_fotos
                        SET es_principal = 1
                        WHERE id_foto = '$sigId'
                    ");
                }
            }
        }

        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header("Location: editar_producto.php?codigo=" . urlencode($codigo) . "&msg=eliminada_ok");
        exit;
    }
}

// -------------------------------------------------------------
// ACTION: Subir fotografías (Múltiples fotos, Drag & Drop o Form)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_fotos'])) {
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
              || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

    $uploadedCount = 0;
    $errors = [];

    if (!$tableFotosExists) {
        $errors[] = 'La tabla cc_productos_fotos no existe en la base de datos.';
    } elseif (!isset($_FILES['fotos']) || empty($_FILES['fotos']['name']) || !is_array($_FILES['fotos']['name'])) {
        $errors[] = 'No se recibieron archivos para subir.';
    } else {
        $uploadDir = getProductoUploadDir($id_sucursal, $codigo);
        ensureDirExists($uploadDir);

        $principalIndex = isset($_POST['principal_index']) ? (int) $_POST['principal_index'] : -1;
        $altTextGeneral = trim((string) ($_POST['alt_text'] ?? ''));

        // Check how many existing photos this product already has
        $countResult = mysqli_query($link, "
            SELECT COUNT(*) AS total
            FROM cc_productos_fotos
            WHERE id_sucursal = '$id_sucursal'
                AND codigo = '$codigoEscaped'
        ");
        $countRow = $countResult ? mysqli_fetch_assoc($countResult) : ['total' => 0];
        $tieneFotosPrevias = ((int) $countRow['total']) > 0;

        $fileCount = count($_FILES['fotos']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpFile = $_FILES['fotos']['tmp_name'][$i];
            $origName = $_FILES['fotos']['name'][$i];

            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $tmpFile);
            finfo_close($fileInfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes, true)) {
                $errors[] = "El archivo '$origName' no es un formato válido (solo JPG, PNG o WEBP).";
                continue;
            }

            $sourceImage = loadGdImage($tmpFile, $mimeType);
            if (!$sourceImage) {
                $errors[] = "No se pudo leer la imagen '$origName'.";
                continue;
            }

            // Create base filename
            $uniqueId = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
            $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $codigo) . '_' . date('Ymd_His') . '_' . $uniqueId;
            $smallFileName = $baseName . '_sm.jpg';
            $largeFileName = $baseName . '_lg.jpg';

            $smallFullPath = $uploadDir . '/' . $smallFileName;
            $largeFullPath = $uploadDir . '/' . $largeFileName;

            // Formato 1: Versión ligera para catálogo y carga rápida (800x600 px en 4:3)
            $smallImage = createResampledCrop($sourceImage, 800, 600);
            $savedSmall = saveDualFormatImage($smallImage, $smallFullPath, 84);
            imagedestroy($smallImage);

            // Formato 2: Versión de alta resolución para detalle y zoom (1600x1200 px en 4:3)
            $largeImage = createResampledCrop($sourceImage, 1600, 1200);
            $savedLarge = saveDualFormatImage($largeImage, $largeFullPath, 92);
            imagedestroy($largeImage);

            imagedestroy($sourceImage);

            if (!$savedSmall || !$savedLarge) {
                $errors[] = "No se pudieron guardar las versiones redimensionadas de '$origName'.";
                if (is_file($smallFullPath)) { @unlink($smallFullPath); }
                if (is_file($largeFullPath)) { @unlink($largeFullPath); }
                continue;
            }

            // Determine if this photo should be principal
            $esPrincipal = 0;
            if ($principalIndex === $i) {
                $esPrincipal = 1;
            } elseif (!$tieneFotosPrevias && $uploadedCount === 0) {
                // If the product had 0 photos and none was explicitly chosen, first uploaded becomes principal
                $esPrincipal = 1;
            }

            $rutaSmall = mysqli_real_escape_string($link, getProductoRelativePath($id_sucursal, $codigo, $smallFileName));
            $rutaLarge = mysqli_real_escape_string($link, getProductoRelativePath($id_sucursal, $codigo, $largeFileName));
            $altTextFinal = mysqli_real_escape_string($link, $altTextGeneral !== '' ? $altTextGeneral : pathinfo($origName, PATHINFO_FILENAME));

            mysqli_begin_transaction($link);
            try {
                if ($esPrincipal === 1) {
                    mysqli_query($link, "
                        UPDATE cc_productos_fotos
                        SET es_principal = 0
                        WHERE id_sucursal = '$id_sucursal'
                            AND codigo = '$codigoEscaped'
                    ");
                }

                $ordenResult = mysqli_query($link, "
                    SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
                    FROM cc_productos_fotos
                    WHERE id_sucursal = '$id_sucursal'
                        AND codigo = '$codigoEscaped'
                ");
                $ordenRow = $ordenResult ? mysqli_fetch_assoc($ordenResult) : ['siguiente' => 1];
                $orden = (int) $ordenRow['siguiente'];

                $insertSql = "
                    INSERT INTO cc_productos_fotos (
                        id_sucursal, codigo, ruta_small, ruta_large, alt_text, orden, es_principal, activo, fecha_ingreso, id_usuario
                    ) VALUES (
                        '$id_sucursal', '$codigoEscaped', '$rutaSmall', '$rutaLarge', '$altTextFinal', '$orden', '$esPrincipal', 1, NOW(), '$id_usuario'
                    )
                ";

                if (!mysqli_query($link, $insertSql)) {
                    throw new Exception(mysqli_error($link));
                }

                mysqli_commit($link);
                $uploadedCount++;
                $tieneFotosPrevias = true;
            } catch (Exception $e) {
                mysqli_rollback($link);
                if (is_file($smallFullPath)) { @unlink($smallFullPath); }
                if (is_file($largeFullPath)) { @unlink($largeFullPath); }
                $errors[] = "Error al registrar '$origName': " . $e->getMessage();
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $uploadedCount > 0,
            'count' => $uploadedCount,
            'errors' => $errors,
            'message' => $uploadedCount > 0
                ? "Se subieron exitosamente $uploadedCount fotografía(s) en 2 formatos (800x600 y 1600x1200)."
                : (empty($errors) ? "No se procesaron fotografías." : implode(" ", $errors))
        ]);
        exit;
    }

    if ($uploadedCount > 0) {
        $feedbackType = 'success';
        $feedbackMessage = "Se subieron exitosamente $uploadedCount fotografía(s) guardadas en 2 formatos optimizados.";
    } elseif (!empty($errors)) {
        $feedbackType = 'danger';
        $feedbackMessage = implode('<br>', $errors);
    }
}

// -------------------------------------------------------------
// Cargar datos actuales del producto
// -------------------------------------------------------------
$productoResult = mysqli_query($link, "
    SELECT p.*, c.desc_categoria, ca.descripcion_corta AS desc_centraliza
    FROM cc_productos p
    LEFT JOIN cc_categorias c
        ON c.id_sucursal = p.id_sucursal
        AND c.id_categoria = p.id_categoria
    LEFT JOIN cc_claves ca
        ON ca.nombre_clave = 'CENTRALIZAR_ALMACEN'
        AND ca.clave = p.centralizar_almacen
    WHERE p.id_sucursal = '$id_sucursal'
        AND p.codigo = '$codigoEscaped'
    LIMIT 1
");

$producto = $productoResult ? mysqli_fetch_assoc($productoResult) : null;

if ($producto === null) {
    header("location: productos.php");
    exit;
}

// Check GET status messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'principal_ok') {
        $feedbackType = 'success';
        $feedbackMessage = 'La fotografía seleccionada ahora es la PRINCIPAL para carniceriacano.com.';
    } elseif ($_GET['msg'] === 'eliminada_ok') {
        $feedbackType = 'success';
        $feedbackMessage = 'Fotografía eliminada correctamente.';
    }
}

// Cargar categorías disponibles
$categoriasQuery = mysqli_query($link, "
    SELECT id_categoria, desc_categoria
    FROM cc_categorias
    WHERE id_sucursal = '$id_sucursal'
    ORDER BY desc_categoria ASC
");

// Cargar claves de centralización de almacén
$clavesQuery = mysqli_query($link, "
    SELECT clave, descripcion
    FROM cc_claves
    WHERE nombre_clave = 'CENTRALIZAR_ALMACEN'
    ORDER BY clave ASC
");

// Cargar fotografías actuales del producto
$fotos = [];
if ($tableFotosExists) {
    $fotosResult = mysqli_query($link, "
        SELECT *
        FROM cc_productos_fotos
        WHERE id_sucursal = '$id_sucursal'
            AND codigo = '$codigoEscaped'
        ORDER BY es_principal DESC, orden ASC, id_foto ASC
    ");
    while ($row = mysqli_fetch_assoc($fotosResult)) {
        $fotos[] = $row;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Edición de Producto y Gestión de Fotografías">
    <link rel="shortcut icon" href="../img/logo_1.png">
    <title>Editar Producto: <?php echo htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?> - Carnicería Cano</title>

    <!-- Estilos base -->
    <link href="../css/bootstrap.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/navbar.css" rel="stylesheet">
    <link href="../css/cropper.min.css" rel="stylesheet">

    <style>
        .photo-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            position: relative;
        }
        .photo-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.12) !important;
        }
        .photo-thumb-container {
            position: relative;
            width: 100%;
            height: 190px;
            background-color: #f1f3f5;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-thumb-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .badge-principal-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
        }
        .dropzone-container {
            border: 2px dashed #0d6efd;
            border-radius: 12px;
            background-color: #f8fbff;
            cursor: pointer;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .dropzone-container:hover,
        .dropzone-container.dragover {
            background-color: #e7f1ff;
            border-color: #0b5ed7;
        }
        .staging-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            padding: 12px;
        }
        .cropper-view-box,
        .cropper-face {
            border-radius: 0;
        }
        .img-crop-container {
            max-height: 480px;
            min-height: 300px;
            background-color: #212529;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .img-crop-container img {
            max-width: 100%;
            max-height: 480px;
            display: block;
        }
    </style>
</head>
<body class="bg-light">
    <main>
        <div class="container py-3">
            <?php require_once __DIR__ . "/../components/nav.php"; ?>

            <!-- Encabezado de la página -->
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="../main/inicio.php" class="text-decoration-none">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="productos.php" class="text-decoration-none">Productos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Editar Producto</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0 fw-bold">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Producto
                    </h2>
                </div>
                <div class="d-flex gap-2">
                    <a href="productos.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver al listado
                    </a>
                    <a href="#seccion_fotos" class="btn btn-outline-primary">
                        <i class="bi bi-images me-1"></i> Ir a fotografías (<?php echo count($fotos); ?>)
                    </a>
                </div>
            </div>

            <!-- Alertas de estado -->
            <?php if ($feedbackMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?php if ($feedbackType === 'success'): ?>
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <?php else: ?>
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <?php endif; ?>
                    <?php echo $feedbackMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- ======================================================== -->
            <!-- SECCIÓN 1: FORMULARIO PRINCIPAL DE EDICIÓN DEL PRODUCTO -->
            <!-- ======================================================== -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-card-text text-primary me-2"></i>Datos Generales del Producto
                    </h5>
                    <span class="badge bg-secondary">Código: <?php echo htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="card-body p-4">
                    <form method="post" action="editar_producto.php?codigo=<?php echo urlencode($codigo); ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="codigo" value="<?php echo htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="codigo_display" class="form-label fw-bold small text-muted">CÓDIGO (CLAVE)</label>
                                <input type="text" class="form-control bg-light" id="codigo_display" value="<?php echo htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                            </div>
                            <div class="col-md-9">
                                <label for="descripcion" class="form-label fw-bold small">DESCRIPCIÓN DEL PRODUCTO <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase" id="descripcion" name="descripcion" value="<?php echo htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="off">
                                <div class="invalid-feedback">Favor de ingresar la descripción del producto.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3 col-sm-6">
                                <label for="precio_compra" class="form-label fw-bold small">PRECIO COMPRA ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="precio_compra" name="precio_compra" value="<?php echo htmlspecialchars($producto['precio_compra'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="precio_venta" class="form-label fw-bold small">PRECIO VENTA ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" id="precio_venta" name="precio_venta" value="<?php echo htmlspecialchars($producto['precio_venta'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="almacen" class="form-label fw-bold small">STOCK EN ALMACÉN (KG/PZA) <span class="text-danger">*</span></label>
                                <input type="number" step="0.001" class="form-control" id="almacen" name="almacen" value="<?php echo htmlspecialchars($producto['almacen'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="limite_stock" class="form-label fw-bold small">LÍMITE MÍNIMO DE STOCK <span class="text-danger">*</span></label>
                                <input type="number" step="0.001" class="form-control" id="limite_stock" name="limite_stock" value="<?php echo htmlspecialchars($producto['limite_stock'] ?? 5, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="id_categoria" class="form-label fw-bold small">CATEGORÍA <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_categoria" name="id_categoria" required>
                                    <option value="" disabled>Seleccione una categoría...</option>
                                    <?php while ($cat = mysqli_fetch_assoc($categoriasQuery)): ?>
                                        <option value="<?php echo (int) $cat['id_categoria']; ?>" <?php echo ((int) $cat['id_categoria'] === (int) $producto['id_categoria']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="centralizar_almacen" class="form-label fw-bold small">¿DÓNDE CENTRALIZA ALMACÉN? <span class="text-danger">*</span></label>
                                <select class="form-select" id="centralizar_almacen" name="centralizar_almacen" required>
                                    <option value="" disabled>Seleccione...</option>
                                    <?php while ($ca = mysqli_fetch_assoc($clavesQuery)): ?>
                                        <option value="<?php echo htmlspecialchars($ca['clave'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($ca['clave'] == $producto['centralizar_almacen']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ca['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small d-block">OPCIONES ADICIONALES</label>
                                <div class="form-check form-check-inline pt-1">
                                    <input class="form-check-input" type="checkbox" id="mayoreo" name="mayoreo" value="1" <?php echo ((int) $producto['mayoreo'] === 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mayoreo">Venta Mayoreo</label>
                                </div>
                                <div class="form-check form-check-inline pt-1">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?php echo ((int) $producto['activo'] === 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activo">Producto Activo</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="text-muted small">
                                Última actualización: <?php echo htmlspecialchars($producto['fecha_act'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($producto['hora_act'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <button type="submit" name="actualizar_producto" class="btn btn-primary px-4 py-2 fw-semibold">
                                <i class="bi bi-save me-1"></i> Guardar Cambios del Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- SECCIÓN 2: GESTIÓN DE FOTOGRAFÍAS (AL PIE DE PÁGINA)     -->
            <!-- ======================================================== -->
            <div class="card shadow-sm border-0 rounded-3" id="seccion_fotos">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-camera-fill text-primary me-2"></i>Fotografías del Producto
                        </h5>
                        <small class="text-muted">Gestión de imágenes para catálogo y visualización en carniceriacano.com</small>
                    </div>
                    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                        <i class="bi bi-images me-1"></i> <?php echo count($fotos); ?> foto(s) registrada(s)
                    </span>
                </div>
                <div class="card-body p-4">



                    <!-- Galería de fotos existentes -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <span>Galería Actual de Fotos</span>
                            <span class="badge bg-secondary"><?php echo count($fotos); ?></span>
                        </h6>

                        <?php if (empty($fotos)): ?>
                            <div class="text-center py-5 bg-white border rounded-3 p-4">
                                <i class="bi bi-image text-muted display-4 d-block mb-2"></i>
                                <h6 class="fw-bold text-muted">Este producto aún no cuenta con fotografías</h6>
                                <p class="text-muted small mb-0">Utiliza la zona de abajo para arrastrar o seleccionar fotos para este producto.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($fotos as $f): ?>
                                    <div class="col-xl-3 col-lg-4 col-md-6">
                                        <div class="card h-100 photo-card border shadow-sm rounded-3">
                                            <div class="photo-thumb-container rounded-top">
                                                <?php if ((int) $f['es_principal'] === 1): ?>
                                                    <span class="badge bg-success badge-principal-tag shadow-sm">
                                                        <i class="bi bi-star-fill me-1"></i> Principal (Tienda)
                                                    </span>
                                                <?php endif; ?>
                                                <img src="../<?php echo htmlspecialchars($f['ruta_small'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($f['alt_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-bold text-truncate" title="<?php echo htmlspecialchars($f['alt_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($f['alt_text'] ?? $producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark border">#<?php echo (int) $f['orden']; ?></span>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.78rem;">
                                                    <div><i class="bi bi-file-earmark-image me-1"></i> Web: 800×600 | HD: 1600×1200</div>
                                                    <div><i class="bi bi-calendar3 me-1"></i> <?php echo htmlspecialchars($f['fecha_ingreso'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center flex-wrap gap-1">
                                                <?php if ((int) $f['es_principal'] !== 1): ?>
                                                    <a href="editar_producto.php?codigo=<?php echo urlencode($codigo); ?>&accion=marcar_principal&id_foto=<?php echo (int) $f['id_foto']; ?>" class="btn btn-sm btn-outline-success" title="Mostrar como foto principal en la tienda">
                                                        <i class="bi bi-star me-1"></i> Hacer Principal
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-success disabled" disabled>
                                                        <i class="bi bi-check-lg me-1"></i> Es Principal
                                                    </button>
                                                <?php endif; ?>
                                                <div class="d-flex gap-1">
                                                    <a href="../<?php echo htmlspecialchars($f['ruta_large'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Ver en alta resolución">
                                                        <i class="bi bi-arrows-fullscreen"></i>
                                                    </a>
                                                    <a href="editar_producto.php?codigo=<?php echo urlencode($codigo); ?>&accion=eliminar_foto&id_foto=<?php echo (int) $f['id_foto']; ?>" class="btn btn-sm btn-outline-danger" title="Eliminar foto" onclick="return confirm('¿Seguro que deseas eliminar esta fotografía permanentemente?');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr class="my-4">

                    <!-- Zona de Arrastrar y Soltar + Selector de Archivos -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Agregar Nuevas Fotografías
                        </h6>

                        <!-- Contenedor Drag & Drop -->
                        <div id="dropzone" class="dropzone-container p-4 text-center">
                            <i class="bi bi-images display-4 text-primary d-block mb-2"></i>
                            <h5 class="fw-bold text-dark mb-1">Arrastra y suelta tus fotos aquí</h5>
                            <p class="text-muted small mb-3">o haz clic en el botón para seleccionarlas desde tu equipo (puedes elegir varias fotos)</p>
                            <button type="button" class="btn btn-outline-primary px-4" id="btn_examinar">
                                <i class="bi bi-folder2-open me-1"></i> Examinar Archivos
                            </button>
                            <input type="file" id="input_archivos" name="fotos[]" multiple accept="image/jpeg,image/png,image/webp" class="d-none">
                            <div class="text-muted mt-2" style="font-size: 0.8rem;">
                                Formatos admitidos: JPG, PNG, WEBP &nbsp;|&nbsp; Estándar de recorte: 4:3 (800×600 y 1600×1200)
                            </div>
                        </div>

                        <!-- Barra de texto alternativo / descripción común -->
                        <div class="row g-2 align-items-center mt-3">
                            <div class="col-md-8">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-card-heading me-1"></i> Texto descriptivo / Alt:</span>
                                    <input type="text" class="form-control" id="alt_text_input" placeholder="<?php echo htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end text-muted small">
                                Se aplicará como descripción de las fotos subidas.
                            </div>
                        </div>

                        <!-- Cola de Fotos por Subir (Staging Queue) -->
                        <div id="contenedor_cola" class="mt-4" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <h6 class="fw-bold text-dark mb-0">
                                    <i class="bi bi-list-check text-primary me-1"></i>
                                    Fotos listas para subir (<span id="contador_cola">0</span>)
                                </h6>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_limpiar_cola">
                                        <i class="bi bi-x-circle me-1"></i> Limpiar cola
                                    </button>
                                </div>
                            </div>

                            <!-- Listado de fotos en preparación -->
                            <div class="row g-3" id="lista_cola">
                                <!-- Elementos dinámicos generados por JS -->
                            </div>

                            <!-- Barra de progreso durante la subida -->
                            <div id="progreso_subida" class="progress mt-3" style="height: 12px; display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                            </div>

                            <!-- Botón de Confirmación de Subida -->
                            <div class="d-flex justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                                <button type="button" class="btn btn-primary px-4 py-2 fw-semibold" id="btn_confirmar_subida">
                                    <i class="bi bi-cloud-arrow-up-fill me-1"></i>
                                    Guardar y Subir Fotografías (<span id="btn_count">0</span>)
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- ======================================================== -->
    <!-- MODAL DE RECORTE Y REDIMENSIÓN (CROPPER.JS)              -->
    <!-- ======================================================== -->
    <div class="modal fade" id="modalCropper" tabindex="-1" aria-labelledby="modalCropperLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold" id="modalCropperLabel">
                        <i class="bi bi-crop me-2"></i>Ajustar y Redimensionar Fotografía al Estándar
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <!-- Instrucciones breves -->
                    <div class="alert alert-warning py-2 px-3 small mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-aspect-ratio fs-5"></i>
                        <span>El estándar de Carnicería Cano es <strong>4:3</strong> (800×600 y 1600×1200). Encuadra el producto en el recuadro para que se vea óptimo en la tienda.</span>
                    </div>

                    <!-- Contenedor de la imagen a recortar -->
                    <div class="img-crop-container rounded shadow-inner">
                        <img id="imagen_recortar" src="" alt="Recorte">
                    </div>

                    <!-- Barra de herramientas de control -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 p-2 bg-white rounded border">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Aspect Ratio">
                            <button type="button" class="btn btn-outline-primary active" id="btn_ratio_4_3">4:3 (Estándar Tienda)</button>
                            <button type="button" class="btn btn-outline-secondary" id="btn_ratio_1_1">1:1 (Cuadrada)</button>
                            <button type="button" class="btn btn-outline-secondary" id="btn_ratio_free">Libre</button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Crop Controls">
                            <button type="button" class="btn btn-outline-secondary" id="btn_zoom_in" title="Acercar"><i class="bi bi-zoom-in"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="btn_zoom_out" title="Alejar"><i class="bi bi-zoom-out"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="btn_rotate_left" title="Rotar -90°"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="btn_rotate_right" title="Rotar +90°"><i class="bi bi-arrow-clockwise"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="btn_reset_crop" title="Restablecer"><i class="bi bi-arrow-repeat"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary fw-semibold" id="btn_aplicar_recorte">
                        <i class="bi bi-check2-circle me-1"></i> Aplicar Recorte y Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts necesarios -->
    <script src="../js/jquery-3.5.1.js"></script>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/cropper.min.js"></script>

    <script>
    (function() {
        'use strict';

        // Variables de estado
        var stagedPhotos = []; // { file, previewUrl, originalWidth, originalHeight, isCropped, croppedBlob, isPrincipal }
        var currentCropIndex = -1;
        var cropper = null;

        var dropzone = document.getElementById('dropzone');
        var inputArchivos = document.getElementById('input_archivos');
        var btnExaminar = document.getElementById('btn_examinar');
        var contenedorCola = document.getElementById('contenedor_cola');
        var listaCola = document.getElementById('lista_cola');
        var contadorCola = document.getElementById('contador_cola');
        var btnCount = document.getElementById('btn_count');
        var btnLimpiarCola = document.getElementById('btn_limpiar_cola');
        var btnConfirmarSubida = document.getElementById('btn_confirmar_subida');
        var progresoSubida = document.getElementById('progreso_subida');
        var altTextInput = document.getElementById('alt_text_input');

        var modalCropperEl = document.getElementById('modalCropper');
        var bsModalCropper = new bootstrap.Modal(modalCropperEl);
        var imagenRecortar = document.getElementById('imagen_recortar');

        // Drag & Drop event handlers
        btnExaminar.addEventListener('click', function() {
            inputArchivos.click();
        });

        dropzone.addEventListener('click', function(e) {
            if (e.target !== btnExaminar) {
                inputArchivos.click();
            }
        });

        ['dragenter', 'dragover'].forEach(function(eventName) {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(function(eventName) {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', function(e) {
            var dt = e.dataTransfer;
            var files = dt.files;
            handleFilesSelected(files);
        });

        inputArchivos.addEventListener('change', function() {
            handleFilesSelected(this.files);
            this.value = ''; // Reset input
        });

        btnLimpiarCola.addEventListener('click', function() {
            stagedPhotos = [];
            renderCola();
        });

        // Procesa archivos seleccionados
        function handleFilesSelected(files) {
            if (!files || files.length === 0) return;

            var validMimes = ['image/jpeg', 'image/png', 'image/webp'];
            var existingCount = stagedPhotos.length;

            Array.from(files).forEach(function(file) {
                if (!validMimes.includes(file.type)) {
                    alert('El archivo "' + file.name + '" no es un formato de imagen compatible (JPG, PNG o WEBP).');
                    return;
                }

                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = new Image();
                    img.onload = function() {
                        var w = img.width;
                        var h = img.height;
                        var ratio = w / h;
                        var isStandard = Math.abs(ratio - (4 / 3)) < 0.04;

                        // Determina si debería ser la principal inicial
                        var isPrincipal = (stagedPhotos.length === 0 && <?php echo empty($fotos) ? 'true' : 'false'; ?>);

                        stagedPhotos.push({
                            file: file,
                            previewUrl: e.target.result,
                            originalWidth: w,
                            originalHeight: h,
                            isStandard: isStandard,
                            isCropped: false,
                            croppedBlob: null,
                            isPrincipal: isPrincipal
                        });

                        renderCola();
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        // Renderizado visual de la cola de preparación
        function renderCola() {
            if (stagedPhotos.length === 0) {
                contenedorCola.style.display = 'none';
                return;
            }

            contenedorCola.style.display = 'block';
            contadorCola.textContent = stagedPhotos.length;
            btnCount.textContent = stagedPhotos.length;
            listaCola.innerHTML = '';

            stagedPhotos.forEach(function(item, idx) {
                var col = document.createElement('div');
                col.className = 'col-lg-4 col-md-6';

                var ratioText = (item.originalWidth / item.originalHeight).toFixed(2);
                var standardBadge = item.isStandard || item.isCropped
                    ? '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-circle me-1"></i> Estándar 4:3 (' + item.originalWidth + '×' + item.originalHeight + ')</span>'
                    : '<span class="badge bg-warning-subtle text-dark border border-warning"><i class="bi bi-exclamation-triangle me-1"></i> No cumple 4:3 (' + item.originalWidth + '×' + item.originalHeight + ')</span>';

                if (item.isCropped) {
                    standardBadge = '<span class="badge bg-primary-subtle text-primary border border-primary"><i class="bi bi-crop me-1"></i> Recortada a 4:3</span>';
                }

                var cardHtml = '' +
                '<div class="staging-card h-100 shadow-sm">' +
                '  <div class="d-flex gap-3 align-items-center mb-2">' +
                '    <img src="' + item.previewUrl + '" style="width: 80px; height: 60px; object-fit: cover; border-radius: 6px;" class="border flex-shrink-0">' +
                '    <div class="overflow-hidden flex-grow-1">' +
                '      <div class="fw-bold text-truncate small" title="' + item.file.name + '">' + item.file.name + '</div>' +
                '      <div class="text-muted" style="font-size: 0.75rem;">' + (item.file.size / 1024).toFixed(1) + ' KB</div>' +
                '      <div class="mt-1">' + standardBadge + '</div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="d-flex justify-content-between align-items-center pt-2 border-top">' +
                '    <div class="form-check">' +
                '      <input class="form-check-input check-principal" type="radio" name="staging_principal" id="princ_' + idx + '" value="' + idx + '" ' + (item.isPrincipal ? 'checked' : '') + '>' +
                '      <label class="form-check-label small fw-semibold" for="princ_' + idx + '">Principal</label>' +
                '    </div>' +
                '    <div class="btn-group btn-group-sm">' +
                '      <button type="button" class="btn btn-outline-primary btn-crop-action" data-index="' + idx + '" title="Recortar / Redimensionar al estándar 4:3">' +
                '        <i class="bi bi-crop me-1"></i> Redimensionar' +
                '      </button>' +
                '      <button type="button" class="btn btn-outline-danger btn-remove-action" data-index="' + idx + '" title="Quitar de la cola">' +
                '        <i class="bi bi-trash"></i>' +
                '      </button>' +
                '    </div>' +
                '  </div>' +
                '</div>';

                col.innerHTML = cardHtml;
                listaCola.appendChild(col);
            });

            // Bind radio buttons
            document.querySelectorAll('.check-principal').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    var selectedIdx = parseInt(this.value, 10);
                    stagedPhotos.forEach(function(p, i) {
                        p.isPrincipal = (i === selectedIdx);
                    });
                });
            });

            // Bind crop buttons
            document.querySelectorAll('.btn-crop-action').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var idx = parseInt(this.getAttribute('data-index'), 10);
                    abrirCropper(idx);
                });
            });

            // Bind remove buttons
            document.querySelectorAll('.btn-remove-action').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var idx = parseInt(this.getAttribute('data-index'), 10);
                    stagedPhotos.splice(idx, 1);
                    renderCola();
                });
            });
        }

        // Abrir modal de Cropper.js
        function abrirCropper(idx) {
            currentCropIndex = idx;
            var item = stagedPhotos[idx];

            imagenRecortar.src = item.previewUrl;
            bsModalCropper.show();
        }

        modalCropperEl.addEventListener('shown.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
            }
            cropper = new Cropper(imagenRecortar, {
                aspectRatio: 4 / 3, // Estándar oficial 4:3
                viewMode: 1,
                autoCropArea: 0.95,
                responsive: true,
                restore: false
            });
        });

        modalCropperEl.addEventListener('hidden.bs.modal', function() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            currentCropIndex = -1;
        });

        // Controles dentro del modal de recorte
        document.getElementById('btn_ratio_4_3').addEventListener('click', function() {
            if (cropper) cropper.setAspectRatio(4 / 3);
            this.classList.add('active', 'btn-outline-primary');
            document.getElementById('btn_ratio_1_1').classList.remove('active', 'btn-outline-primary');
            document.getElementById('btn_ratio_free').classList.remove('active', 'btn-outline-primary');
        });
        document.getElementById('btn_ratio_1_1').addEventListener('click', function() {
            if (cropper) cropper.setAspectRatio(1 / 1);
            this.classList.add('active', 'btn-outline-primary');
            document.getElementById('btn_ratio_4_3').classList.remove('active', 'btn-outline-primary');
            document.getElementById('btn_ratio_free').classList.remove('active', 'btn-outline-primary');
        });
        document.getElementById('btn_ratio_free').addEventListener('click', function() {
            if (cropper) cropper.setAspectRatio(NaN);
            this.classList.add('active', 'btn-outline-primary');
            document.getElementById('btn_ratio_4_3').classList.remove('active', 'btn-outline-primary');
            document.getElementById('btn_ratio_1_1').classList.remove('active', 'btn-outline-primary');
        });

        document.getElementById('btn_zoom_in').addEventListener('click', function() {
            if (cropper) cropper.zoom(0.1);
        });
        document.getElementById('btn_zoom_out').addEventListener('click', function() {
            if (cropper) cropper.zoom(-0.1);
        });
        document.getElementById('btn_rotate_left').addEventListener('click', function() {
            if (cropper) cropper.rotate(-90);
        });
        document.getElementById('btn_rotate_right').addEventListener('click', function() {
            if (cropper) cropper.rotate(90);
        });
        document.getElementById('btn_reset_crop').addEventListener('click', function() {
            if (cropper) cropper.reset();
        });

        // Aplicar recorte y redimensión
        document.getElementById('btn_aplicar_recorte').addEventListener('click', function() {
            if (!cropper || currentCropIndex < 0) return;

            // Obtener canvas en alta resolución
            var canvas = cropper.getCroppedCanvas({
                width: 1600,
                height: 1200,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            if (!canvas) {
                alert('No se pudo procesar el recorte de la imagen.');
                return;
            }

            canvas.toBlob(function(blob) {
                var item = stagedPhotos[currentCropIndex];
                item.previewUrl = canvas.toDataURL('image/jpeg', 0.85);
                item.isCropped = true;
                item.croppedBlob = blob;
                item.originalWidth = 1600;
                item.originalHeight = 1200;

                bsModalCropper.hide();
                renderCola();
            }, 'image/jpeg', 0.92);
        });

        // Confirmar y subir fotografías vía AJAX
        btnConfirmarSubida.addEventListener('click', function() {
            if (stagedPhotos.length === 0) return;

            btnConfirmarSubida.disabled = true;
            btnConfirmarSubida.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Subiendo y optimizando...';
            progresoSubida.style.display = 'flex';

            var formData = new FormData();
            formData.append('subir_fotos', '1');
            formData.append('is_ajax', '1');
            formData.append('codigo', '<?php echo addslashes($codigo); ?>');
            formData.append('alt_text', altTextInput.value.trim());

            // Determinar cuál es la principal
            var principalIdx = -1;
            stagedPhotos.forEach(function(item, idx) {
                if (item.isPrincipal) {
                    principalIdx = idx;
                }
            });
            formData.append('principal_index', principalIdx.toString());

            stagedPhotos.forEach(function(item, idx) {
                if (item.isCropped && item.croppedBlob) {
                    formData.append('fotos[]', item.croppedBlob, item.file.name.replace(/\.[^.]+$/, '') + '.jpg');
                } else {
                    formData.append('fotos[]', item.file, item.file.name);
                }
            });

            fetch('editar_producto.php?codigo=' + encodeURIComponent('<?php echo $codigo; ?>'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) {
                return res.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Recargar página para refrescar galería
                    window.location.href = 'editar_producto.php?codigo=' + encodeURIComponent('<?php echo $codigo; ?>') + '&msg=fotos_subidas';
                } else {
                    alert('Error al subir: ' + (data.message || 'Ocurrió un error inesperado'));
                    btnConfirmarSubida.disabled = false;
                    btnConfirmarSubida.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-1"></i> Guardar y Subir Fotografías (' + stagedPhotos.length + ')';
                    progresoSubida.style.display = 'none';
                }
            })
            .catch(function(err) {
                console.error(err);
                alert('Error en la comunicación con el servidor al subir las imágenes.');
                btnConfirmarSubida.disabled = false;
                btnConfirmarSubida.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-1"></i> Guardar y Subir Fotografías (' + stagedPhotos.length + ')';
                progresoSubida.style.display = 'none';
            });
        });

        // Bootstrap validation
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

    })();
    </script>
</body>
</html>
