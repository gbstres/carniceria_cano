<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    exit;
}
?>
<?php

require_once "config.php";

$id_cierre = $_POST['id'];
$value = mb_strtoupper($_POST['value']);
$columnName = $_POST['columnName'];

$cadena = $_POST['id'];
$value = mb_strtoupper($_POST['value']);
$columnName = $_POST['columnName'];

$separada = explode(',', $cadena);
$clave = $separada[0];
$fecha = $separada[1];

date_default_timezone_set("America/Mexico_City");
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $rowcierre = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_cierre WHERE id_sucursal = '$id_sucursal' and fecha = $fecha and clave = $clave"));
    $existe = $rowgasto['clave'];
    if ($existe == null) {
        $sql = "INSERT INTO cc_cierre (id_sucursal, id_gasto, codigo, descripcion, precio, cantidad, comentario, id_usuario, fecha_ingreso, hora_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "iissddsiss", $id_sucursal, $id_gasto, $codigo, $descripcion, $precio, $cantidad, $comentario, $id_usuario, $fecha_ingreso, $hora_ingreso);
            if (mysqli_stmt_execute($stmt)) {
                $importe = round($cantidad * $precio, 2);
                $usuario = mysqli_fetch_assoc(mysqli_query($link, "SELECT * FROM cc_users where id  =" . $id_usuario));
                $response_array [] = array('id_gasto' => $id_gasto, 'codigo' => $codigo, 'descripcion' => $descripcion, 'precio' => $precio, 'cantidad' => $cantidad, 'comentario' => $comentario, 'fecha' => $fecha_ingreso, 'hora' => $hora_ingreso, 'importe' => $importe, 'usuario' => $usuario['username']);
            } else {
                $response_array [] = array('id_gasto' => 0);
            }
        }
        echo json_encode($response_array);
    } else {


        // Set parameters
        $id_sucursal = $_SESSION["id_sucursal"];
        $id_usuario_act = $_SESSION['id'];
        $fecha_act = date('y-m-d');
        $hora_act = date('H:i:s');
        $update_venta = mysqli_query($link, "UPDATE cc_gastos SET "
                        . "$columnName = '$value', fecha_act='$fecha_act', hora_act='$hora_act', id_usuario_act='$id_usuario_act' "
                        . "WHERE id_sucursal='$id_sucursal' and id_gasto='$id_gasto'")
                or die(mysqli_error());
        if ($update_venta) {
            echo $value;
        } else {
            echo 'Error, no se pudo actualizar ';
        }
    }
}
?>