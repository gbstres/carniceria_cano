<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    $returnTo = $_SERVER['REQUEST_URI'] ?? '/reportes/inventario_bitacora.php';
    header('location: ../login/login.php?return_to=' . rawurlencode($returnTo));
    exit;
}

require_once '../functions/config.php';
date_default_timezone_set('America/Mexico_City');

if (!tienePermiso('ver')) {
    http_response_code(403);
    exit('No tienes permiso para consultar este reporte.');
}

function activosH($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function activosFecha($value, $fallback)
{
    $date = DateTime::createFromFormat('Y-m-d', (string) $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

function activosValor($value, array $allowed, $fallback)
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function activosEjecutar(mysqli $link, $sql, $types = '', array $params = [])
{
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($link));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException(mysqli_stmt_error($stmt));
    }
    return $stmt;
}

$tiposEvento = ['FALLA', 'MANTENIMIENTO', 'INSPECCION', 'REPARACION', 'OBSERVACION'];
$estadosActivo = ['OPERATIVO', 'REQUIERE ATENCION', 'FUERA DE SERVICIO', 'BAJA'];
$estatusEvento = ['PENDIENTE', 'EN PROCESO', 'RESUELTO'];
$mensaje = '';
$error = '';
$usuario = (int) ($_SESSION['id'] ?? 0);
$hoy = date('Y-m-d');
$ahora = date('H:i');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_activo'])) {
        $idSucursal = (int) ($_POST['id_sucursal'] ?? 0);
        $tipo = mb_strtoupper(trim((string) ($_POST['tipo'] ?? '')));
        $nombre = mb_strtoupper(trim((string) ($_POST['nombre'] ?? '')));
        $identificador = mb_strtoupper(trim((string) ($_POST['identificador'] ?? '')));
        $estado = activosValor($_POST['estado'] ?? '', $estadosActivo, 'OPERATIVO');
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
        if ($idSucursal <= 0 || $tipo === '' || $nombre === '') {
            throw new RuntimeException('Sucursal, tipo y nombre son obligatorios.');
        }
        $stmt = activosEjecutar($link, 'INSERT INTO cc_activos (id_sucursal,tipo,nombre,identificador,estado,observaciones,activo,id_usuario,fecha_ingreso,hora_ingreso) VALUES (?,?,?,?,?,?,1,?,?,?)', 'isssssiss', [$idSucursal, $tipo, $nombre, $identificador, $estado, $observaciones, $usuario, $hoy, date('H:i:s')]);
        mysqli_stmt_close($stmt);
        $mensaje = 'Activo registrado correctamente.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_evento'])) {
        $idActivo = (int) ($_POST['id_activo'] ?? 0);
        $tipoEvento = activosValor($_POST['tipo_evento'] ?? '', $tiposEvento, 'OBSERVACION');
        $fechaEvento = activosFecha($_POST['fecha_evento'] ?? '', $hoy);
        $horaEvento = preg_match('/^\d{2}:\d{2}$/', $_POST['hora_evento'] ?? '') ? $_POST['hora_evento'] . ':00' : date('H:i:s');
        $detalle = trim((string) ($_POST['detalle'] ?? ''));
        $estatus = activosValor($_POST['estatus'] ?? '', $estatusEvento, 'PENDIENTE');
        if ($idActivo <= 0 || $detalle === '') {
            throw new RuntimeException('Activo y detalle del evento son obligatorios.');
        }
        $stmt = activosEjecutar($link, 'INSERT INTO cc_activos_bitacora (id_activo,tipo_evento,fecha_evento,hora_evento,detalle,estatus,id_usuario,fecha_ingreso,hora_ingreso) VALUES (?,?,?,?,?,?,?,?,?)', 'isssssiss', [$idActivo, $tipoEvento, $fechaEvento, $horaEvento, $detalle, $estatus, $usuario, $hoy, date('H:i:s')]);
        mysqli_stmt_close($stmt);
        if ($estatus !== 'RESUELTO' && in_array($tipoEvento, ['FALLA', 'MANTENIMIENTO'], true)) {
            $stmt = activosEjecutar($link, "UPDATE cc_activos SET estado='REQUIERE ATENCION',id_usuario_act=?,fecha_act=?,hora_act=? WHERE id_activo=? AND estado<>'BAJA'", 'issi', [$usuario, $hoy, date('H:i:s'), $idActivo]);
            mysqli_stmt_close($stmt);
        }
        $mensaje = 'Evento agregado a la bitácora.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_evento'])) {
        $idBitacora = (int) ($_POST['id_bitacora'] ?? 0);
        $estatus = activosValor($_POST['estatus_evento'] ?? '', $estatusEvento, 'PENDIENTE');
        $solucion = trim((string) ($_POST['solucion'] ?? ''));
        $fechaResolucion = $estatus === 'RESUELTO' ? $hoy : null;
        $horaResolucion = $estatus === 'RESUELTO' ? date('H:i:s') : null;
        $stmt = activosEjecutar($link, 'UPDATE cc_activos_bitacora SET estatus=?,solucion=?,fecha_resolucion=?,hora_resolucion=?,id_usuario_act=?,fecha_act=?,hora_act=? WHERE id_bitacora=?', 'ssssissi', [$estatus, $solucion, $fechaResolucion, $horaResolucion, $usuario, $hoy, date('H:i:s'), $idBitacora]);
        mysqli_stmt_close($stmt);
        $mensaje = 'Evento actualizado correctamente.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$fecha1 = activosFecha($_GET['fecha1'] ?? date('Y-m-01'), date('Y-m-01'));
$fecha2 = activosFecha($_GET['fecha2'] ?? $hoy, $hoy);
if ($fecha1 > $fecha2) {
    [$fecha1, $fecha2] = [$fecha2, $fecha1];
}
$filtroSucursal = (int) ($_GET['sucursal'] ?? 0);
$filtroEstatus = activosValor($_GET['estatus'] ?? '', array_merge([''], $estatusEvento), '');
$sucursales = $activos = $eventos = [];

try {
    $result = mysqli_query($link, "SELECT id_sucursal,desc_sucursal FROM cc_sucursales WHERE activo=1 AND UPPER(desc_sucursal) NOT LIKE '%PRUEBA%' ORDER BY desc_sucursal");
    if (!$result) {
        throw new RuntimeException(mysqli_error($link));
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $sucursales[(int) $row['id_sucursal']] = $row['desc_sucursal'];
    }
    $result = mysqli_query($link, "SELECT a.id_activo,a.tipo,a.nombre,a.identificador,a.estado,a.observaciones,s.desc_sucursal,
            (SELECT COUNT(*) FROM cc_activos_bitacora bp WHERE bp.id_activo=a.id_activo AND bp.estatus<>'RESUELTO') pendientes,
            (SELECT MAX(CONCAT(bu.fecha_evento,' ',bu.hora_evento)) FROM cc_activos_bitacora bu WHERE bu.id_activo=a.id_activo) ultimo_evento
        FROM cc_activos a INNER JOIN cc_sucursales s ON s.id_sucursal=a.id_sucursal
        WHERE a.activo=1 AND s.activo=1 AND UPPER(s.desc_sucursal) NOT LIKE '%PRUEBA%'
        ORDER BY a.tipo,a.nombre");
    if (!$result) {
        throw new RuntimeException('Falta ejecutar la migración de inventario y bitácora.');
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $activos[(int) $row['id_activo']] = $row;
    }
    $where = ['b.fecha_evento BETWEEN ? AND ?'];
    $types = 'ss';
    $params = [$fecha1, $fecha2];
    if ($filtroSucursal > 0) {
        $where[] = 'a.id_sucursal=?';
        $types .= 'i';
        $params[] = $filtroSucursal;
    }
    if ($filtroEstatus !== '') {
        $where[] = 'b.estatus=?';
        $types .= 's';
        $params[] = $filtroEstatus;
    }
    $stmt = activosEjecutar($link, 'SELECT b.*,a.tipo,a.nombre,a.identificador,a.estado estado_activo,s.desc_sucursal,u.nombre usuario FROM cc_activos_bitacora b INNER JOIN cc_activos a ON a.id_activo=b.id_activo INNER JOIN cc_sucursales s ON s.id_sucursal=a.id_sucursal LEFT JOIN cc_users u ON u.id=b.id_usuario WHERE ' . implode(' AND ', $where) . ' ORDER BY b.fecha_evento DESC,b.hora_evento DESC,b.id_bitacora DESC', $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $eventos[] = $row;
    }
    mysqli_stmt_close($stmt);
} catch (Throwable $e) {
    if ($error === '') {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Carnicería Cano"><link rel="shortcut icon" href="../img/logo_1.png"><title>Inventario y bitácora</title>
<style>@import "../css/bootstrap.css";</style><link href="../css/navbar.css" rel="stylesheet"></head>
<body><main><div class="container"><?php require_once '../components/nav.php'; ?>
<div class="bg-light p-4 rounded"><h1 class="text-center">Inventario y bitácora de activos</h1>
<?php if ($mensaje !== '') { ?><div class="alert alert-success"><?php echo activosH($mensaje); ?></div><?php } ?>
<?php if ($error !== '') { ?><div class="alert alert-danger"><?php echo activosH($error); ?></div><?php } ?>

<div class="row g-4 mt-1">
<section class="col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Registrar activo</h2><form method="post" class="row g-3">
<div class="col-md-6"><label class="form-label">Sucursal</label><select class="form-select" name="id_sucursal" required><option value="">Seleccione</option><?php foreach ($sucursales as $id=>$nombre) { ?><option value="<?php echo $id; ?>"><?php echo activosH($nombre); ?></option><?php } ?></select></div>
<div class="col-md-6"><label class="form-label">Tipo</label><input class="form-control" name="tipo" placeholder="Refrigerador, moto..." required></div>
<div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" placeholder="Refrigerador cámara 1" required></div>
<div class="col-md-6"><label class="form-label">Identificación</label><input class="form-control" name="identificador" placeholder="Serie, placas o número interno"></div>
<div class="col-md-6"><label class="form-label">Estado</label><select class="form-select" name="estado"><?php foreach ($estadosActivo as $estado) { ?><option><?php echo activosH($estado); ?></option><?php } ?></select></div>
<div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="observaciones" rows="2"></textarea></div>
<div class="col-12 text-end"><button class="btn btn-primary" name="agregar_activo">Guardar activo</button></div></form></div></div></section>

<section class="col-lg-6"><div class="card h-100"><div class="card-body"><h2 class="h5">Agregar evento a la bitácora</h2><form method="post" class="row g-3">
<div class="col-12"><label class="form-label">Activo</label><select class="form-select" name="id_activo" required><option value="">Seleccione</option><?php foreach ($activos as $id=>$activo) { ?><option value="<?php echo $id; ?>"><?php echo activosH($activo['tipo'].' — '.$activo['nombre'].' — '.$activo['desc_sucursal']); ?></option><?php } ?></select></div>
<div class="col-md-6"><label class="form-label">Evento</label><select class="form-select" name="tipo_evento"><?php foreach ($tiposEvento as $tipo) { ?><option><?php echo activosH($tipo); ?></option><?php } ?></select></div>
<div class="col-md-6"><label class="form-label">Estatus</label><select class="form-select" name="estatus"><?php foreach ($estatusEvento as $estatus) { ?><option><?php echo activosH($estatus); ?></option><?php } ?></select></div>
<div class="col-md-6"><label class="form-label">Fecha</label><input type="date" class="form-control" name="fecha_evento" value="<?php echo $hoy; ?>" required></div>
<div class="col-md-6"><label class="form-label">Hora</label><input type="time" class="form-control" name="hora_evento" value="<?php echo $ahora; ?>" required></div>
<div class="col-12"><label class="form-label">Detalle</label><textarea class="form-control" name="detalle" rows="2" placeholder="Descripción de la falla, mantenimiento requerido u observación" required></textarea></div>
<div class="col-12 text-end"><button class="btn btn-primary" name="agregar_evento">Agregar evento</button></div></form></div></div></section></div>

<hr class="my-4"><h2 class="h4">Inventario de activos</h2><div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead class="table-light"><tr><th>Sucursal</th><th>Tipo</th><th>Activo</th><th>Identificación</th><th>Estado</th><th>Pendientes</th><th>Último evento</th><th>Observaciones</th></tr></thead><tbody>
<?php if (!$activos) { ?><tr><td colspan="8" class="text-center text-muted">No hay activos registrados.</td></tr><?php } ?>
<?php foreach ($activos as $activo) { ?><tr><td><?php echo activosH($activo['desc_sucursal']); ?></td><td><?php echo activosH($activo['tipo']); ?></td><td><?php echo activosH($activo['nombre']); ?></td><td><?php echo activosH($activo['identificador']); ?></td><td><?php echo activosH($activo['estado']); ?></td><td class="text-center"><?php echo (int) $activo['pendientes']; ?></td><td><?php echo activosH($activo['ultimo_evento'] ?? ''); ?></td><td><?php echo activosH($activo['observaciones']); ?></td></tr><?php } ?>
</tbody></table></div>

<hr class="my-4"><h2 class="h4">Consultar bitácora</h2><form method="get" class="row g-3 align-items-end mb-3">
<div class="col-md-3"><label class="form-label">Fecha inicial</label><input type="date" class="form-control" name="fecha1" value="<?php echo activosH($fecha1); ?>"></div><div class="col-md-3"><label class="form-label">Fecha final</label><input type="date" class="form-control" name="fecha2" value="<?php echo activosH($fecha2); ?>"></div>
<div class="col-md-2"><label class="form-label">Sucursal</label><select class="form-select" name="sucursal"><option value="0">Todas</option><?php foreach ($sucursales as $id=>$nombre) { ?><option value="<?php echo $id; ?>" <?php echo $filtroSucursal===$id?'selected':''; ?>><?php echo activosH($nombre); ?></option><?php } ?></select></div>
<div class="col-md-2"><label class="form-label">Estatus</label><select class="form-select" name="estatus"><option value="">Todos</option><?php foreach ($estatusEvento as $estatus) { ?><option <?php echo $filtroEstatus===$estatus?'selected':''; ?>><?php echo activosH($estatus); ?></option><?php } ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Consultar</button></div></form>

<div class="table-responsive"><table class="table table-sm table-bordered table-hover"><thead class="table-light"><tr><th>Fecha y hora</th><th>Sucursal</th><th>Activo</th><th>Evento</th><th>Detalle</th><th>Estatus</th><th>Solución / actualizar</th></tr></thead><tbody>
<?php if (!$eventos) { ?><tr><td colspan="7" class="text-center text-muted">No hay eventos en el rango seleccionado.</td></tr><?php } ?>
<?php foreach ($eventos as $evento) { ?><tr><td><?php echo activosH($evento['fecha_evento'].' '.$evento['hora_evento']); ?></td><td><?php echo activosH($evento['desc_sucursal']); ?></td><td><?php echo activosH($evento['tipo'].' — '.$evento['nombre']); ?><br><small><?php echo activosH($evento['identificador']); ?></small></td><td><?php echo activosH($evento['tipo_evento']); ?></td><td><?php echo nl2br(activosH($evento['detalle'])); ?></td><td><?php echo activosH($evento['estatus']); ?></td><td><form method="post" class="d-flex gap-1 flex-wrap"><input type="hidden" name="id_bitacora" value="<?php echo (int) $evento['id_bitacora']; ?>"><select class="form-select form-select-sm" name="estatus_evento" style="width:auto"><?php foreach ($estatusEvento as $estatus) { ?><option <?php echo $evento['estatus']===$estatus?'selected':''; ?>><?php echo activosH($estatus); ?></option><?php } ?></select><input class="form-control form-control-sm" name="solucion" value="<?php echo activosH($evento['solucion']); ?>" placeholder="Solución" style="min-width:180px"><button class="btn btn-sm btn-outline-primary" name="actualizar_evento">Guardar</button></form></td></tr><?php } ?>
</tbody></table></div></div></div></main><script src="../js/bootstrap.bundle.min.js"></script></body></html>
