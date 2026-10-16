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

if (!function_exists('storefront_escape')) {
    function storefront_escape($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Define variables and initialize with empty values
$desc_sucursal = "";
$direccion = "";
$latitud = "";
$longitud = "";
$horario = "";
$whatsapp = "";
$activo = 0;
$fecha_ingreso = 0;
$hora_ingreso = 0;
$id_usuario = 0;
$body = "";
$title = "";

if (isset($_POST['agregar'])) {
    $desc_sucursal = mb_strtoupper(trim($_POST["desc_sucursal"]));
    $direccion = trim($_POST["direccion"]);
    $latitud = trim($_POST["latitud"]);
    $longitud = trim($_POST["longitud"]);
    $horario = trim($_POST["horario"]);
    $whatsapp = trim($_POST["whatsapp"]);
    $wa_instancia = trim($_POST["wa_instancia"] ?? "");
    $es_default = isset($_POST['es_default']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $fecha_ingreso = date('Y-m-d');
    $hora_ingreso = date('H:i:s');
    $id_usuario = $_SESSION["id"];

    if ($es_default === 1) {
        mysqli_query($link, "UPDATE cc_sucursales SET es_default = 0");
    }

    $sql = "INSERT INTO cc_sucursales (desc_sucursal, direccion, latitud, longitud, horario, whatsapp, wa_instancia, es_default, activo, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssssiiiss", $desc_sucursal, $direccion, $latitud, $longitud, $horario, $whatsapp, $wa_instancia, $es_default, $activo, $id_usuario, $fecha_ingreso, $hora_ingreso);
        if (mysqli_stmt_execute($stmt)) {
            $title = 'Agregado';
            $body = 'Sucursal ' . $desc_sucursal . ' agregada correctamente.';
        } else {
            $title = 'No Agregado';
            $body = 'Sucursal ' . $desc_sucursal . ' no se puede agregar: ' . mysqli_error($link);
        }
    }
}

if (isset($_POST['editar'])) {
    $id_sucursal = trim($_POST["id_sucursal_e"]);
    $desc_sucursal = mb_strtoupper(trim($_POST["desc_sucursal_e"]));
    $direccion = mysqli_real_escape_string($link, trim($_POST["direccion_e"]));
    $latitud = mysqli_real_escape_string($link, trim($_POST["latitud_e"]));
    $longitud = mysqli_real_escape_string($link, trim($_POST["longitud_e"]));
    $horario = mysqli_real_escape_string($link, trim($_POST["horario_e"]));
    $whatsapp = mysqli_real_escape_string($link, trim($_POST["whatsapp_e"]));
    $wa_instancia = mysqli_real_escape_string($link, trim($_POST["wa_instancia_e"] ?? ''));
    $es_default = isset($_POST['es_default_e']) ? 1 : 0;
    $activo = isset($_POST['activo_e']) ? 1 : 0;
    $id_usuario_act = $_SESSION["id"];
    $fecha_act = date('Y-m-d');
    $hora_act = date('H:i:s');

    if ($es_default === 1) {
        mysqli_query($link, "UPDATE cc_sucursales SET es_default = 0");
    }

    $update1 = mysqli_query($link, "UPDATE cc_sucursales SET "
                    . "desc_sucursal='$desc_sucursal', direccion='$direccion', latitud='$latitud', longitud='$longitud', "
                    . "horario='$horario', whatsapp='$whatsapp', wa_instancia='$wa_instancia', es_default='$es_default', activo='$activo', "
                    . "fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                    . "WHERE id_sucursal='$id_sucursal'")
            or die(mysqli_error($link));
    if ($update1) {
        $title = 'Actualizado';
        $body = 'Sucursal ' . $desc_sucursal . ' actualizada correctamente.';
    } else {
        $title = 'No actualizado';
        $body = 'Sucursal ' . $desc_sucursal . ' no actualizada.';
    }
}

if (isset($_GET['accion']) && $_GET['accion'] == 'delete' && $title == '') {
    $id_sucursal = mysqli_real_escape_string($link, (strip_tags($_GET["id_sucursal"], ENT_QUOTES)));
    $cek1 = mysqli_query($link, "SELECT * FROM cc_categorias WHERE id_sucursal = '$id_sucursal'");
    $cek2 = mysqli_query($link, "SELECT * FROM cc_sucursales WHERE id_sucursal = '$id_sucursal'");
    $sqlsucursales = mysqli_fetch_assoc($cek2);
    if ($cek1 && mysqli_num_rows($cek1) > 0) {
        $title = 'No Eliminado';
        $body = 'Sucursal ' . $sqlsucursales['desc_sucursal'] . ' no eliminada. Ya está asociado a categorías';
    } else {
        $delete = mysqli_query($link, "DELETE FROM cc_sucursales WHERE id_sucursal = '$id_sucursal'");
        if ($delete) {
            $title = 'Eliminado';
            $body = 'Sucursal ' . $sqlsucursales['desc_sucursal'] . ' eliminada correctamente.';
        } else {
            $title = 'No Eliminado';
            $body = 'Sucursal ' . $sqlsucursales['desc_sucursal'] . ' no eliminada.';
        }
    }
}

$googleApiKey = defined('GOOGLE_MAPS_API_KEY') ? trim(GOOGLE_MAPS_API_KEY) : '';
?>
<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicerías "EL Cinco"">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Mantenimiento de Sucursales</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>

        <style>
            @import "../css/bootstrap.css";
            .interactive-map {
                height: 280px;
                border-radius: 8px;
            }
            .search-results-list {
                z-index: 1070 !important;
                max-height: 220px;
                overflow-y: auto;
            }
        </style>

        <!-- Custom styles for this template -->
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

        <!-- Google Maps JavaScript API -->
        <?php if (!empty($googleApiKey)): ?>
            <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo storefront_escape($googleApiKey); ?>&callback=initGoogleMaps" async defer></script>
        <?php endif; ?>
    </head>
    <body>
        <main>
            <div class="container">
                <?php require_once "../components/nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded shadow-sm mt-3">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Sucursales</h1>
                        </div>
                        <br>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <label for="descripcion" class="form-label fw-bold">Sucursal *:</label>
                                    <input type="text" class="form-control" id="descripcion" name="desc_sucursal" placeholder="Descripción de la sucursal" required>
                                    <div class="invalid-feedback">Favor de ingresar descripción.</div>
                                </div>
                                <div class="col-md-8 position-relative">
                                    <label for="direccion" class="form-label fw-bold">Dirección:</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Escribe la dirección para buscar..." autocomplete="off">
                                    <div id="searchResultsAdd" class="list-group position-absolute w-100 shadow search-results-list d-none"></div>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <label for="latitud" class="form-label fw-bold">Latitud:</label>
                                    <input type="text" class="form-control" id="latitud" name="latitud" placeholder="Ej: 19.6021539">
                                </div>
                                <div class="col-md-3">
                                    <label for="longitud" class="form-label fw-bold">Longitud:</label>
                                    <input type="text" class="form-control" id="longitud" name="longitud" placeholder="Ej: -99.0451077">
                                </div>
                                <div class="col-md-3">
                                    <label for="horario" class="form-label fw-bold">Horario de Atención:</label>
                                    <input type="text" class="form-control" id="horario" name="horario" placeholder="Ej: 7:30 AM - 3:30 PM">
                                </div>
                                <div class="col-md-3">
                                    <label for="whatsapp" class="form-label fw-bold">WhatsApp:</label>
                                    <input type="text" class="form-control" id="whatsapp" name="whatsapp" placeholder="Ej: 5512345678">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="wa_instancia" class="form-label fw-bold text-dark"><i class="bi bi-robot text-success me-1"></i> Instancia Evolution API (WhatsApp):</label>
                                    <input type="text" class="form-control" id="wa_instancia" name="wa_instancia" placeholder="Ej: pruebas, test_abstrings, prod_abstrings">
                                    <div class="form-text small text-muted">Nombre exacto de la instancia en Evolution API vinculada a esta sucursal.</div>
                                </div>
                                <div class="col-md-6 d-flex align-items-center pt-3 flex-wrap gap-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" name="activo" type="checkbox" value="1" id="activo" checked>
                                        <label class="form-check-label fw-bold" for="activo">Sucursal Activa</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" name="es_default" type="checkbox" value="1" id="es_default">
                                        <label class="form-check-label fw-bold text-primary" for="es_default"><i class="bi bi-star-fill text-warning me-1"></i> Sucursal Predeterminada (Default)</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Mapa Interactivo Google Maps -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Mapa Google Maps (Arrastra el pin o haz clic para ajustar las coordenadas):</label>
                                    <div id="mapAddContainer" class="interactive-map border shadow-sm"></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" name="agregar"><i class="bi bi-plus-circle me-1"></i> Agregar Sucursal</button>
                                </div>
                            </div>
                        </form>
                        <br>
                        <div class="table-responsive">
                            <table id="sucursales" class="display" style="width:100%" >
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Dirección</th>
                                        <th>Horario</th>
                                        <th>WhatsApp / Instancia</th>
                                        <th>Mapa</th>
                                        <th>Activo</th>
                                        <th>Usuario</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $sqlsucursales = mysqli_query($link, "SELECT * FROM cc_sucursales");
                                $renglon = 0;
                                while ($rowc = mysqli_fetch_assoc($sqlsucursales)) {
                                    $renglon = $renglon + 1;
                                    $sqluser = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id =" . (int)$rowc['id_usuario']));
                                    $username = $sqluser ? storefront_escape($sqluser['username']) : 'Sistema';
                                    $lat = storefront_escape($rowc['latitud'] ?? '');
                                    $lng = storefront_escape($rowc['longitud'] ?? '');
                                    $dir = storefront_escape($rowc['direccion'] ?? '');
                                    $desc = storefront_escape($rowc['desc_sucursal'] ?? '');
                                    $hor = storefront_escape($rowc['horario'] ?? '');
                                    $wa = storefront_escape($rowc['whatsapp'] ?? '');
                                    $instancia = storefront_escape($rowc['wa_instancia'] ?? '');
                                    $isDefault = ((int)($rowc['es_default'] ?? 0) === 1);
                                    
                                    $mapBtn = '-';
                                    if (!empty($lat) && !empty($lng)) {
                                        $mapBtn = '<a href="https://www.google.com/maps?q=' . $lat . ',' . $lng . '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger py-0 px-2" title="Abrir en Google Maps"><i class="bi bi-geo-alt-fill"></i> Ver</a>';
                                    }

                                    $waLink = '<span class="text-muted">Sin teléfono</span>';
                                    if (!empty($wa)) {
                                        $cleanWa = preg_replace('/[^0-9]/', '', $wa);
                                        $waLink = '<a href="https://wa.me/52' . $cleanWa . '" target="_blank" class="text-success fw-bold text-decoration-none"><i class="bi bi-whatsapp"></i> ' . $wa . '</a>';
                                    }
                                    if (!empty($instancia)) {
                                        $waLink .= '<small class="text-muted d-block">Inst: <code class="text-dark fw-bold">' . $instancia . '</code></small>';
                                    }

                                    $defaultBadge = $isDefault ? ' <span class="badge bg-primary text-white ms-1"><i class="bi bi-star-fill text-warning me-1"></i>Predeterminada</span>' : '';

                                    echo '
                                    <tr id="fila' . $renglon . '">
                                        <td>' . $rowc['id_sucursal'] . '</td>
                                        <td><strong>' . $desc . '</strong>' . $defaultBadge . '</td>
                                        <td><small>' . ($dir !== '' ? $dir : '<span class="text-muted">Sin dirección</span>') . '</small></td>
                                        <td><small><span class="badge bg-warning text-dark">' . ($hor !== '' ? $hor : 'Por definir') . '</span></small></td>
                                        <td><small>' . $waLink . '</small></td>
                                        <td align="center">' . $mapBtn . '</td>
                                        <td align="center"><input type="checkbox" class="form-check-input" disabled ' . ($rowc['activo'] == "1" ? 'checked' : '') . '></td>
                                        <td>' . $username . '</td>
                                        <td align="center">
                                            <a href="?accion=delete&id_sucursal=' . $rowc['id_sucursal'] . '" title="Eliminar" onclick="return confirm(\'¿Esta seguro de borrar la sucursal ' . $desc . '?\')"><img class="imga" src="../img/icons/trash.svg"></a>
                                            <a href="#" title="Editar sucursal" data-bs-toggle="modal" data-bs-target="#editModal" 
                                               data-id="' . $rowc['id_sucursal'] . '" 
                                               data-desc="' . htmlspecialchars($rowc['desc_sucursal'], ENT_QUOTES) . '" 
                                               data-direccion="' . htmlspecialchars($rowc['direccion'] ?? '', ENT_QUOTES) . '" 
                                               data-latitud="' . htmlspecialchars($rowc['latitud'] ?? '', ENT_QUOTES) . '" 
                                               data-longitud="' . htmlspecialchars($rowc['longitud'] ?? '', ENT_QUOTES) . '" 
                                               data-horario="' . htmlspecialchars($rowc['horario'] ?? '', ENT_QUOTES) . '" 
                                               data-whatsapp="' . htmlspecialchars($rowc['whatsapp'] ?? '', ENT_QUOTES) . '" 
                                               data-wa-instancia="' . htmlspecialchars($rowc['wa_instancia'] ?? '', ENT_QUOTES) . '" 
                                               data-es-default="' . ($rowc['es_default'] ?? 0) . '" 
                                               data-activo="' . $rowc['activo'] . '">
                                                <img class="imga" src="../img/icons/pencil-square.svg">
                                            </a>
                                        </td>
                                    </tr>';
                                }
                                ?>  
                                </tbody>
                            </table> 
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <div class="modal fade modal-lg" id="editModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="ModalLabelTitle">Editar Sucursal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form class="needs-validation" action="#" method="post" novalidate>
                        <input type="hidden" name="id_sucursal_e" id="id_sucursal_e">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="desc_sucursal_e" class="form-label fw-bold">Sucursal *:</label>
                                <input type="text" class="form-control" id="desc_sucursal_e" name="desc_sucursal_e" placeholder="Descripción de la sucursal" required>
                                <div class="invalid-feedback">Favor de ingresar descripción.</div>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="direccion_e" class="form-label fw-bold">Dirección:</label>
                                <input type="text" class="form-control" id="direccion_e" name="direccion_e" placeholder="Escribe la dirección para buscar..." autocomplete="off">
                                <div id="searchResultsEdit" class="list-group position-absolute w-100 shadow search-results-list d-none"></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <label for="latitud_e" class="form-label fw-bold">Latitud:</label>
                                    <input type="text" class="form-control" id="latitud_e" name="latitud_e" placeholder="Ej: 19.6021539">
                                </div>
                                <div class="col-md-6">
                                    <label for="longitud_e" class="form-label fw-bold">Longitud:</label>
                                    <input type="text" class="form-control" id="longitud_e" name="longitud_e" placeholder="Ej: -99.0451077">
                                </div>
                            </div>

                            <!-- Mapa Interactivo en Modal -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold text-dark mb-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Mapa Google Maps (Arrastra el pin o haz clic para ajustar las coordenadas):</label>
                                    <div id="mapEditContainer" class="interactive-map border shadow-sm"></div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="horario_e" class="form-label fw-bold">Horario de Atención:</label>
                                    <input type="text" class="form-control" id="horario_e" name="horario_e" placeholder="Ej: 7:30 AM - 3:30 PM">
                                </div>
                                <div class="col-md-4">
                                    <label for="whatsapp_e" class="form-label fw-bold">Número de WhatsApp:</label>
                                    <input type="text" class="form-control" id="whatsapp_e" name="whatsapp_e" placeholder="Ej: 5512345678">
                                </div>
                                <div class="col-md-4">
                                    <label for="wa_instancia_e" class="form-label fw-bold text-dark"><i class="bi bi-robot text-success me-1"></i> Instancia Evolution API:</label>
                                    <input type="text" class="form-control" id="wa_instancia_e" name="wa_instancia_e" placeholder="Ej: pruebas, test_abstrings">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" name="activo_e" type="checkbox" value="1" id="activo_e">
                                        <label class="form-check-label fw-bold" for="activo_e">¿Sucursal Activa?</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" name="es_default_e" type="checkbox" value="1" id="es_default_e">
                                        <label class="form-check-label fw-bold text-primary" for="es_default_e"><i class="bi bi-star-fill text-warning me-1"></i> ¿Sucursal Predeterminada (Default)?</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary fw-bold" name="editar">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if ($title != ""): ?>
            <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="staticBackdropLabel"><?php echo storefront_escape($title); ?></h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body"><?php echo storefront_escape($body); ?></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            var mapAdd = null, markerAdd = null, geocoder = null;
            var mapEdit = null, markerEdit = null;
            const DEFAULT_LAT = 19.6021539;
            const DEFAULT_LNG = -99.0451077;

            $(document).ready(function () {
                $('#sucursales').DataTable({
                    language: {
                        "decimal": "",
                        "emptyTable": "No hay información",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ Entradas",
                        "infoEmpty": "Mostrando 0 a 0 de 0 Entradas",
                        "infoFiltered": "(Filtrado de _MAX_ total entradas)",
                        "infoPostFix": "",
                        "thousands": ",",
                        "lengthMenu": "Mostrar _MENU_ Entradas",
                        "loadingRecords": "Cargando...",
                        "processing": "Procesando...",
                        "search": "Buscar:",
                        "zeroRecords": "Sin resultados encontrados",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    }
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

                setupLiveAddressSearch('direccion', 'latitud', 'longitud', 'searchResultsAdd', () => mapAdd, () => markerAdd);
                setupLiveAddressSearch('direccion_e', 'latitud_e', 'longitud_e', 'searchResultsEdit', () => mapEdit, () => markerEdit);
            });

            // Inicializar Mapa Google Maps
            function initGoogleMaps() {
                if (typeof google === 'undefined' || typeof google.maps === 'undefined') return;

                geocoder = new google.maps.Geocoder();

                const mapAddElem = document.getElementById('mapAddContainer');
                if (mapAddElem) {
                    mapAdd = new google.maps.Map(mapAddElem, {
                        center: { lat: DEFAULT_LAT, lng: DEFAULT_LNG },
                        zoom: 14,
                        mapTypeControl: true,
                        streetViewControl: false
                    });

                    markerAdd = new google.maps.Marker({
                        position: { lat: DEFAULT_LAT, lng: DEFAULT_LNG },
                        map: mapAdd,
                        draggable: true,
                        title: "Arrastra el pin para ajustar las coordenadas"
                    });

                    google.maps.event.addListener(markerAdd, 'dragend', function (event) {
                        updateCoordsAndAddress(event.latLng.lat(), event.latLng.lng(), 'latitud', 'longitud', 'direccion');
                    });

                    google.maps.event.addListener(mapAdd, 'click', function (event) {
                        markerAdd.setPosition(event.latLng);
                        updateCoordsAndAddress(event.latLng.lat(), event.latLng.lng(), 'latitud', 'longitud', 'direccion');
                    });
                }
            }

            // Manejo del Modal de Edición
            var editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function (event) {
                    var trigger = event.relatedTarget;
                    var $btn = $(trigger).closest('a');
                    var id_sucursal = $btn.data('id');
                    var desc_sucursal = $btn.data('desc');
                    var direccion = $btn.data('direccion');
                    var latitud = $btn.data('latitud');
                    var longitud = $btn.data('longitud');
                    var horario = $btn.data('horario');
                    var whatsapp = $btn.data('whatsapp');
                    var wa_instancia = $btn.data('wa-instancia');
                    var es_default = $btn.data('es-default');
                    var activo = $btn.data('activo');

                    $("#ModalLabelTitle").html("Editar sucursal: " + desc_sucursal + " (#" + id_sucursal + ")");
                    $("#id_sucursal_e").val(id_sucursal);
                    $("#desc_sucursal_e").val(desc_sucursal);
                    $("#direccion_e").val(direccion);
                    $("#latitud_e").val(latitud);
                    $("#longitud_e").val(longitud);
                    $("#horario_e").val(horario);
                    $("#whatsapp_e").val(whatsapp);
                    $("#wa_instancia_e").val(wa_instancia || '');
                    $("#es_default_e").prop("checked", es_default == 1);
                    $("#activo_e").prop("checked", activo == 1);
                });

                editModal.addEventListener('shown.bs.modal', function () {
                    if (typeof google === 'undefined' || typeof google.maps === 'undefined') return;

                    const latVal = parseFloat($("#latitud_e").val()) || DEFAULT_LAT;
                    const lngVal = parseFloat($("#longitud_e").val()) || DEFAULT_LNG;
                    const mapEditElem = document.getElementById('mapEditContainer');

                    if (!mapEdit && mapEditElem) {
                        mapEdit = new google.maps.Map(mapEditElem, {
                            center: { lat: latVal, lng: lngVal },
                            zoom: 15,
                            mapTypeControl: true,
                            streetViewControl: false
                        });

                        markerEdit = new google.maps.Marker({
                            position: { lat: latVal, lng: lngVal },
                            map: mapEdit,
                            draggable: true
                        });

                        google.maps.event.addListener(markerEdit, 'dragend', function (event) {
                            updateCoordsAndAddress(event.latLng.lat(), event.latLng.lng(), 'latitud_e', 'longitud_e', 'direccion_e');
                        });

                        google.maps.event.addListener(mapEdit, 'click', function (event) {
                            markerEdit.setPosition(event.latLng);
                            updateCoordsAndAddress(event.latLng.lat(), event.latLng.lng(), 'latitud_e', 'longitud_e', 'direccion_e');
                        });
                    } else if (mapEdit && markerEdit) {
                        google.maps.event.trigger(mapEdit, 'resize');
                        mapEdit.setCenter({ lat: latVal, lng: lngVal });
                        markerEdit.setPosition({ lat: latVal, lng: lngVal });
                    }
                });
            }

            function updateCoordsAndAddress(lat, lng, latId, lngId, dirId) {
                const latInput = document.getElementById(latId);
                const lngInput = document.getElementById(lngId);
                const dirInput = document.getElementById(dirId);

                if (latInput) latInput.value = lat;
                if (lngInput) lngInput.value = lng;

                if (geocoder && dirInput) {
                    geocoder.geocode({ location: { lat: lat, lng: lng } }, function (results, status) {
                        if (status === 'OK' && results[0]) {
                            dirInput.value = results[0].formatted_address;
                        }
                    });
                }
            }

            // Búsqueda en Vivo de Direcciones (Debounced)
            let searchDebounce = null;
            function setupLiveAddressSearch(inputId, latId, lngId, resultsId, getMapFn, getMarkerFn) {
                const input = document.getElementById(inputId);
                const container = document.getElementById(resultsId);
                if (!input || !container) return;

                input.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter' || e.key === 'Escape') return;
                    const query = this.value.trim();
                    clearTimeout(searchDebounce);

                    if (query.length < 3) {
                        container.innerHTML = '';
                        container.classList.add('d-none');
                        return;
                    }

                    searchDebounce = setTimeout(() => {
                        performGeoSearch(query, inputId, latId, lngId, resultsId, getMapFn, getMarkerFn);
                    }, 350);
                });

                document.addEventListener('click', function(e) {
                    if (!input.contains(e.target) && !container.contains(e.target)) {
                        container.classList.add('d-none');
                    }
                });
            }

            function performGeoSearch(query, inputId, latId, lngId, resultsId, getMapFn, getMarkerFn) {
                const input = document.getElementById(inputId);
                const latInput = document.getElementById(latId);
                const lngInput = document.getElementById(lngId);
                const container = document.getElementById(resultsId);
                if (!container) return;

                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=mx&limit=6`;
                
                fetch(url, { headers: { 'Accept-Language': 'es' } })
                    .then(res => res.json())
                    .then(data => {
                        container.innerHTML = '';
                        if (!data || data.length === 0) {
                            container.innerHTML = '<div class="list-group-item small text-muted">No se encontraron resultados.</div>';
                            container.classList.remove('d-none');
                            return;
                        }

                        data.forEach(item => {
                            const a = document.createElement('a');
                            a.href = 'javascript:void(0);';
                            a.className = 'list-group-item list-group-item-action small py-2 d-flex align-items-start';
                            a.innerHTML = `<i class="bi bi-geo-alt-fill text-danger me-2 mt-1"></i> <div><strong>${item.display_name}</strong></div>`;
                            
                            // Detener la propagación del evento para que NO cierre el Modal de Bootstrap
                            a.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                            });

                            a.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();

                                input.value = item.display_name;
                                const lat = parseFloat(item.lat);
                                const lng = parseFloat(item.lon);

                                if (latInput) latInput.value = lat;
                                if (lngInput) lngInput.value = lng;

                                const mapObj = typeof getMapFn === 'function' ? getMapFn() : getMapFn;
                                const markerObj = typeof getMarkerFn === 'function' ? getMarkerFn() : getMarkerFn;

                                if (mapObj && markerObj) {
                                    mapObj.setCenter({ lat: lat, lng: lng });
                                    mapObj.setZoom(16);
                                    markerObj.setPosition({ lat: lat, lng: lng });
                                }

                                container.innerHTML = '';
                                container.classList.add('d-none');
                            });
                            container.appendChild(a);
                        });
                        container.classList.remove('d-none');
                    })
                    .catch(err => {
                        console.error("Error en búsqueda:", err);
                    });
            }

            <?php if ($title != ""): ?>
                var myModal = new bootstrap.Modal(document.getElementById("staticBackdrop"), {});
                document.addEventListener("DOMContentLoaded", function () {
                    myModal.show();
                });
            <?php endif; ?>
        </script>      
    </body>
</html>
