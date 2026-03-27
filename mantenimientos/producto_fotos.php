<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];
$id_usuario = (int) $_SESSION["id"];
$codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
$title = '';
$body = '';

function productoFotosTableExists(mysqli $link)
{
    $result = mysqli_query($link, "SHOW TABLES LIKE 'cc_productos_fotos'");
    $exists = $result && mysqli_num_rows($result) > 0;
    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);
    }
    return $exists;
}

function productoFotoUploadDir($idSucursal, $codigoProducto)
{
    return __DIR__ . '/../uploads/productos/' . $idSucursal . '/' . $codigoProducto;
}

function productoFotoRelativePath($idSucursal, $codigoProducto, $fileName)
{
    return 'uploads/productos/' . $idSucursal . '/' . $codigoProducto . '/' . $fileName;
}

function productoFotoEnsureDir($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function productoFotoLoadImage($tmpFile, $mimeType)
{
    if ($mimeType === 'image/jpeg') {
        return imagecreatefromjpeg($tmpFile);
    }
    if ($mimeType === 'image/png') {
        return imagecreatefrompng($tmpFile);
    }
    if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return imagecreatefromwebp($tmpFile);
    }
    return false;
}

function productoFotoSaveJpeg($image, $path, $quality = 90)
{
    return imagejpeg($image, $path, $quality);
}

function productoFotoBuildCrop($sourceImage, $targetWidth, $targetHeight)
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
    imagecopyresampled(
        $targetImage,
        $sourceImage,
        0,
        0,
        $srcX,
        $srcY,
        $targetWidth,
        $targetHeight,
        $cropWidth,
        $cropHeight
    );

    return $targetImage;
}

if ($codigo === '') {
    header("location: productos.php");
    exit;
}

$codigoEscaped = mysqli_real_escape_string($link, $codigo);
$productoResult = mysqli_query($link, "
    SELECT p.*, c.desc_categoria
    FROM cc_productos p
    LEFT JOIN cc_categorias c
        ON c.id_sucursal = p.id_sucursal
        AND c.id_categoria = p.id_categoria
    WHERE p.id_sucursal = '$id_sucursal'
        AND p.codigo = '$codigoEscaped'
    LIMIT 1
");
$producto = $productoResult ? mysqli_fetch_assoc($productoResult) : null;

if ($producto === null) {
    header("location: productos.php");
    exit;
}

$tableExists = productoFotosTableExists($link);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_foto'])) {
    if (!$tableExists) {
        $title = 'Tabla faltante';
        $body = 'Primero crea la tabla cc_productos_fotos.';
    } elseif (!isset($_FILES['foto_producto']) || $_FILES['foto_producto']['error'] !== UPLOAD_ERR_OK) {
        $title = 'Sin archivo';
        $body = 'Selecciona una imagen valida.';
    } else {
        $tmpFile = $_FILES['foto_producto']['tmp_name'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $tmpFile);
        finfo_close($fileInfo);

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            $title = 'Formato no valido';
            $body = 'Solo se permiten JPG, PNG o WEBP.';
        } else {
            $sourceImage = productoFotoLoadImage($tmpFile, $mimeType);
            if ($sourceImage === false) {
                $title = 'Imagen invalida';
                $body = 'No se pudo leer la imagen seleccionada.';
            } else {
                $uploadDir = productoFotoUploadDir($id_sucursal, $codigo);
                productoFotoEnsureDir($uploadDir);

                $baseName = $codigo . '_' . date('Ymd_His') . '_' . substr(md5((string) mt_rand()), 0, 8);
                $smallFileName = $baseName . '_sm.jpg';
                $largeFileName = $baseName . '_lg.jpg';
                $smallFullPath = $uploadDir . '/' . $smallFileName;
                $largeFullPath = $uploadDir . '/' . $largeFileName;

                $smallImage = productoFotoBuildCrop($sourceImage, 800, 600);
                $largeImage = productoFotoBuildCrop($sourceImage, 1600, 1200);

                $savedSmall = productoFotoSaveJpeg($smallImage, $smallFullPath, 88);
                $savedLarge = productoFotoSaveJpeg($largeImage, $largeFullPath, 90);

                imagedestroy($smallImage);
                imagedestroy($largeImage);
                imagedestroy($sourceImage);

                if (!$savedSmall || !$savedLarge) {
                    $title = 'Error al guardar';
                    $body = 'No se pudieron escribir los archivos de imagen.';
                } else {
                    $altText = trim((string) ($_POST['alt_text'] ?? $producto['descripcion']));
                    $esPrincipal = isset($_POST['es_principal']) ? 1 : 0;
                    $rutaSmall = mysqli_real_escape_string($link, productoFotoRelativePath($id_sucursal, $codigo, $smallFileName));
                    $rutaLarge = mysqli_real_escape_string($link, productoFotoRelativePath($id_sucursal, $codigo, $largeFileName));
                    $altTextSql = mysqli_real_escape_string($link, $altText);

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
                                '$id_sucursal', '$codigoEscaped', '$rutaSmall', '$rutaLarge', '$altTextSql', '$orden', '$esPrincipal', 1, NOW(), '$id_usuario'
                            )
                        ";

                        if (!mysqli_query($link, $insertSql)) {
                            throw new Exception(mysqli_error($link));
                        }

                        mysqli_commit($link);
                        $title = 'Foto agregada';
                        $body = 'La imagen se guardo en version pequena y grande.';
                    } catch (Exception $exception) {
                        mysqli_rollback($link);
                        if (is_file($smallFullPath)) {
                            unlink($smallFullPath);
                        }
                        if (is_file($largeFullPath)) {
                            unlink($largeFullPath);
                        }
                        $title = 'Error al registrar';
                        $body = $exception->getMessage();
                    }
                }
            }
        }
    }
}

if (isset($_GET['accion']) && $_GET['accion'] === 'principal' && $tableExists) {
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
        header("location: producto_fotos.php?codigo=" . urlencode($codigo));
        exit;
    }
}

if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && $tableExists) {
    $idFoto = isset($_GET['id_foto']) ? (int) $_GET['id_foto'] : 0;
    if ($idFoto > 0) {
        $fotoResult = mysqli_query($link, "
            SELECT ruta_small, ruta_large
            FROM cc_productos_fotos
            WHERE id_foto = '$idFoto'
                AND id_sucursal = '$id_sucursal'
                AND codigo = '$codigoEscaped'
            LIMIT 1
        ");
        $foto = $fotoResult ? mysqli_fetch_assoc($fotoResult) : null;

        if ($foto !== null) {
            mysqli_query($link, "
                DELETE FROM cc_productos_fotos
                WHERE id_foto = '$idFoto'
                    AND id_sucursal = '$id_sucursal'
                    AND codigo = '$codigoEscaped'
            ");

            $smallPath = __DIR__ . '/../' . $foto['ruta_small'];
            $largePath = __DIR__ . '/../' . $foto['ruta_large'];
            if (is_file($smallPath)) {
                unlink($smallPath);
            }
            if (is_file($largePath)) {
                unlink($largePath);
            }
        }
        header("location: producto_fotos.php?codigo=" . urlencode($codigo));
        exit;
    }
}

$fotos = [];
if ($tableExists) {
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
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carniceria Cano">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Fotos de producto</title>
        <link href="../css/bootstrap.css" rel="stylesheet">
        <link href="../css/navbar.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>
                <div class="bg-light p-4 rounded">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h1 class="mb-1">Fotos del producto</h1>
                            <div><strong><?php echo htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <div>Codigo: <?php echo htmlspecialchars($producto['codigo'], ENT_QUOTES, 'UTF-8'); ?> | Categoria: <?php echo htmlspecialchars((string) $producto['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div>
                            <a href="productos.php" class="btn btn-outline-secondary">Volver a productos</a>
                        </div>
                    </div>

                    <hr>

                    <?php if ($title !== ''): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($title . ': ' . $body, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <?php if (!$tableExists): ?>
                        <div class="alert alert-warning">No existe la tabla <code>cc_productos_fotos</code>. Creala primero para poder subir imagenes.</div>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            Las imagenes se recortan automaticamente a proporcion 4:3 y se guardan en dos tamanos:
                            <strong>800x600</strong> y <strong>1600x1200</strong>.
                        </div>

                        <form method="post" enctype="multipart/form-data" class="row g-3">
                            <div class="col-md-5">
                                <label for="foto_producto" class="form-label">Imagen</label>
                                <input type="file" class="form-control" id="foto_producto" name="foto_producto" accept=".jpg,.jpeg,.png,.webp" required>
                            </div>
                            <div class="col-md-5">
                                <label for="alt_text" class="form-label">Texto alternativo</label>
                                <input type="text" class="form-control" id="alt_text" name="alt_text" value="<?php echo htmlspecialchars($producto['descripcion'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-block">Principal</label>
                                <div class="form-check pt-2">
                                    <input class="form-check-input" type="checkbox" id="es_principal" name="es_principal" value="1" <?php echo empty($fotos) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="es_principal">Si</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="subir_foto" class="btn btn-primary">Subir foto</button>
                            </div>
                        </form>

                        <hr>

                        <div class="row g-4">
                            <?php if (empty($fotos)): ?>
                                <div class="col-12">
                                    <div class="alert alert-light border">Este producto todavia no tiene fotos.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($fotos as $foto): ?>
                                    <div class="col-md-6 col-xl-4">
                                        <div class="card h-100">
                                            <img src="../<?php echo htmlspecialchars($foto['ruta_small'], ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($foto['alt_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <?php if ((int) $foto['es_principal'] === 1): ?>
                                                        <span class="badge text-bg-primary">Principal</span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-secondary">Secundaria</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted mb-2"><?php echo htmlspecialchars($foto['alt_text'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small">Orden: <?php echo (int) $foto['orden']; ?></div>
                                            </div>
                                            <div class="card-footer bg-white d-flex gap-2 flex-wrap">
                                                <?php if ((int) $foto['es_principal'] !== 1): ?>
                                                    <a href="?codigo=<?php echo urlencode($codigo); ?>&accion=principal&id_foto=<?php echo (int) $foto['id_foto']; ?>" class="btn btn-outline-primary btn-sm">Marcar principal</a>
                                                <?php endif; ?>
                                                <a href="../<?php echo htmlspecialchars($foto['ruta_large'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Ver grande</a>
                                                <a href="?codigo=<?php echo urlencode($codigo); ?>&accion=eliminar&id_foto=<?php echo (int) $foto['id_foto']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Eliminar esta foto?');">Eliminar</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <script src="../js/bootstrap.bundle.min.js"></script>
    </body>
</html>
