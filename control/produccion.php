<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");

$id_sucursal = (int) $_SESSION["id_sucursal"];
$id_usuario = (int) $_SESSION["id"];

if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
}
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicerías &quot;EL Cinco&quot;">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Producción de Derivados</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/typeahead.js"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>

        <link href="../css/bootstrap.css" rel="stylesheet">
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link href="../css/gijgo.min.css" rel="stylesheet" type="text/css" />
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
            .badge-procesado {
                background-color: #198754;
                color: #fff;
            }
            .badge-cancelado {
                background-color: #dc3545;
                color: #fff;
            }
            .badge-cerrado {
                background-color: #6c757d;
                color: #fff;
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
                                <h2 class="mb-0 text-dark"><i class="bi bi-gear-wide-connected text-primary me-2"></i>Producción de Derivados</h2>
                                <small class="text-muted">Transforma insumos (ej. Carne de res) en productos elaborados (ej. Longaniza) por lotes</small>
                            </div>
                            <div>
                                <button type="button" class="btn btn-success btn-lg shadow-sm" id="btnNuevoLote">
                                    <i class="bi bi-plus-circle me-1"></i> Nuevo Lote de Producción
                                </button>
                            </div>
                        </div>

                        <!-- Filtro de Fecha -->
                        <form class="row g-2 align-items-center my-3 p-3 bg-white border rounded" action="#" method="post">
                            <div class="col-auto">
                                <label for="datepicker" class="col-form-label fw-bold">Fecha de producción:</label>
                            </div>
                            <div class="col-auto">
                                <input name="fecha" id="datepicker" width="220" autocomplete="off" readonly="" value="<?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>"/>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Filtrar</button>
                            </div>
                        </form>

                        <!-- Tabla de Lotes Registrados -->
                        <div class="table-responsive bg-white p-3 border rounded">
                            <table id="tabla_producciones" class="display table table-striped table-hover" style="width:100%" >
                                <thead class="table-dark">
                                    <tr>
                                        <th>Lote #</th>
                                        <th>Hora</th>
                                        <th>Producto Elaborado</th>
                                        <th>Kilos Obtenidos</th>
                                        <th>Comentarios</th>
                                        <th>Estatus</th>
                                        <th>Usuario</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $fechaEsc = mysqli_real_escape_string($link, $fecha);
                                    $sqlProd = "
                                        SELECT p.*, u.username
                                        FROM cc_produccion p
                                        LEFT JOIN cc_users u ON p.id_usuario = u.id_usuario
                                        WHERE p.id_sucursal = $id_sucursal AND p.fecha_ingreso = '$fechaEsc'
                                        ORDER BY p.id_produccion DESC
                                    ";
                                    $resProd = mysqli_query($link, $sqlProd);
                                    $totalKilos = 0;

                                    if ($resProd) {
                                        while ($row = mysqli_fetch_assoc($resProd)) {
                                            $id_prod = (int) $row['id_produccion'];
                                            $estatus = (int) $row['estatus'];
                                            $id_cierre = (int) $row['id_cierre'];
                                            $cantidad = (float) $row['cantidad'];

                                            if ($estatus === 1) {
                                                $totalKilos += $cantidad;
                                                $badgeStatus = '<span class="badge bg-success">Activo</span>';
                                            } elseif ($estatus === 2) {
                                                $badgeStatus = '<span class="badge bg-danger">Cancelado</span>';
                                            } else {
                                                $badgeStatus = '<span class="badge bg-secondary">Cerrado</span>';
                                            }

                                            echo '<tr>';
                                            echo '<td><span class="badge bg-light text-dark border">#' . $id_prod . '</span></td>';
                                            echo '<td>' . htmlspecialchars($row['hora_ingreso'], ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td><strong>' . htmlspecialchars($row['descripcion'], ENT_QUOTES, 'UTF-8') . '</strong> <small class="text-muted">(' . htmlspecialchars($row['codigo'], ENT_QUOTES, 'UTF-8') . ')</small></td>';
                                            echo '<td><strong class="text-primary">' . number_format($cantidad, 3) . ' kg</strong></td>';
                                            echo '<td>' . htmlspecialchars($row['comentario'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td>' . $badgeStatus . '</td>';
                                            echo '<td>' . htmlspecialchars($row['username'] ?: 'N/D', ENT_QUOTES, 'UTF-8') . '</td>';
                                            echo '<td class="text-center">';
                                            echo '<button type="button" class="btn btn-sm btn-outline-info me-1" onclick="verDetalleLote(' . $id_prod . ')" title="Ver insumos consumidos"><i class="bi bi-eye"></i> Insumos</button>';
                                            
                                            // Solo permitir cancelar si está activo y no forma parte de un cierre cerrado
                                            if ($estatus === 1 && $id_cierre === 0) {
                                                echo '<button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelarLote(' . $id_prod . ', \'' . htmlspecialchars(addslashes($row['descripcion']), ENT_QUOTES, 'UTF-8') . '\', ' . $cantidad . ')" title="Cancelar lote y revertir stock"><i class="bi bi-trash"></i></button>';
                                            } else {
                                                echo '<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="No editable (cerrado o cancelado)"><i class="bi bi-lock"></i></button>';
                                            }
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end">Total producido activo:</td>
                                        <td><?php echo number_format($totalKilos, 3); ?> kg</td>
                                        <td colspan="4"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- MODAL: NUEVO LOTE DE PRODUCCIÓN -->
        <div class="modal fade" id="modalNuevoLote" tabindex="-1" aria-labelledby="modalNuevoLoteLabel" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalNuevoLoteLabel"><i class="bi bi-plus-circle me-2"></i>Registrar Nuevo Lote de Producción</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formProduccion" novalidate>
                        <input type="hidden" name="movimiento" value="1">
                        <input type="hidden" id="codigo_p" name="codigo_p" value="">
                        
                        <div class="modal-body">
                            <!-- Paso 1: Selección de producto -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-7">
                                    <label for="producto_input" class="form-label fw-bold">1. Producto elaborado (Derivado):</label>
                                    <input type="text" class="form-control" id="producto_input" placeholder="Escribe para buscar (ej. Longaniza, Molida Mixta)..." autocomplete="off" required>
                                    <div class="form-text" id="helpProducto">Solo se muestran productos con receta en <a href="../admin/recetas_produccion.php" target="_blank">Admin -> Recetas de producción</a>.</div>
                                </div>
                                <div class="col-md-5">
                                    <label for="cantidad_producida" class="form-label fw-bold">2. Kilos obtenidos (Lote):</label>
                                    <div class="input-group">
                                        <input type="number" step="0.001" min="0.001" class="form-control fw-bold fs-5 text-primary" id="cantidad_producida" name="cantidad" placeholder="0.000" required disabled>
                                        <span class="input-group-text fw-bold">kg</span>
                                    </div>
                                    <div class="form-text">Cantidad total producida para vitrina.</div>
                                </div>
                            </div>

                            <!-- Alerta de producto seleccionado -->
                            <div id="alertaProductoSeleccionado" class="alert alert-info py-2 d-none">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-check2-circle me-1"></i> <strong id="lblProdNombre">-</strong> 
                                        <span class="badge bg-secondary ms-2" id="lblProdCodigo">-</span>
                                    </div>
                                    <div>Stock actual en vitrina: <strong id="lblProdStock">0.000 kg</strong></div>
                                </div>
                            </div>

                            <!-- Paso 2: Desglose de Insumos Automático -->
                            <div id="contenedorInsumos" class="d-none mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam me-1 text-primary"></i>Insumos a descontar de almacén:</h6>
                                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Calculado según la fórmula. Puedes ajustar los kg reales si hubo variación.</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-insumos table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Insumo</th>
                                                <th class="text-center" style="width: 15%;">% Fórmula</th>
                                                <th class="text-center" style="width: 20%;">Stock en Almacén</th>
                                                <th class="text-center" style="width: 25%;">Kilos a Descontar</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyInsumos">
                                            <!-- Se rellena vía JavaScript -->
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr class="fw-bold">
                                                <td class="text-end">Total insumos:</td>
                                                <td class="text-center" id="lblTotalPorcentaje">0%</td>
                                                <td></td>
                                                <td class="text-center text-primary" id="lblTotalKgInsumos">0.000 kg</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Comentarios -->
                            <div class="mt-3">
                                <label for="comentario" class="form-label">Comentarios u observaciones (opcional):</label>
                                <input type="text" class="form-control" id="comentario" name="comentario" placeholder="Ej. Lote de la mañana, receta con tripa delgada, etc.">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success" id="btnGuardarProduccion" disabled>
                                <i class="bi bi-check-lg me-1"></i> Registrar y Afectar Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: VER DETALLE DE LOTE -->
        <div class="modal fade" id="modalDetalleLote" tabindex="-1" aria-labelledby="modalDetalleLoteLabel" aria-hidden="true">
            <div class="modal-dialog modal-md">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="modalDetalleLoteLabel"><i class="bi bi-card-checklist me-2"></i>Detalle de Insumos del Lote</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3 p-2 bg-light border rounded">
                            <div><strong>Lote:</strong> <span id="detLoteId"></span></div>
                            <div><strong>Producto:</strong> <span id="detProdDesc"></span></div>
                            <div><strong>Kilos obtenidos:</strong> <span id="detProdCant" class="text-primary fw-bold"></span></div>
                            <div><strong>Fecha y hora:</strong> <span id="detFechaHora"></span></div>
                            <div><strong>Registrado por:</strong> <span id="detUsuario"></span></div>
                        </div>
                        <h6 class="fw-bold">Insumos descontados en este lote:</h6>
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Insumo</th>
                                    <th class="text-center">% Receta</th>
                                    <th class="text-center">Kg Descontados</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetalleVer">
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            let recetaActual = null;

            $(document).ready(function () {
                // DatePicker
                $('#datepicker').datepicker({
                    uiLibrary: 'bootstrap5',
                    format: 'yyyy-mm-dd'
                });

                // DataTable
                $('#tabla_producciones').DataTable({
                    language: {
                        "decimal": "",
                        "emptyTable": "No hay lotes registrados para esta fecha",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ Lotes",
                        "infoEmpty": "Mostrando 0 a 0 de 0 Lotes",
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
                    },
                    order: [[0, "desc"]]
                });

                // Abrir modal de nuevo lote
                $('#btnNuevoLote').on('click', function () {
                    limpiarFormulario();
                    var modal = new bootstrap.Modal(document.getElementById('modalNuevoLote'));
                    modal.show();
                    setTimeout(() => { $('#producto_input').focus(); }, 400);
                });

                // Autocompletado de productos que tienen receta
                $('#producto_input').typeahead({
                    source: function (busqueda, process) {
                        $.ajax({
                            url: "../functions/consulta_derivados.php",
                            data: { accion: 'buscar', busqueda: busqueda },
                            dataType: "json",
                            type: "POST",
                            success: function (data) {
                                process(data);
                            },
                            error: function (xhr) {
                                console.error("Error al buscar derivados:", xhr);
                            }
                        });
                    },
                    display: 'name',
                    val: 'id',
                    afterSelect: function (item) {
                        cargarRecetaProducto(item.id);
                    },
                    minLength: 1,
                    delay: 300
                });

                // Recalcular insumos al teclear cantidad producida
                $('#cantidad_producida').on('input', function () {
                    recalcularInsumos();
                });

                // Envío del formulario de producción
                $('#formProduccion').on('submit', function (e) {
                    e.preventDefault();

                    var codigo_p = $('#codigo_p').val();
                    var cantidad = parseFloat($('#cantidad_producida').val());

                    if (!codigo_p || isNaN(cantidad) || cantidad <= 0) {
                        alert("Por favor selecciona un producto e ingresa una cantidad válida mayor a cero.");
                        return;
                    }

                    // Recolectar insumos
                    var insumosData = [];
                    var errorKg = false;
                    $('.input-insumo-kg').each(function () {
                        var codD = $(this).data('codigo');
                        var pct = parseFloat($(this).data('porcentaje')) || 0;
                        var kg = parseFloat($(this).val());
                        if (isNaN(kg) || kg < 0) {
                            errorKg = true;
                        }
                        insumosData.push({
                            codigo_d: codD,
                            porcentaje: pct,
                            cantidad_utilizada: kg
                        });
                    });

                    if (errorKg || insumosData.length === 0) {
                        alert("Verifica los kilos de los insumos.");
                        return;
                    }

                    if (!confirm("¿Confirmas procesar el lote de " + cantidad + " kg de " + $('#lblProdNombre').text() + "?\n\nEsto aumentará el inventario del producto y descontará los insumos correspondientes.")) {
                        return;
                    }

                    $('#btnGuardarProduccion').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Procesando...');

                    $.ajax({
                        url: "../functions/edita_produccion.php",
                        type: "POST",
                        dataType: "json",
                        data: {
                            movimiento: 1,
                            codigo_p: codigo_p,
                            cantidad: cantidad,
                            comentario: $('#comentario').val(),
                            insumos: JSON.stringify(insumosData)
                        },
                        success: function (res) {
                            if (res.exito === 1) {
                                alert(res.mensaje);
                                location.reload();
                            } else {
                                alert("Error: " + res.mensaje);
                                $('#btnGuardarProduccion').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Registrar y Afectar Stock');
                            }
                        },
                        error: function (xhr) {
                            console.error(xhr);
                            alert("Ocurrió un error en la petición del servidor.");
                            $('#btnGuardarProduccion').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Registrar y Afectar Stock');
                        }
                    });
                });
            });

            function limpiarFormulario() {
                $('#formProduccion')[0].reset();
                $('#codigo_p').val('');
                $('#alertaProductoSeleccionado').addClass('d-none');
                $('#contenedorInsumos').addClass('d-none');
                $('#cantidad_producida').prop('disabled', true);
                $('#btnGuardarProduccion').prop('disabled', true);
                recetaActual = null;
            }

            function cargarRecetaProducto(codigo_p) {
                $.ajax({
                    url: "../functions/consulta_derivados.php",
                    type: "POST",
                    dataType: "json",
                    data: { accion: 'obtener_receta', codigo_p: codigo_p },
                    success: function (res) {
                        if (res.exito === 1) {
                            recetaActual = res;
                            $('#codigo_p').val(res.producto.codigo);
                            $('#lblProdNombre').text(res.producto.descripcion);
                            $('#lblProdCodigo').text("Código: " + res.producto.codigo);
                            $('#lblProdStock').text(parseFloat(res.producto.almacen).toFixed(3) + " kg");

                            $('#alertaProductoSeleccionado').removeClass('d-none');
                            $('#cantidad_producida').prop('disabled', false).val('').focus();
                            $('#btnGuardarProduccion').prop('disabled', false);

                            renderizarTablaInsumos();
                        } else {
                            alert(res.mensaje);
                            limpiarFormulario();
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr);
                        alert("Error al obtener la receta del producto.");
                        limpiarFormulario();
                    }
                });
            }

            function renderizarTablaInsumos() {
                if (!recetaActual || !recetaActual.insumos) return;

                var html = '';
                var sumaPct = 0;

                recetaActual.insumos.forEach(function (ins, idx) {
                    sumaPct += ins.porcentaje;
                    var stockClass = (ins.almacen <= 0) ? 'text-danger fw-bold' : 'text-success';

                    html += '<tr>';
                    html += '<td><strong>' + ins.descripcion + '</strong><br><small class="text-muted">Cód: ' + ins.codigo_d + '</small></td>';
                    html += '<td class="text-center fw-bold">' + ins.porcentaje.toFixed(2) + '%</td>';
                    html += '<td class="text-center ' + stockClass + '">' + ins.almacen.toFixed(3) + ' kg</td>';
                    html += '<td>';
                    html += '<div class="input-group input-group-sm">';
                    html += '<input type="number" step="0.001" min="0" class="form-control text-end fw-bold input-insumo-kg" ';
                    html += ' id="insumo_kg_' + idx + '" ';
                    html += ' data-codigo="' + ins.codigo_d + '" ';
                    html += ' data-porcentaje="' + ins.porcentaje + '" ';
                    html += ' oninput="sumarTotalKgInsumos()" value="0.000">';
                    html += '<span class="input-group-text">kg</span>';
                    html += '</div>';
                    html += '</td>';
                    html += '</tr>';
                });

                $('#tbodyInsumos').html(html);
                $('#lblTotalPorcentaje').text(sumaPct.toFixed(2) + '%');
                $('#contenedorInsumos').removeClass('d-none');
                recalcularInsumos();
            }

            function recalcularInsumos() {
                if (!recetaActual || !recetaActual.insumos) return;

                var totalKilosProd = parseFloat($('#cantidad_producida').val()) || 0;

                recetaActual.insumos.forEach(function (ins, idx) {
                    // Cálculo proporcional según porcentaje
                    var kgCalculados = (totalKilosProd * (ins.porcentaje / 100)).toFixed(3);
                    $('#insumo_kg_' + idx).val(kgCalculados);
                });

                sumarTotalKgInsumos();
            }

            function sumarTotalKgInsumos() {
                var total = 0;
                $('.input-insumo-kg').each(function () {
                    var val = parseFloat($(this).val()) || 0;
                    total += val;
                });
                $('#lblTotalKgInsumos').text(total.toFixed(3) + " kg");
            }

            function verDetalleLote(id_produccion) {
                $.ajax({
                    url: "../functions/edita_produccion.php",
                    type: "POST",
                    dataType: "json",
                    data: { movimiento: 3, id_produccion: id_produccion },
                    success: function (res) {
                        if (res.exito === 1) {
                            $('#detLoteId').text("#" + res.cabecera.id_produccion);
                            $('#detProdDesc').text(res.cabecera.descripcion + " (" + res.cabecera.codigo + ")");
                            $('#detProdCant').text(parseFloat(res.cabecera.cantidad).toFixed(3) + " kg");
                            $('#detFechaHora').text(res.cabecera.fecha_ingreso + " " + res.cabecera.hora_ingreso);
                            $('#detUsuario').text(res.cabecera.username || 'N/D');

                            var html = '';
                            res.detalles.forEach(function (d) {
                                html += '<tr>';
                                html += '<td>' + d.desc_insumo + ' <small class="text-muted">(' + d.codigo_insumo + ')</small></td>';
                                html += '<td class="text-center">' + parseFloat(d.porcentaje_teorico).toFixed(2) + '%</td>';
                                html += '<td class="text-center fw-bold text-primary">' + parseFloat(d.cantidad_utilizada).toFixed(3) + ' kg</td>';
                                html += '</tr>';
                            });
                            $('#tbodyDetalleVer').html(html);

                            var modal = new bootstrap.Modal(document.getElementById('modalDetalleLote'));
                            modal.show();
                        } else {
                            alert(res.mensaje);
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr);
                        alert("Error al cargar detalle del lote.");
                    }
                });
            }

            function cancelarLote(id_produccion, descripcion, cantidad) {
                if (!confirm("¿Estás seguro de CANCELAR el lote #" + id_produccion + " (" + cantidad + " kg de " + descripcion + ")?\n\nEsta acción revertirá los movimientos en el almacén (restará la producción y devolverá los insumos).")) {
                    return;
                }

                $.ajax({
                    url: "../functions/edita_produccion.php",
                    type: "POST",
                    dataType: "json",
                    data: { movimiento: 2, id_produccion: id_produccion },
                    success: function (res) {
                        if (res.exito === 1) {
                            alert(res.mensaje);
                            location.reload();
                        } else {
                            alert("Error: " + res.mensaje);
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr);
                        alert("Error al cancelar el lote.");
                    }
                });
            }
        </script>
    </body>
</html>
