<link rel="stylesheet" href="css/bootstrap-icons.css">
<nav class="navbar navbar-expand-lg bg-light rounded" aria-label="Eleventh navbar example">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Carnicería Cano</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample09" aria-controls="navbarsExample09" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarsExample09">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="../index.php">Inicio</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Mantenimientos</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../productos.php">Productos</a></li>
                        <li><a class="dropdown-item" href="../categorias.php">Categorías</a></li>
                        <li><a class="dropdown-item" href="../sucursales.php">Sucursales</a></li>
                        <li><a class="dropdown-item" href="../clientes.php">Clientes</a></li>
                        <li><a class="dropdown-item" href="../empleados.php">Empleados</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Reportes</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../reportes/reporte_dia.php">Reporte x día</a></li>
                        <li><a class="dropdown-item" href="../reportes/por_fecha.php">Por fecha</a></li>
                        <li><a class="dropdown-item" href="../reportes/cierre_dia.php">Cierre de día</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">Control</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../derivados.php">Derivados</a></li>
                        <li><a class="dropdown-item" href="../agrupados.php">Agrupados</a></li>
                        <li><a class="dropdown-item" href="../gastos.php">Gastos</a></li>
                        <li><a class="dropdown-item" href="../gastos.php">Entradas</a></li>
                        <li><a class="dropdown-item" href="../respaldo.php">Respaldo</a></li>
                    </ul>
                </li>
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
                        <li><a href="../cambio_sucursal.php" class="dropdown-item"><i class="bi bi-building"></i> Cambiar sucursal</a></li>
                        <li><a href="#" class="dropdown-item"><i class="bi bi-lock-fill"></i> Cambiar Contraseña</a></li>
                        <li><a href="usuarios.php" class="dropdown-item"><i class="bi bi-gear-fill"></i> Usuarios</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a href="../logout.php" class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>