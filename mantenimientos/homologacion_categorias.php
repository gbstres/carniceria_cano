<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: ../login/login.php');
    exit;
}

require_once '../functions/config.php';
date_default_timezone_set('America/Mexico_City');

if (!tienePermiso('ver')) {
    http_response_code(403);
    exit('No tienes permiso para administrar la homologación de categorías.');
}

$idUsuario = (int) ($_SESSION['id'] ?? 0);
$fecha = date('Y-m-d');
$hora = date('H:i:s');
$title = '';
$body = '';
if (empty($_SESSION['csrf_homologacion_categorias'])) {
    $_SESSION['csrf_homologacion_categorias'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_homologacion_categorias'];

function homologacionCrearTablas(mysqli $link): void
{
    $sqlGlobales = "CREATE TABLE IF NOT EXISTS cc_categorias_globales (
        id_categoria_global INT NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        id_usuario INT NOT NULL DEFAULT 0,
        fecha_ingreso DATE NOT NULL,
        hora_ingreso TIME NOT NULL,
        id_usuario_act INT NOT NULL DEFAULT 0,
        fecha_act DATE NULL,
        hora_act TIME NULL,
        PRIMARY KEY (id_categoria_global),
        UNIQUE KEY uq_categorias_globales_nombre (nombre),
        KEY idx_categorias_globales_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci";

    $sqlRelaciones = "CREATE TABLE IF NOT EXISTS cc_categorias_homologacion (
        id_sucursal INT NOT NULL,
        id_categoria INT NOT NULL,
        id_categoria_global INT NOT NULL,
        id_usuario INT NOT NULL DEFAULT 0,
        fecha_ingreso DATE NOT NULL,
        hora_ingreso TIME NOT NULL,
        id_usuario_act INT NOT NULL DEFAULT 0,
        fecha_act DATE NULL,
        hora_act TIME NULL,
        PRIMARY KEY (id_sucursal, id_categoria),
        UNIQUE KEY uq_homologacion_global_sucursal (id_categoria_global, id_sucursal),
        KEY idx_categorias_homologacion_global (id_categoria_global)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci";

    if (!mysqli_query($link, $sqlGlobales) || !mysqli_query($link, $sqlRelaciones)) {
        throw new RuntimeException('No se pudieron preparar las tablas: ' . mysqli_error($link));
    }
}

function homologacionNormalizar(string $nombre): string
{
    $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
    $nombre = strtr($nombre, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $nombre = preg_replace('/[^A-Z0-9]+/u', ' ', $nombre);
    return trim(preg_replace('/\s+/', ' ', (string) $nombre));
}

function homologacionGlobalExiste(mysqli $link, int $idGlobal): bool
{
    $rs = mysqli_query($link, "SELECT 1 FROM cc_categorias_globales WHERE id_categoria_global = $idGlobal LIMIT 1");
    return $rs && mysqli_num_rows($rs) === 1;
}

function homologacionLocalExiste(mysqli $link, int $idSucursal, int $idCategoria): bool
{
    $rs = mysqli_query($link, "SELECT 1 FROM cc_categorias WHERE id_sucursal = $idSucursal AND id_categoria = $idCategoria LIMIT 1");
    return $rs && mysqli_num_rows($rs) === 1;
}

try {
    homologacionCrearTablas($link);
} catch (Throwable $e) {
    $title = 'Error';
    $body = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $title === '') {
    $tokenRecibido = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $tokenRecibido)) {
        http_response_code(403);
        exit('La sesión del formulario venció. Recarga la página e inténtalo nuevamente.');
    }
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'guardar_global') {
        $idGlobal = (int) ($_POST['id_categoria_global'] ?? 0);
        $nombre = mb_strtoupper(trim((string) ($_POST['nombre'] ?? '')), 'UTF-8');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            $title = 'No guardado';
            $body = 'Ingresa el nombre de la categoría global.';
        } else {
            $nombreEsc = mysqli_real_escape_string($link, $nombre);
            mysqli_begin_transaction($link);
            try {
                if ($idGlobal > 0) {
                    $sql = "UPDATE cc_categorias_globales
                        SET nombre = '$nombreEsc', activo = $activo, id_usuario_act = $idUsuario,
                            fecha_act = '$fecha', hora_act = '$hora'
                        WHERE id_categoria_global = $idGlobal";
                } else {
                    $sql = "INSERT INTO cc_categorias_globales
                        (nombre, activo, id_usuario, fecha_ingreso, hora_ingreso)
                        VALUES ('$nombreEsc', $activo, $idUsuario, '$fecha', '$hora')";
                }
                if (!mysqli_query($link, $sql)) {
                    throw new RuntimeException(mysqli_error($link), mysqli_errno($link));
                }
                if ($idGlobal === 0) {
                    $idGlobal = (int) mysqli_insert_id($link);
                }

                $asignaciones = is_array($_POST['categoria_sucursal'] ?? null)
                    ? $_POST['categoria_sucursal']
                    : [];
                foreach ($asignaciones as $idSucursalRaw => $idCategoriaRaw) {
                    $idSucursal = (int) $idSucursalRaw;
                    $idCategoria = (int) $idCategoriaRaw;
                    if ($idSucursal <= 0) {
                        continue;
                    }

                    if (!mysqli_query($link, "DELETE FROM cc_categorias_homologacion
                        WHERE id_categoria_global = $idGlobal AND id_sucursal = $idSucursal")) {
                        throw new RuntimeException(mysqli_error($link));
                    }
                    if ($idCategoria === 0) {
                        continue;
                    }
                    if (!homologacionLocalExiste($link, $idSucursal, $idCategoria)) {
                        throw new RuntimeException("La categoría $idCategoria no pertenece a la sucursal $idSucursal.");
                    }
                    $sqlAsignacion = "INSERT INTO cc_categorias_homologacion
                            (id_sucursal, id_categoria, id_categoria_global, id_usuario, fecha_ingreso, hora_ingreso,
                             id_usuario_act, fecha_act, hora_act)
                        VALUES
                            ($idSucursal, $idCategoria, $idGlobal, $idUsuario, '$fecha', '$hora',
                             $idUsuario, '$fecha', '$hora')
                        ON DUPLICATE KEY UPDATE
                            id_categoria_global = VALUES(id_categoria_global), id_usuario_act = VALUES(id_usuario_act),
                            fecha_act = VALUES(fecha_act), hora_act = VALUES(hora_act)";
                    if (!mysqli_query($link, $sqlAsignacion)) {
                        throw new RuntimeException(mysqli_error($link));
                    }
                }
                mysqli_commit($link);
                $title = 'Guardado';
                $body = 'La categoría global y sus correspondencias se guardaron correctamente.';
            } catch (Throwable $e) {
                mysqli_rollback($link);
                $title = 'No guardado';
                $body = (int) $e->getCode() === 1062
                    ? 'Ya existe una categoría global con ese nombre.'
                    : $e->getMessage();
            }
        }
    } elseif ($accion === 'asignar') {
        $idSucursal = (int) ($_POST['id_sucursal'] ?? 0);
        $idCategoria = (int) ($_POST['id_categoria'] ?? 0);
        $idGlobal = (int) ($_POST['id_categoria_global'] ?? 0);

        if (!homologacionLocalExiste($link, $idSucursal, $idCategoria)) {
            $title = 'No guardado';
            $body = 'La categoría local seleccionada no existe.';
        } elseif ($idGlobal === 0) {
            $sql = "DELETE FROM cc_categorias_homologacion
                WHERE id_sucursal = $idSucursal AND id_categoria = $idCategoria";
            if (mysqli_query($link, $sql)) {
                $title = 'Guardado';
                $body = 'La categoría quedó pendiente de homologar.';
            } else {
                $title = 'No guardado';
                $body = mysqli_error($link);
            }
        } elseif (!homologacionGlobalExiste($link, $idGlobal)) {
            $title = 'No guardado';
            $body = 'La categoría global seleccionada no existe.';
        } else {
            $sql = "INSERT INTO cc_categorias_homologacion
                    (id_sucursal, id_categoria, id_categoria_global, id_usuario, fecha_ingreso, hora_ingreso,
                     id_usuario_act, fecha_act, hora_act)
                VALUES
                    ($idSucursal, $idCategoria, $idGlobal, $idUsuario, '$fecha', '$hora', $idUsuario, '$fecha', '$hora')
                ON DUPLICATE KEY UPDATE
                    id_categoria_global = VALUES(id_categoria_global), id_usuario_act = VALUES(id_usuario_act),
                    fecha_act = VALUES(fecha_act), hora_act = VALUES(hora_act)";
            if (mysqli_query($link, $sql)) {
                $title = 'Guardado';
                $body = 'Homologación actualizada correctamente.';
            } else {
                $title = 'No guardado';
                $body = mysqli_error($link);
            }
        }
    } elseif ($accion === 'homologar_exactas') {
        $globalesPorNombre = [];
        $rs = mysqli_query($link, 'SELECT id_categoria_global, nombre FROM cc_categorias_globales');
        while ($rs && $row = mysqli_fetch_assoc($rs)) {
            $globalesPorNombre[homologacionNormalizar($row['nombre'])] = (int) $row['id_categoria_global'];
        }

        $creadas = 0;
        $asignadas = 0;
        mysqli_begin_transaction($link);
        try {
            $rs = mysqli_query($link, "SELECT c.id_sucursal, c.id_categoria, c.desc_categoria
                FROM cc_categorias c
                LEFT JOIN cc_categorias_homologacion h
                    ON h.id_sucursal = c.id_sucursal AND h.id_categoria = c.id_categoria
                WHERE c.activo = 1 AND h.id_categoria IS NULL
                ORDER BY c.desc_categoria, c.id_sucursal");
            if (!$rs) {
                throw new RuntimeException(mysqli_error($link));
            }

            while ($row = mysqli_fetch_assoc($rs)) {
                $clave = homologacionNormalizar($row['desc_categoria']);
                if ($clave === '') {
                    continue;
                }
                if (!isset($globalesPorNombre[$clave])) {
                    $nombreEsc = mysqli_real_escape_string($link, mb_strtoupper(trim($row['desc_categoria']), 'UTF-8'));
                    if (!mysqli_query($link, "INSERT INTO cc_categorias_globales
                        (nombre, activo, id_usuario, fecha_ingreso, hora_ingreso)
                        VALUES ('$nombreEsc', 1, $idUsuario, '$fecha', '$hora')")) {
                        throw new RuntimeException(mysqli_error($link));
                    }
                    $globalesPorNombre[$clave] = (int) mysqli_insert_id($link);
                    $creadas++;
                }

                $idGlobal = $globalesPorNombre[$clave];
                $idSucursal = (int) $row['id_sucursal'];
                $idCategoria = (int) $row['id_categoria'];
                if (!mysqli_query($link, "INSERT INTO cc_categorias_homologacion
                    (id_sucursal, id_categoria, id_categoria_global, id_usuario, fecha_ingreso, hora_ingreso)
                    VALUES ($idSucursal, $idCategoria, $idGlobal, $idUsuario, '$fecha', '$hora')")) {
                    throw new RuntimeException(mysqli_error($link));
                }
                $asignadas++;
            }
            mysqli_commit($link);
            $title = 'Homologación terminada';
            $body = "Se crearon $creadas categorías globales y se asignaron $asignadas categorías locales.";
        } catch (Throwable $e) {
            mysqli_rollback($link);
            $title = 'No se pudo homologar';
            $body = $e->getMessage();
        }
    }
}

$globales = [];
$rsGlobales = mysqli_query($link, 'SELECT * FROM cc_categorias_globales ORDER BY activo DESC, nombre');
while ($rsGlobales && $row = mysqli_fetch_assoc($rsGlobales)) {
    $globales[] = $row;
}
$nombresGlobales = [];
foreach ($globales as $global) {
    $nombresGlobales[(int) $global['id_categoria_global']] = $global['nombre'];
}

$filtroSucursal = (int) ($_GET['id_sucursal'] ?? 0);
$soloPendientes = isset($_GET['pendientes']) && $_GET['pendientes'] === '1';
$condiciones = ['s.activo = 1'];
if ($filtroSucursal > 0) {
    $condiciones[] = "c.id_sucursal = $filtroSucursal";
}
if ($soloPendientes) {
    $condiciones[] = 'h.id_categoria_global IS NULL';
}
$where = implode(' AND ', $condiciones);

$sucursales = [];
$rsSucursales = mysqli_query($link, "SELECT id_sucursal, desc_sucursal FROM cc_sucursales
    WHERE activo = 1 AND UPPER(desc_sucursal) NOT LIKE '%PRUEBAS%' ORDER BY desc_sucursal");
while ($rsSucursales && $row = mysqli_fetch_assoc($rsSucursales)) {
    $sucursales[] = $row;
}

$categoriasPorSucursal = [];
$rsCategoriasSelector = mysqli_query($link, "SELECT c.id_sucursal, c.id_categoria, c.desc_categoria,
        h.id_categoria_global
    FROM cc_categorias c
    LEFT JOIN cc_categorias_homologacion h
        ON h.id_sucursal = c.id_sucursal AND h.id_categoria = c.id_categoria
    WHERE c.activo = 1
    ORDER BY c.id_sucursal, c.desc_categoria");
while ($rsCategoriasSelector && $row = mysqli_fetch_assoc($rsCategoriasSelector)) {
    $categoriasPorSucursal[(int) $row['id_sucursal']][] = $row;
}

$asignacionesPorGlobal = [];
$rsAsignaciones = mysqli_query($link, 'SELECT id_categoria_global, id_sucursal, id_categoria FROM cc_categorias_homologacion');
while ($rsAsignaciones && $row = mysqli_fetch_assoc($rsAsignaciones)) {
    $asignacionesPorGlobal[(int) $row['id_categoria_global']][(int) $row['id_sucursal']] = (int) $row['id_categoria'];
}

$rsLocales = mysqli_query($link, "SELECT c.id_sucursal, c.id_categoria, c.desc_categoria, c.activo,
        s.desc_sucursal, h.id_categoria_global, g.nombre AS nombre_global
    FROM cc_categorias c
    INNER JOIN cc_sucursales s ON s.id_sucursal = c.id_sucursal
    LEFT JOIN cc_categorias_homologacion h
        ON h.id_sucursal = c.id_sucursal AND h.id_categoria = c.id_categoria
    LEFT JOIN cc_categorias_globales g ON g.id_categoria_global = h.id_categoria_global
    WHERE $where
    ORDER BY s.desc_sucursal, c.desc_categoria");

$totalLocales = 0;
$totalPendientes = 0;
$rsConteo = mysqli_query($link, "SELECT COUNT(*) AS total,
        SUM(CASE WHEN h.id_categoria_global IS NULL THEN 1 ELSE 0 END) AS pendientes
    FROM cc_categorias c
    INNER JOIN cc_sucursales s ON s.id_sucursal = c.id_sucursal AND s.activo = 1
    LEFT JOIN cc_categorias_homologacion h
        ON h.id_sucursal = c.id_sucursal AND h.id_categoria = c.id_categoria
    WHERE c.activo = 1 AND UPPER(s.desc_sucursal) NOT LIKE '%PRUEBAS%'");
if ($rsConteo && $row = mysqli_fetch_assoc($rsConteo)) {
    $totalLocales = (int) $row['total'];
    $totalPendientes = (int) $row['pendientes'];
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Homologación de categorías</title>
        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <style>@import "../css/bootstrap.css";</style>
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once '../components/nav.php'; ?>
                <div class="bg-light p-4 rounded">
                    <h1 class="text-center">Homologación de categorías</h1>
                    <p class="text-muted text-center">Crea una categoría global y selecciona su categoría correspondiente en cada sucursal. El campo mayoreo no participa.</p>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Categorías locales activas</div><div class="fs-3 fw-bold"><?php echo $totalLocales; ?></div></div></div></div>
                        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Pendientes</div><div class="fs-3 fw-bold text-warning"><?php echo $totalPendientes; ?></div></div></div></div>
                        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Categorías globales</div><div class="fs-3 fw-bold"><?php echo count($globales); ?></div></div></div></div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">Categoría global y correspondencias por sucursal</div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="accion" value="guardar_global">
                                <input type="hidden" name="id_categoria_global" id="id_categoria_global" value="0">
                                <div class="col-md-7">
                                    <label class="form-label" for="nombre">Nombre</label>
                                    <input class="form-control text-uppercase" type="text" name="nombre" id="nombre" required maxlength="100">
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mt-4 pt-2">
                                        <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" checked>
                                        <label class="form-check-label" for="activo">Activa</label>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end pt-4">
                                    <button class="btn btn-outline-secondary mt-2" id="nueva_global" type="button">Nueva</button>
                                    <button class="btn btn-primary mt-2" type="submit">Guardar homologación</button>
                                </div>
                                <div class="col-12"><hr class="my-1"></div>
                                <?php foreach ($sucursales as $sucursal) { ?>
                                    <?php $idSucursalSelector = (int) $sucursal['id_sucursal']; ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label" for="categoria_sucursal_<?php echo $idSucursalSelector; ?>"><?php echo htmlspecialchars($sucursal['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></label>
                                        <select class="form-select selector-sucursal" id="categoria_sucursal_<?php echo $idSucursalSelector; ?>" name="categoria_sucursal[<?php echo $idSucursalSelector; ?>]" data-sucursal="<?php echo $idSucursalSelector; ?>">
                                            <option value="0">— No existe / pendiente —</option>
                                            <?php foreach ($categoriasPorSucursal[$idSucursalSelector] ?? [] as $categoriaLocal) { ?>
                                                <option value="<?php echo (int) $categoriaLocal['id_categoria']; ?>">
                                                    <?php
                                                    $idGlobalAsignada = (int) ($categoriaLocal['id_categoria_global'] ?? 0);
                                                    $sufijoAsignacion = $idGlobalAsignada > 0
                                                        ? ' — asignada a ' . ($nombresGlobales[$idGlobalAsignada] ?? ('global ' . $idGlobalAsignada))
                                                        : '';
                                                    echo htmlspecialchars($categoriaLocal['desc_categoria'] . $sufijoAsignacion, ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                <?php } ?>
                            </form>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-end mb-3">
                        <form method="get" class="row g-2 align-items-end">
                            <div class="col-auto">
                                <label class="form-label" for="filtro_sucursal">Sucursal</label>
                                <select class="form-select" id="filtro_sucursal" name="id_sucursal">
                                    <option value="0">Todas</option>
                                    <?php foreach ($sucursales as $sucursal) { ?>
                                        <option value="<?php echo (int) $sucursal['id_sucursal']; ?>" <?php echo $filtroSucursal === (int) $sucursal['id_sucursal'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sucursal['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-auto form-check ms-2 mb-2">
                                <input class="form-check-input" type="checkbox" name="pendientes" id="pendientes" value="1" <?php echo $soloPendientes ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pendientes">Sólo pendientes</label>
                            </div>
                            <div class="col-auto"><button class="btn btn-secondary" type="submit">Filtrar</button></div>
                        </form>
                    </div>

                    <h2 class="h5">Estado de categorías locales</h2>
                    <div class="table-responsive">
                        <table id="homologaciones" class="display" style="width:100%">
                            <thead><tr><th>Sucursal</th><th>ID local</th><th>Categoría local</th><th>Categoría global</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php while ($rsLocales && $row = mysqli_fetch_assoc($rsLocales)) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['desc_sucursal'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $row['id_categoria']; ?></td>
                                        <td><?php echo htmlspecialchars($row['desc_categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($row['nombre_global'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $row['id_categoria_global'] === null ? '<span class="badge bg-warning text-dark">Pendiente</span>' : '<span class="badge bg-success">Homologada</span>'; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <hr>
                    <h2 class="h5">Catálogo global</h2>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>ID</th><th>Nombre</th><th>Estado</th><th></th></tr></thead>
                            <tbody><?php foreach ($globales as $global) { ?>
                                <?php $asignacionesJson = json_encode($asignacionesPorGlobal[(int) $global['id_categoria_global']] ?? [], JSON_UNESCAPED_UNICODE); ?>
                                <tr><td><?php echo (int) $global['id_categoria_global']; ?></td><td><?php echo htmlspecialchars($global['nombre'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $global['activo'] === 1 ? 'Activa' : 'Inactiva'; ?></td><td><button type="button" class="btn btn-sm btn-outline-secondary editar-global" data-id="<?php echo (int) $global['id_categoria_global']; ?>" data-nombre="<?php echo htmlspecialchars($global['nombre'], ENT_QUOTES, 'UTF-8'); ?>" data-activo="<?php echo (int) $global['activo']; ?>" data-asignaciones="<?php echo htmlspecialchars($asignacionesJson, ENT_QUOTES, 'UTF-8'); ?>">Editar</button></td></tr>
                            <?php } ?></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <?php if ($title !== '') { ?>
            <div class="modal fade" id="modalRespuesta" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><?php echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); ?></div><div class="modal-footer"><button class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>
        <?php } ?>
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(function () {
                $('#homologaciones').DataTable({
                    pageLength: 50,
                    language: {emptyTable: 'No hay categorías', info: 'Mostrando _START_ a _END_ de _TOTAL_', infoEmpty: 'Mostrando 0 a 0 de 0', infoFiltered: '(filtrado de _MAX_)', lengthMenu: 'Mostrar _MENU_', search: 'Buscar:', zeroRecords: 'Sin resultados', paginate: {next: 'Siguiente', previous: 'Anterior'}}
                });
                $('#modalRespuesta').modal('show');
                $('#nueva_global').on('click', function () {
                    $('#id_categoria_global').val('0');
                    $('#nombre').val('').focus();
                    $('#activo').prop('checked', true);
                    $('.selector-sucursal').val('0');
                });
                $('.editar-global').on('click', function () {
                    $('#id_categoria_global').val($(this).data('id'));
                    $('#nombre').val($(this).data('nombre')).focus();
                    $('#activo').prop('checked', Number($(this).data('activo')) === 1);
                    $('.selector-sucursal').val('0');
                    var asignaciones = {};
                    try {
                        asignaciones = JSON.parse(this.getAttribute('data-asignaciones') || '{}');
                    } catch (e) {
                        asignaciones = {};
                    }
                    Object.keys(asignaciones).forEach(function (idSucursal) {
                        $('#categoria_sucursal_' + idSucursal).val(String(asignaciones[idSucursal]));
                    });
                    window.scrollTo({top: 0, behavior: 'smooth'});
                });
            });
        </script>
    </body>
</html>
