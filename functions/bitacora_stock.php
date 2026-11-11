<?php

if (!function_exists('registrarBitacoraStock')) {
    /**
     * Registra un movimiento o ajuste de stock en la bitácora del sistema.
     *
     * @param mysqli $link Conexión a la base de datos
     * @param int $idSucursal ID de la sucursal donde ocurre el cambio
     * @param string $tipo 'PRODUCTO' o 'CATEGORIA'
     * @param string $idItem Código del producto o ID de la categoría
     * @param string $descripcion Nombre o descripción del producto/categoría
     * @param float $stockAnterior Stock antes del cambio
     * @param float $stockNuevo Nuevo stock asignado
     * @param string $motivo Razón o comentario obligatorio del porqué se actualiza
     * @param int $idUsuario ID del usuario que realiza la actualización
     * @param string $usuarioNombre Nombre o username del usuario
     * @param string $origen Pantalla o módulo que originó el cambio
     * @return int|false Retorna el ID de la bitácora creada o false en caso de error
     */
    function registrarBitacoraStock(
        mysqli $link,
        int $idSucursal,
        string $tipo,
        string $idItem,
        string $descripcion,
        float $stockAnterior,
        float $stockNuevo,
        string $motivo,
        int $idUsuario,
        string $usuarioNombre = '',
        string $origen = 'MANUAL'
    ) {
        $motivo = trim($motivo);
        if ($motivo === '') {
            return false;
        }

        date_default_timezone_set('America/Mexico_City');
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        $diferencia = round($stockNuevo - $stockAnterior, 3);

        // Si no se proporcionó el nombre del usuario, buscarlo en cc_users
        if (trim($usuarioNombre) === '' && $idUsuario > 0) {
            $stmtUser = mysqli_prepare($link, "SELECT nombre, username FROM cc_users WHERE id = ? LIMIT 1");
            if ($stmtUser) {
                mysqli_stmt_bind_param($stmtUser, "i", $idUsuario);
                mysqli_stmt_execute($stmtUser);
                $resUser = mysqli_stmt_get_result($stmtUser);
                if ($rowUser = mysqli_fetch_assoc($resUser)) {
                    $usuarioNombre = !empty($rowUser['nombre']) ? $rowUser['nombre'] : $rowUser['username'];
                }
                mysqli_stmt_close($stmtUser);
            }
        }

        if (trim($usuarioNombre) === '') {
            $usuarioNombre = 'Usuario #' . $idUsuario;
        }

        $sql = "INSERT INTO cc_bitacora_stock 
            (id_sucursal, tipo, id_item, descripcion, stock_anterior, stock_nuevo, diferencia, motivo, id_usuario, usuario, fecha, hora, origen) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            error_log("Error al preparar bitácora stock: " . mysqli_error($link));
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "isssdddsissss",
            $idSucursal,
            $tipo,
            $idItem,
            $descripcion,
            $stockAnterior,
            $stockNuevo,
            $diferencia,
            $motivo,
            $idUsuario,
            $usuarioNombre,
            $fecha,
            $hora,
            $origen
        );

        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            error_log("Error al insertar bitácora stock: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        $idBitacora = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);

        // Encolar para sincronización si está disponible
        if (function_exists('cc_sync_enqueue')) {
            cc_sync_enqueue($link, $idSucursal, 'bitacora_stock', 'upsert', [
                'id_bitacora' => (int) $idBitacora,
            ], [
                'tipo' => $tipo,
                'id_item' => $idItem,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo' => $stockNuevo,
                'diferencia' => $diferencia,
                'motivo' => $motivo,
                'id_usuario' => $idUsuario,
                'usuario' => $usuarioNombre,
                'fecha' => $fecha,
                'hora' => $hora,
                'origen' => $origen,
            ]);
        }

        return (int) $idBitacora;
    }
}
