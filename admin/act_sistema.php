<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect him to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login/login.php");
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

require_once "../functions/config.php";
date_default_timezone_set("America/Mexico_City");
// Define variables and initialize with empty values
$id_sucursal = $_SESSION["id_sucursal"];

if (isset($_POST['fecha'])) {
    
}


if (isset($_POST['fecha'])) {
    $fecha = $_POST['fecha'];
} else {
    $fecha = date('Y-m-d');
}
?>





<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Carnicería Cano">
        <meta name="author" content="Gerardo Bautista">
        <link rel="shortcut icon" href="../img/logo_1.png">
        <title>Respaldo</title>

        <script src="../js/jquery-3.5.1.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/jquery.dataTables.min.js"></script>
        <script src="../js/sum().js"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.dataTables.editable.js" type="text/javascript"></script>
        <script src="../js/jquery.jeditable.js" type="text/javascript"></script>
        <script src="../js/jquery.validate.js" type="text/javascript"></script>
        <script src="../js/gijgo.min.js" type="text/javascript"></script>

        <style>
            @import "../css/bootstrap.css";
            input[type=number]::-webkit-inner-spin-button,
            input[type=number]::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            input[type=number] {
                -moz-appearance:textfield;
            }
            td .form-control{
                text-transform: uppercase;
            }
        </style>


        <!-- Custom styles for this template -->
        <link href="../css/navbar.css" rel="stylesheet">
        <link href="../css/jquery.dataTables.min.css" rel="stylesheet">
        <link href="../css/gijgo.min.css" rel="stylesheet" type="text/css" />

    </head>
    <body>
        <main>
            <div class="container">
<?php require_once "../components/nav.php" ?>
                <div>
                    <div class="bg-light p-4 rounded ">
                        <div class="col-sm-8 mx-auto">
                            <h1 class="text-center">Actualiza sistema</h1>
                        </div>
                        <form class="row g-3 needs-validation" action="#" method="post" novalidate>
                            
                            <div class="row g-3">
                                <input type="hidden" name="actualiza" value="1" id="actualiza">
                            </div>
                            <div class="col-12 text-center" >
                                <input class="btn btn-primary black bg-silver" type="submit" value="Actualizar" id="buscar_fecha">
                            </div>
                        </form>
                        <br>
                        <br>


                        <?php
                        if (isset($_POST['actualiza'])) {


// Carga la clave privada
                            $privateKey = PublicKeyLoader::loadPrivateKey(file_get_contents('C:\xampp\htdocs\carniceriacano\keys\private_designer2.ppk'));

// Conectar al servidor SFTP
                            $sftp = new SFTP('34.121.229.39');

                            if (!$sftp->login('designer2', $privateKey)) {
                                exit('Fallo en la autenticación con llave pública.');
                            }

                            echo "Conexión exitosa con llave pública.";

// Función para descargar una carpeta completa
                            function descargarCarpeta($sftp, $rutaRemota, $rutaLocal) {
                                $archivos = $sftp->nlist($rutaRemota);
                                if ($archivos === false) {
                                    echo "No se pudo listar la carpeta remota.\n";
                                    return;
                                }

                                // Crea la carpeta local si no existe
                                if (!is_dir($rutaLocal)) {
                                    mkdir($rutaLocal, 0777, true);
                                }

                                foreach ($archivos as $archivo) {
                                    if ($archivo === '.' || $archivo === '..') {
                                        continue;
                                    }

                                    $rutaRemotaCompleta = $rutaRemota . '/' . $archivo;
                                    $rutaLocalCompleta = $rutaLocal . '/' . $archivo;

                                    if ($sftp->is_dir($rutaRemotaCompleta)) {
                                        // Llamada recursiva si es un directorio
                                        descargarCarpeta($sftp, $rutaRemotaCompleta, $rutaLocalCompleta);
                                    } else {
                                        // Descargar archivo
                                        if ($sftp->get($rutaRemotaCompleta, $rutaLocalCompleta)) {
                                            echo "Archivo descargado: $rutaRemotaCompleta\n";
                                        } else {
                                            echo "Error al descargar: $rutaRemotaCompleta\n";
                                        }
                                    }
                                }
                            }

// Llamada a la función con la ruta remota y la ruta local
                            $rutaRemota = '/var/www/carniceriacano/update';
                            $rutaLocal = 'C:/xampp/htdocs/carniceriacano';
                            descargarCarpeta($sftp, $rutaRemota, $rutaLocal);
                        }
                        ?>




                    </div>
                </div>
            </div>
        </main>



        <script src="../js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready(function () {



            })


        </script>      
    </body>
</html>
