<?php
require_once 'config.php';
verificarAdmin();
$admin = obtenerAdminActual();

// Obtener parámetros de búsqueda
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$marca = isset($_GET['marca']) ? intval($_GET['marca']) : 0;
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$stock = isset($_GET['stock']) ? $_GET['stock'] : '';

// Construir consulta base con JOIN
$sql = "SELECT p.*, c.nombre as categoria_nombre, m.nombre as marca_nombre 
        FROM productos p
        JOIN categorias c ON p.categoria_id = c.categoria_id
        JOIN marcas m ON p.marca_id = m.marca_id
        WHERE 1=1";

// Aplicar filtros
if (!empty($search)) {
    $sql .= " AND (p.nombre LIKE '%$search%' OR p.descripcion LIKE '%$search%' OR p.modelo LIKE '%$search%')";
}

if ($categoria > 0) {
    $sql .= " AND p.categoria_id = $categoria";
}

if ($marca > 0) {
    $sql .= " AND p.marca_id = $marca";
}

if ($estado === 'activo') {
    $sql .= " AND p.activo = TRUE";
} elseif ($estado === 'inactivo') {
    $sql .= " AND p.activo = FALSE";
}

if ($stock === 'bajo') {
    $sql .= " AND p.stock < 10";
} elseif ($stock === 'agotado') {
    $sql .= " AND p.stock = 0";
} elseif ($stock === 'disponible') {
    $sql .= " AND p.stock > 0";
}

$sql .= " ORDER BY p.fecha_creacion DESC";
$result = $conn->query($sql);

// Obtener categorías y marcas para filtros
$categorias = $conn->query("SELECT * FROM categorias ORDER BY nombre");
$marcas = $conn->query("SELECT * FROM marcas ORDER BY nombre");

// Estadísticas
$total_productos = $result->num_rows;
$sql_activos = "SELECT COUNT(*) as total FROM productos WHERE activo = TRUE";
$activos = $conn->query($sql_activos)->fetch_assoc();

$sql_stock = "SELECT SUM(stock) as total FROM productos WHERE activo = TRUE";
$stock_total = $conn->query($sql_stock)->fetch_assoc();

$sql_bajo = "SELECT COUNT(*) as total FROM productos WHERE stock < 10 AND activo = TRUE";
$stock_bajo = $conn->query($sql_bajo)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - SAFI Electrónicos</title>
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
            background: linear-gradient(135deg, #e25412ff 0%, #b44902ff 100%);
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
            padding: 12px 12px;
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
            padding: 8px 10px;
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
            white-space: nowrap;
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
        
        /* BOTÓN NUEVO PRODUCTO */
        .btn-new {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
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
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
        
        /* FILTROS DE BÚSQUEDA */
        .search-filters {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
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
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-search {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        
        .btn-clear {
            background: #95a5a6;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }
        
        /* ESTADÍSTICAS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        /* PRODUCTOS */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            height: 180px;
            overflow: hidden;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-content {
            padding: 20px;
        }
        
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .product-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .product-category {
            background: #e8f4fc;
            color: #3498db;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .product-brand {
            background: #e8f6f3;
            color: #27ae60;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .product-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            min-height: 60px;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .product-price {
            font-size: 22px;
            font-weight: bold;
            color: #2ecc71;
        }
        
        .product-stock {
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-view {
            background: #9b59b6;
            color: white;
        }
        
        .btn-view:hover {
            background: #8e44ad;
        }
        
        /* SIN PRODUCTOS */
        .no-products {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .no-products h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .no-products p {
            color: #7f8c8d;
            margin-bottom: 25px;
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
        
        /* BADGES DE ESTADO */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .badge-warning {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #e74c3c;
        }
        
        .badge-secondary {
            background: #e9ecef;
            color: #6c757d;
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
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .top-navbar {
                padding: 0 15px;
            }
            
            .navbar-logo img {
                height: 35px;
            }
            
            .brand-name {
                font-size: 16px;
            }
            
            .navbar-menu {
                display: none;
            }
            
            .navbar-user {
                gap: 10px;
            }
            
            .logout-btn {
                padding: 8px 15px;
                font-size: 13px;
            }
            
            .main-content {
                margin-top: 70px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .product-actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .top-navbar {
                padding: 0 10px;
            }
            
            .brand-name {
                font-size: 14px;
            }
            
            .user-name {
                font-size: 12px;
            }
            
            .logout-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
                    echo '<div style="font-size: 24px;">🏪</div>';
                }
            ?>
        </div>
        
        <!-- MENÚ DE NAVEGACIÓN -->
        <nav class="navbar-menu">
            <a href="admin_dashboard.php">
                <span>📊</span> Inicio
            </a>
            <a href="admin_productos.php" class="active">
                <span>📱</span> Productos
            </a>
            <a href="admin_pedidos.php">
                <span>📦</span> Pedidos
            </a>
            <a href="admin_clientes.php">
                <span>👥</span> Clientes
            </a>
            <a href="admin_empleados.php">
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
                <div class="user-role">Administrador</div>
            </div>
            
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <span></span> Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
    
    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">
        <!-- HEADER DE LA PÁGINA -->
        <div class="page-header">
            <h1>📱 Gestión de Productos <span class="admin-badge">Administrador</span></h1>
            <a href="admin_nuevo_producto.php" class="btn-new">
                <span>➕</span> Nuevo Producto
            </a>
        </div>
        
        <!-- FILTROS DE BÚSQUEDA -->
        <div class="search-filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>🔍 Buscar Producto</label>
                        <input type="text" placeholder="Nombre, descripción, modelo..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label>📂 Categoría</label>
                        <select name="categoria">
                            <option value="">Todas las categorías</option>
                            <?php while($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['categoria_id']; ?>" <?php echo $categoria == $cat['categoria_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>🏷️ Marca</label>
                        <select name="marca">
                            <option value="">Todas las marcas</option>
                            <?php while($marca_item = $marcas->fetch_assoc()): ?>
                            <option value="<?php echo $marca_item['marca_id']; ?>" <?php echo $marca == $marca_item['marca_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($marca_item['nombre']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label>📊 Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="activo" <?php echo $estado == 'activo' ? 'selected' : ''; ?>>Activos</option>
                            <option value="inactivo" <?php echo $estado == 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>📦 Stock</label>
                        <select name="stock">
                            <option value="">Todos</option>
                            <option value="bajo" <?php echo $stock == 'bajo' ? 'selected' : ''; ?>>Stock Bajo (< 10)</option>
                            <option value="agotado" <?php echo $stock == 'agotado' ? 'selected' : ''; ?>>Agotados (0)</option>
                            <option value="disponible" <?php echo $stock == 'disponible' ? 'selected' : ''; ?>>Disponible (> 0)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn-search">
                                <span>🔍</span> Buscar
                            </button>
                            <a href="admin_productos.php" class="btn-clear">
                                <span>🗑️</span> Limpiar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- ESTADÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📱</div>
                <h3>Total Productos</h3>
                <div class="number"><?php echo $total_productos; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <h3>Productos Activos</h3>
                <div class="number"><?php echo $activos['total']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <h3>Stock Total</h3>
                <div class="number"><?php echo $stock_total['total'] ?: 0; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <h3>Stock Bajo</h3>
                <div class="number"><?php echo $stock_bajo['total']; ?></div>
            </div>
        </div>
        
        <!-- PRODUCTOS -->
        <div class="products-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while($producto = $result->fetch_assoc()): 
                    // SISTEMA DE IMÁGENES:
                    // 1. Primero intenta cargar la imagen local
                    $imagen_path = 'img/productos/' . $producto['producto_id'] . '.jpg';
                    
                    // Si no existe localmente, usa un placeholder
                    if (!file_exists($imagen_path)) {
                        // Puedes usar un placeholder online o uno local
                        $imagen_path = 'https://via.placeholder.com/400x200?text=' . urlencode(substr($producto['nombre'], 0, 20));
                        // O también puedes usar: $imagen_path = 'img/default_product.jpg';
                    }
                    
                    // Determinar clase del badge según stock
                    $stock_class = 'badge-success';
                    $stock_text = 'Disponible';
                    
                    if ($producto['stock'] < 10 && $producto['stock'] > 0) {
                        $stock_class = 'badge-warning';
                        $stock_text = 'Stock Bajo';
                    } elseif ($producto['stock'] == 0) {
                        $stock_class = 'badge-danger';
                        $stock_text = 'Agotado';
                    }
                    
                    // Estado del producto
                    $estado_class = $producto['activo'] ? 'badge-success' : 'badge-secondary';
                    $estado_text = $producto['activo'] ? 'Activo' : 'Inactivo';
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?php echo $imagen_path; ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                             onerror="this.src='https://via.placeholder.com/400x200?text=Imagen+No+Disponible'">
                    </div>
                    <div class="product-content">
                        <h3 class="product-title"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                        
                        <div class="product-meta">
                            <span class="product-category"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span>
                            <span class="product-brand"><?php echo htmlspecialchars($producto['marca_nombre']); ?></span>
                            <?php if ($producto['modelo']): ?>
                                <span style="background: #f8f9fa; color: #666; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                    <?php echo htmlspecialchars($producto['modelo']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="product-description">
                            <?php echo htmlspecialchars(substr($producto['descripcion'] ?? 'Sin descripción disponible', 0, 100)); ?>...
                        </p>
                        
                        <div class="product-footer">
                            <div class="product-price">$<?php echo number_format($producto['precio'], 2); ?></div>
                            <div class="product-stock">
                                <span class="<?php echo $stock_class; ?>"><?php echo $producto['stock']; ?> unidades</span>
                                <span class="<?php echo $estado_class; ?>" style="margin-left: 8px;"><?php echo $estado_text; ?></span>
                            </div>
                        </div>
                        
                        <div class="product-actions">
                            <a href="admin_editar_producto.php?id=<?php echo $producto['producto_id']; ?>" class="btn-action btn-edit">
                                <span>✏️</span> Editar
                            </a>
                            <a href="admin_eliminar_producto.php?id=<?php echo $producto['producto_id']; ?>" class="btn-action btn-delete" onclick="return confirm('¿Estás seguro de eliminar este producto?')">
                                <span>🗑️</span> Eliminar
                            </a>
                            <a href="admin_ver_producto.php?id=<?php echo $producto['producto_id']; ?>" class="btn-action btn-view">
                                <span>👁️</span> Ver
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-products">
                    <h3>📦 No se encontraron productos</h3>
                    <p><?php echo empty($search) && $categoria == 0 && $marca == 0 ? 'Comienza agregando tu primer producto al sistema' : 'No hay productos que coincidan con los filtros aplicados'; ?></p>
                    <a href="admin_nuevo_producto.php" class="btn-new">
                        <span>➕</span> Agregar Producto
                    </a>
                    <?php if (!empty($search) || $categoria > 0 || $marca > 0): ?>
                        <a href="admin_productos.php" style="display: inline-block; margin-top: 15px; color: #3498db; text-decoration: none;">
                            🗑️ Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>