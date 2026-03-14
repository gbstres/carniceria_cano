<?php
require __DIR__ . '/../vendor/autoload.php';
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

// Carga la clave privada
$privateKey = PublicKeyLoader::loadPrivateKey(file_get_contents('C:\Users\Grillo\Documents\Respaldo\keys\private_designer2.ppk'));

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
$rutaLocal = 'C:/xampp/carniceriacano';
descargarCarpeta($sftp, $rutaRemota, $rutaLocal);