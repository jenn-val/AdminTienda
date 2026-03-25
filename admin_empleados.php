<?php
require_once 'config.php';
verificarAdmin();
$admin = obtenerAdminActual();

// Obtener empleados
$sql = "SELECT * FROM empleados ORDER BY fecha_creacion DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Admin SAFI ELECTRÓNICOS</title>
    <style>
        /* ESTILOS GENERALES */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        /* BARRA SUPERIOR */
        .top-navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .navbar-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .navbar-logo img {
            height: 45px;
            width: auto;
        }
        
        .brand-name {
            font-size: 20px;
            font-weight: 600;
            color: white;
        }
        
        /* MENÚ HORIZONTAL */
        .navbar-menu {
            display: flex;
            gap: 2px;
            flex: 1;
            justify-content: center;
        }
        
        .navbar-menu a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .navbar-menu a.active {
            background: #e74c3c;
            color: white;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* USUARIO Y CERRAR SESIÓN */
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 500;
            font-size: 14px;
            color: white;
        }
        
        .user-role {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* CONTENIDO PRINCIPAL */
        .main-content {
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        
        /* HEADER DE PÁGINA */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        
        /* BOTÓN NUEVO EMPLEADO */
        .btn-new {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        /* EMPLEADOS */
        .employees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .employee-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .employee-card:hover {
            transform: translateY(-5px);
        }
        
        .employee-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .employee-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .employee-info h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .employee-info p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .employee-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .employee-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #e74c3c;
        }
        
        .employee-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-action {
            flex: 1;
            padding: 10px;
            border-radius: 6px;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2980b9;
        }
        
        .btn-status {
            background: #2ecc71;
            color: white;
        }
        
        .btn-status:hover {
            background: #27ae60;
        }
        
        .btn-password {
            background: #9b59b6;
            color: white;
        }
        
        .btn-password:hover {
            background: #8e44ad;
        }
        
        /* SIN EMPLEADOS */
        .no-employees {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .no-employees h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .no-employees p {
            color: #7f8c8d;
            margin-bottom: 25px;
        }
        
        /* FILTROS */
        .search-filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        /* ADMIN BADGE */
        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .navbar-menu {
                gap: 1px;
            }
            
            .navbar-menu a {
                padding: 10px 15px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 768px) {
            .top-navbar {
                padding: 0 15px;
                height: 60px;
            }
            
            .navbar-logo img {
                height: 35px;
            }
            
            .brand-name {
                display: none;
            }
            
            .navbar-menu {
                display: none;
            }
            
            .main-content {
                margin-top: 60px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .employees-grid {
                grid-template-columns: 1fr;
            }
            
            .employee-actions {
                flex-direction: column;
            }
            
            .filter-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- BARRA SUPERIOR -->
    <div class="top-navbar">
        <!-- LOGO Y NOMBRE -->
        <div class="navbar-logo">
            <?php
                $logo_paths = [
                    'assets/logo.png',
                    'img/logo.png',
                    'logo.png',
                    'Img/logo.png',
                    '../Img/logo.png'
                ];
                
                $logo_found = false;
                foreach ($logo_paths as $path) {
                    if (file_exists($path)) {
                        echo '<img src="' . $path . '" alt="Logo Tienda Electrónica">';
                        $logo_found = true;
                        break;
                    }
                }
                
                if (!$logo_found) {
                    echo '<div class="logo-fallback">🏪</div>';
                }
            ?>
            <span class="brand-name">SAFI ELECTRÓNICOS</span>
        </div>
        
        <!-- MENÚ DE NAVEGACIÓN -->
        <nav class="navbar-menu">
            <a href="admin_dashboard.php">
                <span>📊</span> Dashboard
            </a>
            <a href="admin_productos.php">
                <span>📱</span> Productos
            </a>
            <a href="admin_pedidos.php">
                <span>📦</span> Pedidos
            </a>
            <a href="admin_clientes.php">
                <span>👥</span> Clientes
            </a>
            <a href="admin_empleados.php" class="active">
                <span>👔</span> Empleados
            </a>
            <a href="admin_inventario.php">
                <span>📋</span> Inventario
            </a>
            <a href="admin_proveedores.php">
                <span>🏢</span> Proveedores
            </a>
            <a href="admin_reportes.php">
                <span>📈</span> Reportes
            </a>
        </nav>
        
        <!-- USUARIO Y CERRAR SESIÓN -->
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($admin['nombre'] . ' ' . $admin['primer_apellido']); ?></div>
                <div class="user-role">Administrador <span class="admin-badge">Admin</span></div>
            </div>
            
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <span>🚪</span> Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
    
    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">
        <!-- HEADER DE LA PÁGINA -->
        <div class="page-header">
            <h1>👔 Gestión de Empleados <span class="admin-badge">Administrador</span></h1>
            <a href="admin_nuevo_empleado.php" class="btn-new">
                <span>➕</span> Nuevo Empleado
            </a>
        </div>
        
        <!-- FILTROS DE BÚSQUEDA -->
        <div class="search-filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Buscar Empleado</label>
                        <input type="text" placeholder="Nombre, email..." name="search">
                    </div>
                    <div class="filter-group">
                        <label>Puesto</label>
                        <select name="puesto">
                            <option value="">Todos los puestos</option>
                            <option value="Vendedor">Vendedor</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Almacén">Almacén</option>
                            <option value="Atención a Clientes">Atención a Clientes</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Rol</label>
                        <select name="rol">
                            <option value="">Todos los roles</option>
                            <option value="EMPLEADO GENERAL">Empleado General</option>
                            <option value="EMPLEADO ENCARGADO">Empleado Encargado</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" style="background: #3498db; color: white; padding: 10px 25px; border: none; border-radius: 8px; cursor: pointer; width: 100%;">
                            🔍 Buscar Empleados
                        </button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <a href="admin_empleados.php" style="background: #95a5a6; color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: block; text-align: center;">
                            🗑️ Limpiar Filtros
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- EMPLEADOS -->
        <div class="employees-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($empleado = $result->fetch_assoc()): 
                    $iniciales = substr($empleado['nombre'], 0, 1) . substr($empleado['primer_apellido'], 0, 1);
                    $status_class = $empleado['activo'] ? 'status-active' : 'status-inactive';
                    $status_text = $empleado['activo'] ? 'Activo' : 'Inactivo';
                ?>
                <div class="employee-card">
                    <div class="employee-header">
                        <div class="employee-avatar">
                            <?php echo strtoupper($iniciales); ?>
                        </div>
                        <div class="employee-info">
                            <h3><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['primer_apellido']); ?></h3>
                            <p><?php echo htmlspecialchars($empleado['puesto']); ?></p>
                            <span class="employee-status <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="employee-details">
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($empleado['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Teléfono:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($empleado['telefono'] ?? 'No especificado'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Rol:</span>
                            <span class="detail-value">
                                <?php 
                                if ($empleado['rol'] == 'EMPLEADO ENCARGADO') {
                                    echo 'Encargado';
                                } else {
                                    echo 'General';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Contratación:</span>
                            <span class="detail-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Salario:</span>
                            <span class="detail-value">$<?php echo number_format($empleado['salario'], 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="employee-actions">
                        <a href="admin_editar_empleado.php?id=<?php echo $empleado['empleado_id']; ?>" class="btn-action btn-edit">
                            <span>✏️</span> Editar
                        </a>
                        <?php if ($empleado['activo']): ?>
                            <a href="admin_desactivar_empleado.php?id=<?php echo $empleado['empleado_id']; ?>" class="btn-action btn-status" onclick="return confirm('¿Desactivar este empleado?')">
                                <span>❌</span> Desactivar
                            </a>
                        <?php else: ?>
                            <a href="admin_activar_empleado.php?id=<?php echo $empleado['empleado_id']; ?>" class="btn-action btn-status" onclick="return confirm('¿Activar este empleado?')">
                                <span>✅</span> Activar
                            </a>
                        <?php endif; ?>
                        <a href="admin_reset_password.php?id=<?php echo $empleado['empleado_id']; ?>" class="btn-action btn-password" onclick="return confirm('¿Restablecer contraseña del empleado?')">
                            <span>🔑</span> Contraseña
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-employees">
                    <h3>👥 No hay empleados registrados</h3>
                    <p>Comienza agregando tu primer empleado al sistema</p>
                    <a href="admin_nuevo_empleado.php" class="btn-new">
                        <span>➕</span> Agregar Primer Empleado
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>