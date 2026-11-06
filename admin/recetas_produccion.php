<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/sync_queue.php";
date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];
$id_usuario = (int) $_SESSION["id"];

$codigo_editar = isset($_GET['codigo_p']) ? trim((string) $_GET['codigo_p']) : '';
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicerías &quot;EL Cinco&quot;">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Recetas de Producción</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/typeahead.js"></script>

        <link href="../css/bootstrap.css" rel="stylesheet">
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../css/bootstrap-icons.css">

        <style>
            .typeahead {
                border: 1px solid #ced4da;
                border-radius: 4px;
                padding: 8px 12px;
                width: 100%;
                color: #212529;
                background-color: #fff;
            }
            .tt-menu {
                width: 100%;
                background-color: #fff;
                border: 1px solid #ced4da;
                border-radius: 4px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                max-height: 280px;
                overflow-y: auto;
            }
            ul.typeahead {
                margin: 0px;
                padding: 0px;
                list-style: none;
            }
            ul.typeahead.dropdown-menu li a {
                padding: 8px 12px !important;
                display: block;
                color: #333;
                text-decoration: none;
                border-bottom: 1px solid #eee;
            }
            ul.typeahead.dropdown-menu li:last-child a {
                border-bottom: 0px !important;
            }
            .dropdown-menu>.active>a, .dropdown-menu>.active>a:focus, .dropdown-menu>.active>a:hover {
                text-decoration: none;
                background-color: #0d6efd;
                color: #fff !important;
            }
            .table-insumos th, .table-insumos td {
                vertical-align: middle;
            }
        </style>
    </head>
    <body>
        <main>
            <div class="container pb-5">
                <?php require_once "../components/nav.php" ?>

                <div class="mt-4">
                    <div class="bg-light p-4 rounded shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="mb-0 text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Recetas de Producción / Fórmulas</h2>
                                <small class="text-muted">Define los insumos necesarios (de cualquier categoría, mermas o materias primas) para elaborar Longaniza, Chorizo, Chistorra, etc.</small>
                            </div>
                        </div>

                        <!-- Card de Formulario de Receta -->
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h5 class="card-title mb-0 fw-bold text-primary"><i class="bi bi-pencil-square me-2"></i>Configurar Receta</h5>
                            </div>
                            <div class="card-body">
                                <form id="formReceta">
                                    <div class="row g-3">
                                        <!-- Producto Terminado / Elaborado -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">1. Producto a Elaborar (Terminado):</label>
                                            <input type="text" class="form-control" id="input_producto_p" placeholder="Buscar producto elaborado (ej. Longaniza, Chorizo)..." autocomplete="off" required>
                                            <input type="hidden" id="codigo_p" value="">
                                            <div id="alertaProdSel" class="alert alert-secondary py-2 mt-2 d-none">
                                                <strong>Producto:</strong> <span id="lblProdNom"></span> (<span id="lblProdCod"></span>)
                                            </div>
                                        </div>

                                        <!-- Buscador de Insumos (Cualquier categoría) -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">2. Agregar Insumo (de cualquier categoría, mermas, carnes o insumos):</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="input_insumo" placeholder="Buscar insumo (ej. Grasa, Remolacha, Pulpa espaldilla)..." autocomplete="off" disabled>
                                            </div>
                                            <div class="form-text">Primero selecciona el producto a elaborar para habilitar la adición de insumos.</div>
                                        </div>
                                    </div>

                                    <!-- Tabla de Insumos de la Receta -->
                                    <div class="mt-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-check me-1 text-primary"></i>Insumos que componen este producto:</h6>
                                        </div>
                                        <div class="table-responsive bg-white border rounded">
                                            <table class="table table-hover table-insumos mb-0" id="tablaInsumosReceta">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Código</th>
                                                        <th>Insumo</th>
                                                        <th>Categoría</th>
                                                        <th class="text-center" style="width: 20%;">Porcentaje en la Fórmula (%)</th>
                                                        <th class="text-center" style="width: 10%;">Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyInsumos">
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-3">Selecciona un producto elaborado para cargar o crear su receta.</td>
                                                    </tr>
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr class="fw-bold">
                                                        <td colspan="3" class="text-end">Suma de Porcentajes:</td>
                                                        <td class="text-center fs-6" id="lblTotalPorcentaje">0.00%</td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="text-end mt-3">
                                        <button type="button" class="btn btn-secondary me-2" onclick="limpiarFormularioReceta()"><i class="bi bi-x-circle me-1"></i> Cancelar / Limpiar</button>
                                        <button type="submit" class="btn btn-primary" id="btnGuardarReceta" disabled><i class="bi bi-save me-1"></i> Guardar Receta de Producción</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Modal para ingresar % del insumo -->
                        <div class="modal fade" id="modalPorcentajeInsumo" tabindex="-1" aria-labelledby="modalPorcentajeLabel" aria-hidden="true">
                            <div class="modal-dialog modal-sm">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white py-2">
                                        <h6 class="modal-title" id="modalPorcentajeLabel">Porcentaje del Insumo</h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="mb-2"><strong id="modalInsNombre">-</strong></p>
                                        <label for="modalInsPct" class="form-label">Porcentaje en la receta (%):</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0.01" max="100" class="form-control fw-bold" id="modalInsPct" placeholder="Ej. 70.00">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="modal-footer py-1">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAceptarInsumo">Agregar</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Recetas Registradas -->
                        <div class="bg-white p-3 border rounded">
                            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-card-list me-2 text-primary"></i>Recetas de Producción Configuradas</h5>
                            <div class="table-responsive">
                                <table id="tablaRecetas" class="display table table-striped table-hover" style="width:100%">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Código</th>
                                            <th>Producto Elaborado</th>
                                            <th>Categoría</th>
                                            <th>Insumos que utiliza</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sqlRecetas = mysqli_query($link, "
                                            SELECT r.codigo_p, p.descripcion, c.desc_categoria
                                            FROM cc_recetas_produccion r
                                            INNER JOIN cc_productos p ON r.id_sucursal = p.id_sucursal AND r.codigo_p = p.codigo
                                            LEFT JOIN cc_categorias c ON p.id_sucursal = c.id_sucursal AND p.id_categoria = c.id_categoria
                                            WHERE r.id_sucursal = $id_sucursal
                                            GROUP BY r.codigo_p, p.descripcion, c.desc_categoria
                                            ORDER BY p.descripcion ASC
                                        ");

                                        if ($sqlRecetas) {
                                            while ($r = mysqli_fetch_assoc($sqlRecetas)) {
                                                $codP = $r['codigo_p'];
                                                $descP = $r['descripcion'];
                                                $catP = $r['desc_categoria'] ?: 'General';

                                                // Insumos
                                                $sqlIns = mysqli_query($link, "
                                                    SELECT r.codigo_d, r.porcentaje, p.descripcion
                                                    FROM cc_recetas_produccion r
                                                    INNER JOIN cc_productos p ON r.id_sucursal = p.id_sucursal AND r.codigo_d = p.codigo
                                                    WHERE r.id_sucursal = $id_sucursal AND r.codigo_p = '$codP'
                                                    ORDER BY r.porcentaje DESC
                                                ");
                                                $resumenInsumos = [];
                                                while ($ins = mysqli_fetch_assoc($sqlIns)) {
                                                    $resumenInsumos[] = '<span class="badge bg-light text-dark border me-1">' . htmlspecialchars($ins['descripcion']) . ': <strong>' . number_format($ins['porcentaje'], 1) . '%</strong></span>';
                                                }

                                                echo '<tr>';
                                                echo '<td><strong>' . htmlspecialchars($codP) . '</strong></td>';
                                                echo '<td>' . htmlspecialchars($descP) . '</td>';
                                                echo '<td><span class="badge bg-secondary">' . htmlspecialchars($catP) . '</span></td>';
                                                echo '<td>' . implode(' ', $resumenInsumos) . '</td>';
                                                echo '<td class="text-center">';
                                                echo '<button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="cargarRecetaParaEditar(\'' . $codP . '\')" title="Editar receta"><i class="bi bi-pencil"></i></button>';
                                                echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarReceta(\'' . $codP . '\', \'' . htmlspecialchars(addslashes($descP)) . '\')" title="Eliminar receta"><i class="bi bi-trash"></i></button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            let insumosLista = [];
            let insumoSeleccionadoTemporal = null;

            $(document).ready(function () {
                $('#tablaRecetas').DataTable({
                    language: {
                        "decimal": "",
                        "emptyTable": "No hay recetas de producción configuradas aún",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ Recetas",
                        "infoEmpty": "Mostrando 0 a 0 de 0 Recetas",
                        "infoFiltered": "(Filtrado de _MAX_ total)",
                        "lengthMenu": "Mostrar _MENU_ por página",
                        "loadingRecords": "Cargando...",
                        "processing": "Procesando...",
                        "search": "Buscar:",
                        "zeroRecords": "No se encontraron registros",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    }
                });

                // Autocompletado del Producto Terminado / Elaborado
                $('#input_producto_p').typeahead({
                    source: function (busqueda, process) {
                        $.ajax({
                            url: "../functions/consulta_productos.php",
                            data: { busqueda: busqueda, todos: 1 },
                            dataType: "json",
                            type: "POST",
                            success: function (data) {
                                process(data);
                            }
                        });
                    },
                    display: 'name',
                    val: 'id',
                    afterSelect: function (item) {
                        seleccionarProductoElaborado(item.id, item.name);
                    },
                    minLength: 2,
                    delay: 300
                });

                // Autocompletado de Insumos (busca en TODAS las categorías e insumos internos)
                $('#input_insumo').typeahead({
                    source: function (busqueda, process) {
                        $.ajax({
                            url: "../functions/consulta_derivados.php",
                            data: { accion: 'buscar_insumos', busqueda: busqueda },
                            dataType: "json",
                            type: "POST",
                            success: function (data) {
                                process(data);
                            }
                        });
                    },
                    display: function (item) {
                        return item.name + ' [' + item.categoria + ']';
                    },
                    val: 'id',
                    afterSelect: function (item) {
                        abrirModalPorcentaje(item);
                    },
                    minLength: 2,
                    delay: 300
                });

                // Modal de Porcentaje
                $('#btnAceptarInsumo').on('click', function () {
                    var pct = parseFloat($('#modalInsPct').val());
                    if (isNaN(pct) || pct <= 0 || pct > 100) {
                        alert("Por favor ingresa un porcentaje válido entre 0.01% y 100%");
                        return;
                    }

                    if (!insumoSeleccionadoTemporal) return;

                    // Verificar si ya existe en la lista
                    var existe = insumosLista.find(i => i.codigo_d === insumoSeleccionadoTemporal.id);
                    if (existe) {
                        existe.porcentaje = pct;
                    } else {
                        insumosLista.push({
                            codigo_d: insumoSeleccionadoTemporal.id,
                            descripcion: insumoSeleccionadoTemporal.name,
                            categoria: insumoSeleccionadoTemporal.categoria || '',
                            porcentaje: pct
                        });
                    }

                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalPorcentajeInsumo'));
                    if (modal) modal.hide();

                    $('#input_insumo').val('');
                    renderizarTablaInsumos();
                });

                // Guardar receta
                $('#formReceta').on('submit', function (e) {
                    e.preventDefault();

                    var codigo_p = $('#codigo_p').val();
                    if (!codigo_p) {
                        alert("Selecciona un producto a elaborar.");
                        return;
                    }

                    if (insumosLista.length === 0) {
                        alert("Debes agregar al menos un insumo.");
                        return;
                    }

                    var sumaPct = insumosLista.reduce((acc, cur) => acc + cur.porcentaje, 0);
                    if (Math.abs(sumaPct - 100) > 0.5) {
                        if (!confirm("Aviso: La suma de porcentajes es " + sumaPct.toFixed(2) + "% (no es 100%). ¿Deseas guardarla de todas formas?")) {
                            return;
                        }
                    }

                    $('#btnGuardarReceta').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Guardando...');

                    $.ajax({
                        url: "../functions/edita_receta_produccion.php",
                        type: "POST",
                        dataType: "json",
                        data: {
                            movimiento: 1,
                            codigo_p: codigo_p,
                            insumos: JSON.stringify(insumosLista)
                        },
                        success: function (res) {
                            if (res.exito === 1) {
                                alert(res.mensaje);
                                location.reload();
                            } else {
                                alert("Error: " + res.mensaje);
                                $('#btnGuardarReceta').prop('disabled', false).html('<i class="bi bi-save me-1"></i> Guardar Receta de Producción');
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr);
                            alert("Error al comunicarse con el servidor.");
                            $('#btnGuardarReceta').prop('disabled', false).html('<i class="bi bi-save me-1"></i> Guardar Receta de Producción');
                        }
                    });
                });

                // Si viene un código para editar en URL
                <?php if ($codigo_editar !== ''): ?>
                    cargarRecetaParaEditar('<?php echo htmlspecialchars($codigo_editar); ?>');
                <?php endif; ?>
            });

            function seleccionarProductoElaborado(codigo, descripcion) {
                $('#codigo_p').val(codigo);
                $('#lblProdNom').text(descripcion);
                $('#lblProdCod').text(codigo);
                $('#alertaProdSel').removeClass('d-none');
                $('#input_producto_p').val(descripcion);
                $('#input_insumo').prop('disabled', false).focus();
                $('#btnGuardarReceta').prop('disabled', false);

                // Cargar receta existente si ya tiene
                $.ajax({
                    url: "../functions/consulta_derivados.php",
                    type: "POST",
                    dataType: "json",
                    data: { accion: 'obtener_receta', codigo_p: codigo },
                    success: function (res) {
                        if (res.exito === 1 && res.insumos && res.insumos.length > 0) {
                            insumosLista = res.insumos.map(i => ({
                                codigo_d: i.codigo_d,
                                descripcion: i.descripcion,
                                categoria: i.categoria || '',
                                porcentaje: parseFloat(i.porcentaje)
                            }));
                        } else {
                            insumosLista = [];
                        }
                        renderizarTablaInsumos();
                    },
                    error: function () {
                        insumosLista = [];
                        renderizarTablaInsumos();
                    }
                });
            }

            function abrirModalPorcentaje(item) {
                // Verificar que no sea el mismo producto elaborado
                if (item.id === $('#codigo_p').val()) {
                    alert("No puedes agregar el mismo producto elaborado como su propio insumo.");
                    $('#input_insumo').val('');
                    return;
                }

                insumoSeleccionadoTemporal = item;
                $('#modalInsNombre').text(item.name + ' (' + (item.categoria || '') + ')');
                $('#modalInsPct').val('');
                var modal = new bootstrap.Modal(document.getElementById('modalPorcentajeInsumo'));
                modal.show();
                setTimeout(() => { $('#modalInsPct').focus(); }, 400);
            }

            function renderizarTablaInsumos() {
                var tbody = $('#tbodyInsumos');
                tbody.empty();

                if (insumosLista.length === 0) {
                    tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">No hay insumos agregados aún. Usa el buscador de insumos arriba.</td></tr>');
                    $('#lblTotalPorcentaje').text('0.00%').removeClass('text-success text-danger');
                    return;
                }

                var sumaPct = 0;
                insumosLista.forEach((ins, idx) => {
                    sumaPct += ins.porcentaje;
                    var tr = $('<tr>');
                    tr.append('<td><strong>' + ins.codigo_d + '</strong></td>');
                    tr.append('<td>' + ins.descripcion + '</td>');
                    tr.append('<td><span class="badge bg-secondary">' + (ins.categoria || 'Insumo') + '</span></td>');
                    tr.append('<td class="text-center fw-bold">' + ins.porcentaje.toFixed(2) + '%</td>');
                    tr.append('<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarInsumoLista(' + idx + ')"><i class="bi bi-trash"></i></button></td>');
                    tbody.append(tr);
                });

                var pctColor = (Math.abs(sumaPct - 100) < 0.1) ? 'text-success' : 'text-danger';
                $('#lblTotalPorcentaje').text(sumaPct.toFixed(2) + '%').removeClass('text-success text-danger').addClass(pctColor);
            }

            function eliminarInsumoLista(index) {
                insumosLista.splice(index, 1);
                renderizarTablaInsumos();
            }

            function limpiarFormularioReceta() {
                $('#formReceta')[0].reset();
                $('#codigo_p').val('');
                $('#alertaProdSel').addClass('d-none');
                $('#input_insumo').prop('disabled', true);
                $('#btnGuardarReceta').prop('disabled', true);
                insumosLista = [];
                renderizarTablaInsumos();
            }

            function cargarRecetaParaEditar(codigo_p) {
                $.ajax({
                    url: "../functions/consulta_derivados.php",
                    type: "POST",
                    dataType: "json",
                    data: { accion: 'obtener_receta', codigo_p: codigo_p },
                    success: function (res) {
                        if (res.exito === 1) {
                            seleccionarProductoElaborado(res.producto.codigo, res.producto.descripcion);
                            $('html, body').animate({ scrollTop: 0 }, 'fast');
                        } else {
                            alert(res.mensaje);
                        }
                    }
                });
            }

            function eliminarReceta(codigo_p, descripcion) {
                if (!confirm("¿Estás seguro de eliminar la receta de producción de " + descripcion + "?\n\nEsto no eliminará el producto, solo su fórmula de producción.")) {
                    return;
                }

                $.ajax({
                    url: "../functions/edita_receta_produccion.php",
                    type: "POST",
                    dataType: "json",
                    data: { movimiento: 2, codigo_p: codigo_p },
                    success: function (res) {
                        if (res.exito === 1) {
                            alert(res.mensaje);
                            location.reload();
                        } else {
                            alert("Error: " + res.mensaje);
                        }
                    }
                });
            }
        </script>
    </body>
</html>
