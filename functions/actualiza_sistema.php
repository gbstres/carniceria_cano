<?php

// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require_once "../functions/config.php";
require_once "../functions/config_2.php";

if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    // Verificar la conexión
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }

    // Obtener la lista de tablas
    $tables = array();
    $sql = "SHOW TABLES";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    }

    // Recorrer las tablas y obtener las columnas
    foreach ($tables as $table) {
        //echo "Tabla: " . $table . "<br>";
        $columnas = array();
        $llaves = array();
        $sql = "DESCRIBE " . $table;
        $result = $conn->query($sql);
        $cad1 = "select * from " . $table . " where fecha_ingreso = '" . $fecha . "' or fecha_act = '" . $fecha . "'";
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $columnas[] = $row['Field'];
                if ($row['Key'] == 'PRI') {
                    $llaves[] = $row['Field'];
                }
            }
        }
        //echo $cad1 . "<br>";

        $sqltabla = mysqli_query($link, $cad1);
        while ($rows = mysqli_fetch_assoc($sqltabla)) {
            $cad2 = "";
            $coma = "";
            foreach ($columnas as $columna) {
                $cad2 = $cad2 . $coma . "'" . $rows[$columna] . "'";
                $coma = ",";
            }
            $cad3 = "";
            $and = "";
            foreach ($llaves as $llave) {
                $cad3 = $cad3 . $and . $llave . " = '" . $rows[$llave] . "'";
                $and = " and ";
            }

            $cad_delete = "delete from " . $table . " where " . $cad3;
            if (mysqli_query($link2, $cad_delete)) {
                $cad_insert = "insert into " . $table . " values(" . $cad2 . ")";
                if (!mysqli_query($link2, $cad_insert)) {
                    echo 'No se pudo respaldar la información: ' . $cad_insert . "<br>";
                }
            } else {
                echo 'No se pudo eliminar la información: ' . $cad_delete . "<br>";
            }
        }
    }
    echo "La información se ha respaldado correctamente";
    $conn->close();
}
?>