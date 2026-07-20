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
$fecha = date('Y-m-d');
$hora = date('H:i:s');
$title = "";
$body = "";

function asegurarTablaEquivalencias(mysqli $link): void
{
    $sql = "CREATE TABLE IF NOT EXISTS cc_equivalencias_productos (
        id_sucursal INT NOT NULL,
        codigo_origen VARCHAR(10) NOT NULL,
        codigo_destino VARCHAR(10) NOT NULL,
        factor DECIMAL(10,4) NOT NULL DEFAULT 1,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        id_usuario INT NOT NULL DEFAULT 0,
        fecha_ingreso DATE NOT NULL,
        hora_ingreso TIME NOT NULL,
        id_usuario_act INT NOT NULL DEFAULT 0,
        fecha_act DATE NOT NULL,
        hora_act TIME NOT NULL,
        PRIMARY KEY (id_sucursal, codigo_origen),
        KEY idx_equivalencias_destino (id_sucursal, codigo_destino),
        KEY idx_equivalencias_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci";

    if (!mysqli_query($link, $sql)) {
        throw new RuntimeException("No se pudo crear cc_equivalencias_productos: " . mysqli_error($link));
    }
}

function productoExiste(mysqli $link, int $idSucursal, string $codigo): bool
{
    $codigoEscaped = mysqli_real_escape_string($link, $codigo);
    $rs = mysqli_query($link, "SELECT 1 FROM cc_productos WHERE id_sucursal = $idSucursal AND codigo = '$codigoEscaped' LIMIT 1");
    return $rs && mysqli_num_rows($rs) > 0;
}

try {
    asegurarTablaEquivalencias($link);
} catch (Throwable $e) {
    $title = "Error";
    $body = $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $title === "") {
    $codigo_origen = trim((string) ($_POST["codigo_origen"] ?? ""));
    $codigo_destino = trim((string) ($_POST["codigo_destino"] ?? ""));
    $factor = (float) ($_POST["factor"] ?? 1);
    $activo = isset($_POST["activo"]) ? 1 : 0;

    if ($codigo_origen === "" || $codigo_destino === "") {
        $title = "No guardado";
        $body = "Selecciona producto origen y producto destino.";
    } elseif ($codigo_origen === $codigo_destino) {
        $title = "No guardado";
        $body = "El producto origen y destino no pueden ser el mismo.";
    } elseif ($factor <= 0) {
        $title = "No guardado";
        $body = "El factor debe ser mayor a cero.";
    } elseif (!productoExiste($link, $id_sucursal, $codigo_origen) || !productoExiste($link, $id_sucursal, $codigo_destino)) {
        $title = "No guardado";
        $body = "Uno de los productos seleccionados no existe en esta sucursal.";
    } else {
        $codigoOrigenEsc = mysqli_real_escape_string($link, $codigo_origen);
        $codigoDestinoEsc = mysqli_real_escape_string($link, $codigo_destino);
        $factorSql = number_format($factor, 4, '.', '');

        $sql = "INSERT INTO cc_equivalencias_productos
                (id_sucursal, codigo_origen, codigo_destino, factor, activo, id_usuario, fecha_ingreso, hora_ingreso, id_usuario_act, fecha_act, hora_act)
            VALUES
                ($id_sucursal, '$codigoOrigenEsc', '$codigoDestinoEsc', $factorSql, $activo, $id_usuario, '$fecha', '$hora', $id_usuario, '$fecha', '$hora')
            ON DUPLICATE KEY UPDATE
                codigo_destino = VALUES(codigo_destino),
                factor = VALUES(factor),
                activo = VALUES(activo),
                id_usuario_act = VALUES(id_usuario_act),
                fecha_act = VALUES(fecha_act),
                hora_act = VALUES(hora_act)";

        if (mysqli_query($link, $sql)) {
            $title = "Guardado";
            $body = "Equivalencia guardada correctamente.";
        } else {
            $title = "No guardado";
            $body = mysqli_error($link);
        }
    }
}

if (isset($_GET["accion"], $_GET["codigo_origen"]) && $_GET["accion"] === "delete" && $title === "") {
    $codigoOrigenEsc = mysqli_real_escape_string($link, (string) $_GET["codigo_origen"]);
    $sql = "DELETE FROM cc_equivalencias_productos
        WHERE id_sucursal = $id_sucursal
          AND codigo_origen = '$codigoOrigenEsc'";

    if (mysqli_query($link, $sql)) {
        $title = "Eliminado";
        $body = "Equivalencia eliminada correctamente.";
    } else {
        $title = "No eliminado";
        $body = mysqli_error($link);
    }
}

$productos = [];
$rsProductos = mysqli_query($link, "SELECT codigo, descripcion FROM cc_productos WHERE id_sucursal = $id_sucursal ORDER BY descripcion, codigo");
while ($rsProductos && $row = mysqli_fetch_assoc($rsProductos)) {
    $productos[] = $row;
}

$rsEquivalencias = mysqli_query($link, "
    SELECT
        e.*,
        po.descripcion AS descripcion_origen,
        pd.descripcion AS descripcion_destino
    FROM cc_equivalencias_productos e
    LEFT JOIN cc_productos po
        ON po.id_sucursal = e.id_sucursal
       AND po.codigo = e.codigo_origen
    LEFT JOIN cc_productos pd
        ON pd.id_sucursal = e.id_sucursal
       AND pd.codigo = e.codigo_destino
    WHERE e.id_sucursal = $id_sucursal
    ORDER BY e.activo DESC, po.descripcion, e.codigo_origen
");
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Equivalencias de productos</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>
        <style>
            @import "../css/bootstrap.css";
        </style>
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>

                <div class="bg-light p-4 rounded">
                    <div class="col-sm-8 mx-auto">
                        <h1 class="text-center">Equivalencias de productos</h1>
                    </div>

                    <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                        <div class="col-md-5">
                            <label class="form-label">Producto origen</label>
                            <select class="form-select" name="codigo_origen" id="codigo_origen" required>
                                <option selected disabled value="">Seleccione...</option>
                                <?php foreach ($productos as $producto) { ?>
                                    <option value="<?php echo htmlspecialchars($producto["codigo"], ENT_QUOTES, "UTF-8"); ?>">
                                        <?php echo htmlspecialchars($producto["codigo"] . " - " . $producto["descripcion"], ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="invalid-feedback">Selecciona producto origen.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Producto destino de inventario</label>
                            <select class="form-select" name="codigo_destino" id="codigo_destino" required>
                                <option selected disabled value="">Seleccione...</option>
                                <?php foreach ($productos as $producto) { ?>
                                    <option value="<?php echo htmlspecialchars($producto["codigo"], ENT_QUOTES, "UTF-8"); ?>">
                                        <?php echo htmlspecialchars($producto["codigo"] . " - " . $producto["descripcion"], ENT_QUOTES, "UTF-8"); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="invalid-feedback">Selecciona producto destino.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Factor</label>
                            <input type="number" step="0.0001" min="0.0001" class="form-control" name="factor" id="factor" value="1" required>
                            <div class="invalid-feedback">Ingresa factor.</div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" name="activo" type="checkbox" value="1" id="activo" checked>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                        <div class="col-md-9 text-end">
                            <button type="submit" class="btn btn-primary mt-3">Guardar equivalencia</button>
                        </div>
                    </form>

                    <hr>

                    <div class="table-responsive">
                        <table id="equivalencias" class="display" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Origen</th>
                                    <th>Destino inventario</th>
                                    <th>Factor</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rsEquivalencias && $row = mysqli_fetch_assoc($rsEquivalencias)) { ?>
                                    <tr>
                                        <td data-codigo="<?php echo htmlspecialchars($row["codigo_origen"], ENT_QUOTES, "UTF-8"); ?>">
                                            <?php echo htmlspecialchars($row["codigo_origen"] . " - " . ($row["descripcion_origen"] ?? ""), ENT_QUOTES, "UTF-8"); ?>
                                        </td>
                                        <td data-codigo="<?php echo htmlspecialchars($row["codigo_destino"], ENT_QUOTES, "UTF-8"); ?>">
                                            <?php echo htmlspecialchars($row["codigo_destino"] . " - " . ($row["descripcion_destino"] ?? ""), ENT_QUOTES, "UTF-8"); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row["factor"], ENT_QUOTES, "UTF-8"); ?></td>
                                        <td class="<?php echo (int) $row["activo"]; ?>">
                                            <input type="checkbox" class="form-check-input" disabled <?php echo ((int) $row["activo"] === 1 ? "checked" : ""); ?>>
                                        </td>
                                        <td align="center">
                                            <a href="#" title="Editar equivalencia" data-bs-toggle="modal" data-bs-target="#editModal"><img class="imga" src="../img/icons/pencil-square.svg"></a>
                                            <a href="?accion=delete&codigo_origen=<?php echo urlencode($row["codigo_origen"]); ?>" title="Eliminar" onclick="return confirm('¿Eliminar esta equivalencia?')"><img class="imga" src="../img/icons/trash.svg"></a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <div class="modal fade modal-lg" id="editModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabelTitle">Editar equivalencia</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Producto origen</label>
                                <select class="form-select" name="codigo_origen" id="codigo_origen_e" required>
                                    <?php foreach ($productos as $producto) { ?>
                                        <option value="<?php echo htmlspecialchars($producto["codigo"], ENT_QUOTES, "UTF-8"); ?>">
                                            <?php echo htmlspecialchars($producto["codigo"] . " - " . $producto["descripcion"], ENT_QUOTES, "UTF-8"); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Producto destino de inventario</label>
                                <select class="form-select" name="codigo_destino" id="codigo_destino_e" required>
                                    <?php foreach ($productos as $producto) { ?>
                                        <option value="<?php echo htmlspecialchars($producto["codigo"], ENT_QUOTES, "UTF-8"); ?>">
                                            <?php echo htmlspecialchars($producto["codigo"] . " - " . $producto["descripcion"], ENT_QUOTES, "UTF-8"); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Factor</label>
                                <input type="number" step="0.0001" min="0.0001" class="form-control" name="factor" id="factor_e" required>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" name="activo" type="checkbox" value="1" id="activo_e">
                                <label class="form-check-label" for="activo_e">Activo</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($title !== "") { ?>
            <div class="modal fade" id="ModalRespuesta" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5"><?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?></h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body"><?php echo htmlspecialchars($body, ENT_QUOTES, "UTF-8"); ?></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {
                $('#equivalencias').DataTable({
                    language: {
                        "emptyTable": "No hay equivalencias",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_",
                        "infoEmpty": "Mostrando 0 a 0 de 0",
                        "infoFiltered": "(Filtrado de _MAX_ total)",
                        "lengthMenu": "Mostrar _MENU_",
                        "search": "Buscar:",
                        "zeroRecords": "Sin resultados",
                        "paginate": {"first": "Primero", "last": "Último", "next": "Siguiente", "previous": "Anterior"}
                    }
                });

                $('#ModalRespuesta').modal('show');
            });

            document.getElementById('editModal').addEventListener('show.bs.modal', function (event) {
                var icono = event.relatedTarget;
                var fila = $(icono).parents('tr');
                $('#codigo_origen_e').val(fila.find('td').eq(0).data('codigo'));
                $('#codigo_destino_e').val(fila.find('td').eq(1).data('codigo'));
                $('#factor_e').val(fila.find('td').eq(2).text().trim());
                $('#activo_e').prop('checked', fila.find('td').eq(3).attr('class') === '1');
            });

            (function () {
                'use strict';
                var forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms).forEach(function (form) {
                    form.addEventListener('submit', function (event) {
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
