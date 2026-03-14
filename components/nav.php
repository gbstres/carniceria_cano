<link rel="stylesheet" href="../css/bootstrap-icons.css">
<nav class="navbar navbar-expand-lg bg-light rounded" aria-label="Eleventh navbar example">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Carnicería Cano</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample09" aria-controls="navbarsExample09" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarsExample09">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="../main/inicio.php">Inicio</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Mantenimientos</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../mantenimientos/productos.php">Productos</a></li>
                        <?php
                        if (tienePermiso('ver')) {
                            echo '
                        <li><a class="dropdown-item" href="../mantenimientos/categorias.php">Categorías</a></li>
                        <li><a class="dropdown-item" href="../mantenimientos/sucursales.php">Sucursales</a></li>';
                        }
                        ?>
                        <li><a class="dropdown-item" href="../mantenimientos/clientes.php">Clientes</a></li>
                        <li><a class="dropdown-item" href="../mantenimientos/proveedores.php">Proveedores</a></li>
                        <?php
                        if (tienePermiso('ver')) {
                            echo '                 
                        <li><a class="dropdown-item" href="../mantenimientos/empleados.php">Empleados</a></li>';
                        }
                        ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Reportes</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../reportes/reporte_dia.php">Venta x día</a></li>
                        <li><a class="dropdown-item" href="../reportes/por_fecha.php">Venta x fecha</a></li>
                        <li><a class="dropdown-item" href="../reportes/compra_por_dia.php">Compra x día</a></li>
                        <li><a class="dropdown-item" href="../reportes/compra_por_fecha.php">Compra x fecha</a></li>
                        <?php
                        if (tienePermiso('ver')) {
                            echo '
                        <li><a class="dropdown-item" href="../reportes/Ventas_categoria.php">Ventas x categoría</a></li>';
                        }
                        ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Control</a>
                    <ul class="dropdown-menu">
                        <?php
                        if (tienePermiso('ver')) {
                            echo '
                        <li><a class="dropdown-item" href="../control/compras.php">Compras</a></li>';
                        }
                        ?>
                        <li><a class="dropdown-item" href="../control/gastos.php">Gastos</a></li>
                        <li><a class="dropdown-item" href="../control/entradas.php">Entradas</a></li>
                        <li><a class="dropdown-item" href="../control/respaldo.php">Respaldo</a></li>
                        <li><a class="dropdown-item" href="../control/cierre_dia.php">Cierre de día</a></li>
                        <li><a class="dropdown-item" href="../control/inicio_dia.php">Inicio de día</a></li>
                    </ul>
                </li>
                <?php
                if (tienePermiso('ver')) {
                    echo ' 
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Admin y sistemas</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../admin/derivados.php">Derivados</a></li>
                        <li><a class="dropdown-item" href="../admin/act_sistema.php">Actualizar sistema</a></li>
                        <li><a class="dropdown-item" href="../admin/actualiza_info.php">Actualiza inf. del servidor</a></li>
                        <li><a class="dropdown-item" href="../admin/importa_ventas_matriz.php">Importar Ventas</a></li>
                    </ul>
                </li>';
                }
                ?>
                <li class="nav-item">
                    <a class="nav-link disabled"><?php echo $_SESSION["desc_sucursal"]; ?></a>
                </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-lg-0 ml-auto">
                <li class="nav-item dropdown" >
                    <a href="#" class="dropdown-toggle nav-link" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle "></i> <?php echo $_SESSION["nombre"]; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <?php
                        if (tienePermiso('ver')) {
                            echo ' 
                        <li><a href="../login/cambio_sucursal.php" class="dropdown-item"><i class="bi bi-building"></i> Cambiar sucursal</a></li>';
                        }
                        ?>

                        <li><a href="#" class="dropdown-item"><i class="bi bi-lock-fill"></i> Cambiar Contraseña</a></li>
                        <?php
                        if (tienePermiso('ver')) {
                            echo ' 
                        <li><a href="../usuarios/usuarios.php" class="dropdown-item"><i class="bi bi-gear-fill"></i> Usuarios</a></li>';
                        }
                        ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a href="../login/logout.php" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>