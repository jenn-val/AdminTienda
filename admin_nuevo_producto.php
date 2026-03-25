<?php
require_once 'config.php';
verificarLogin();
$empleado = obtenerEmpleadoActual();

// Obtener categorías y marcas para los select
$categorias = $conn->query("SELECT * FROM categorias ORDER BY nombre");
$marcas = $conn->query("SELECT * FROM marcas ORDER BY nombre");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $categoria_id = intval($_POST['categoria_id']);
    $marca_id = intval($_POST['marca_id']);
    $modelo = trim($_POST['modelo']);
    $especificaciones = trim($_POST['especificaciones'] ?? '');
    
    // Validaciones básicas
    if (empty($nombre) || $precio <= 0 || $categoria_id <= 0 || $marca_id <= 0) {
        $error = "Por favor complete todos los campos requeridos correctamente";
    } else {
        // Preparar JSON para especificaciones
        $especificaciones_json = NULL;
        if (!empty($especificaciones)) {
            $especificaciones_json = json_encode(['especificaciones' => $especificaciones]);
        }
        
        $sql = "INSERT INTO productos (nombre, descripcion, precio, stock, categoria_id, marca_id, modelo, especificaciones_tecnicas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdiisss", $nombre, $descripcion, $precio, $stock, $categoria_id, $marca_id, $modelo, $especificaciones_json);
        
        if ($stmt->execute()) {
            $producto_id = $stmt->insert_id;
            
            // Procesar imagen principal si se subió
            if (isset($_FILES['imagen_principal']) && $_FILES['imagen_principal']['error'] === 0) {
                procesarImagenProducto($producto_id, $_FILES['imagen_principal'], 'principal');
            }
            
            // Procesar imágenes adicionales si existen
            if (!empty($_FILES['imagenes_adicionales']['name'][0])) {
                procesarImagenesAdicionales($producto_id, $_FILES['imagenes_adicionales']);
            }
            
            header("Location: productos.php?success=agregado&id=" . $producto_id);
            exit();
        } else {
            $error = "Error al guardar el producto: " . $conn->error;
        }
    }
}

// Función para procesar imagen del producto
function procesarImagenProducto($producto_id, $imagen, $tipo = 'principal') {
    $extension = strtolower(pathinfo($imagen['name'], PATHINFO_EXTENSION));
    
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
        $directorio = '../uploads/productos/' . $producto_id . '/';
        if (!file_exists($directorio)) {
            mkdir($directorio, 0777, true);
        }
        
        $nombre_archivo = $tipo . '_' . time() . '.' . $extension;
        $destino = $directorio . $nombre_archivo;
        
        if (move_uploaded_file($imagen['tmp_name'], $destino)) {
            // Crear miniatura
            crearMiniatura($destino, $directorio . 'thumb_' . $nombre_archivo, 300, 300);
            return true;
        }
    }
    return false;
}

// Función para procesar imágenes adicionales
function procesarImagenesAdicionales($producto_id, $imagenes) {
    global $conn;
    
    // Crear tabla temporal si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS imagenes_productos (
        imagen_id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT NOT NULL,
        ruta_imagen VARCHAR(255) NOT NULL,
        tipo VARCHAR(20) DEFAULT 'adicional',
        orden INT DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (producto_id) REFERENCES productos(producto_id) ON DELETE CASCADE
    )");
    
    $orden = 1;
    for ($i = 0; $i < count($imagenes['name']); $i++) {
        if ($imagenes['error'][$i] === 0) {
            $extension = strtolower(pathinfo($imagenes['name'][$i], PATHINFO_EXTENSION));
            
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                $directorio = '../uploads/productos/' . $producto_id . '/';
                if (!file_exists($directorio)) {
                    mkdir($directorio, 0777, true);
                }
                
                $nombre_archivo = 'adicional_' . $orden . '_' . time() . '.' . $extension;
                $destino = $directorio . $nombre_archivo;
                
                if (move_uploaded_file($imagenes['tmp_name'][$i], $destino)) {
                    // Guardar en base de datos
                    $ruta_relativa = 'uploads/productos/' . $producto_id . '/' . $nombre_archivo;
                    
                    $sql = "INSERT INTO imagenes_productos (producto_id, ruta_imagen, orden) 
                            VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isi", $producto_id, $ruta_relativa, $orden);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Crear miniatura
                    crearMiniatura($destino, $directorio . 'thumb_' . $nombre_archivo, 300, 300);
                    
                    $orden++;
                }
            }
        }
    }
}

// Función para crear miniatura
function crearMiniatura($src, $dest, $width, $height) {
    if (!file_exists($src)) return false;
    
    list($src_width, $src_height, $type) = getimagesize($src);
    
    // Determinar tipo de imagen
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($src);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($src);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($src);
            break;
        default:
            return false;
    }
    
    // Calcular proporciones
    $ratio = min($width/$src_width, $height/$src_height);
    $new_width = ceil($src_width * $ratio);
    $new_height = ceil($src_height * $ratio);
    
    // Crear nueva imagen
    $thumb = imagecreatetruecolor($new_width, $new_height);
    
    // Preservar transparencia para PNG
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Redimensionar
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height);
    
    // Guardar
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumb, $dest, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumb, $dest);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumb, $dest, 85);
            break;
    }
    
    // Liberar memoria
    imagedestroy($image);
    imagedestroy($thumb);
    
    return true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto - Tienda Electrónica</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-orange: #FF6B35;
            --secondary-orange: #FF9E6D;
            --dark-orange: #E55A2B;
            --light-orange: #FFF3EB;
            --accent-orange: #FFA500;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --dark-color: #333333;
            --border-color: #e0e0e0;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        
        .form-container {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 1000px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin: 20px 0;
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .form-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.3;
        }
        
        .form-header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .form-header p {
            opacity: 0.9;
            font-size: 16px;
            position: relative;
        }
        
        .form-header-icon {
            font-size: 60px;
            margin-bottom: 20px;
            display: block;
            position: relative;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .form-body {
            padding: 40px;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-orange);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateY(-5px);
        }
        
        .section-title {
            color: var(--dark-orange);
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-orange);
        }
        
        .section-title i {
            background: var(--light-orange);
            padding: 10px;
            border-radius: 10px;
            color: var(--primary-orange);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 600;
            font-size: 14px;
        }
        
        .required::after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.15);
        }
        
        .input-group {
            display: flex;
            align-items: center;
        }
        
        .input-group .prefix {
            background: var(--light-orange);
            padding: 14px 15px;
            border: 2px solid var(--border-color);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: var(--dark-orange);
            font-weight: 600;
        }
        
        .input-group input {
            border-radius: 0 12px 12px 0;
        }
        
        /* Área de subida de imágenes */
        .upload-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .upload-section {
                grid-template-columns: 1fr;
            }
        }
        
        .upload-area {
            border: 3px dashed var(--secondary-orange);
            border-radius: 15px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--light-orange);
            color: var(--dark-orange);
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .upload-area:hover {
            background: linear-gradient(135deg, var(--light-orange), #ffe8d9);
            border-color: var(--primary-orange);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.15);
        }
        
        .upload-area i {
            font-size: 50px;
            margin-bottom: 15px;
            color: var(--primary-orange);
        }
        
        .upload-area h4 {
            margin-bottom: 10px;
            color: var(--dark-orange);
        }
        
        .upload-area p {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            min-height: 60px;
        }
        
        .image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid var(--light-orange);
            transition: all 0.3s;
            position: relative;
        }
        
        .image-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1;
        }
        
        .preview-img-container {
            position: relative;
            display: inline-block;
        }
        
        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            z-index: 2;
        }
        
        .remove-image:hover {
            background: #c82333;
            transform: scale(1.2);
        }
        
        .preview-main-image {
            max-width: 250px;
            max-height: 250px;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid var(--primary-orange);
            padding: 5px;
            background: white;
        }
        
        /* Mensajes de error/éxito */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: var(--danger-color);
            border: 2px solid #f5c6cb;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: var(--success-color);
            border: 2px solid #c3e6cb;
        }
        
        /* Botones */
        .form-actions {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid var(--light-orange);
        }
        
        .btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            letter-spacing: 0.5px;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
            color: white;
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }
        
        .btn-save:hover {
            background: linear-gradient(135deg, var(--dark-orange), var(--primary-orange));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
        }
        
        .btn-save:active {
            transform: translateY(-1px);
        }
        
        .btn-cancel {
            background: white;
            color: var(--dark-orange);
            border: 3px solid var(--secondary-orange);
        }
        
        .btn-cancel:hover {
            background: var(--secondary-orange);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 158, 109, 0.3);
        }
        
        .btn-icon {
            font-size: 18px;
        }
        
        /* Información de ayuda */
        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            display: block;
        }
        
        .info-box {
            background: var(--light-orange);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid var(--accent-orange);
        }
        
        .info-box h5 {
            color: var(--dark-orange);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box ul {
            padding-left: 20px;
            color: #666;
        }
        
        .info-box li {
            margin-bottom: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-body {
                padding: 25px 20px;
            }
            
            .form-header {
                padding: 25px 20px;
            }
            
            .form-header h1 {
                font-size: 26px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .upload-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .form-container {
                border-radius: 15px;
            }
            
            .form-header h1 {
                font-size: 22px;
            }
            
            .form-header p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <span class="form-header-icon">📦</span>
            <h1>➕ NUEVO PRODUCTO</h1>
            <p>Complete el formulario para agregar un nuevo producto al catálogo</p>
        </div>
        
        <div class="form-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                <!-- Sección 1: Información Básica -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Información Básica del Producto
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Nombre del Producto</label>
                            <input type="text" name="nombre" required 
                                   placeholder="Ej: iPhone 15 Pro Max" 
                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                            <span class="help-text">Máximo 50 caracteres</span>
                        </div>
                        
                        <div class="form-group">
                            <label>Modelo</label>
                            <input type="text" name="modelo" 
                                   placeholder="Ej: A2849, X1-2024"
                                   value="<?php echo isset($_POST['modelo']) ? htmlspecialchars($_POST['modelo']) : ''; ?>">
                            <span class="help-text">Número de modelo o SKU</span>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Categoría</label>
                            <select name="categoria_id" required>
                                <option value="">Seleccione una categoría</option>
                                <?php while ($categoria = $categorias->fetch_assoc()): ?>
                                    <option value="<?php echo $categoria['categoria_id']; ?>"
                                        <?php echo (isset($_POST['categoria_id']) && $_POST['categoria_id'] == $categoria['categoria_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Marca</label>
                            <select name="marca_id" required>
                                <option value="">Seleccione una marca</option>
                                <?php while ($marca = $marcas->fetch_assoc()): ?>
                                    <option value="<?php echo $marca['marca_id']; ?>"
                                        <?php echo (isset($_POST['marca_id']) && $_POST['marca_id'] == $marca['marca_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($marca['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Sección 2: Precio y Stock -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-dollar-sign"></i>
                        Precio y Stock
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Precio</label>
                            <div class="input-group">
                                <span class="prefix">$</span>
                                <input type="number" name="precio" step="0.01" min="0.01" required 
                                       placeholder="0.00"
                                       value="<?php echo isset($_POST['precio']) ? htmlspecialchars($_POST['precio']) : ''; ?>">
                            </div>
                            <span class="help-text">Precio de venta al público</span>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Stock Disponible</label>
                            <input type="number" name="stock" min="0" required 
                                   placeholder="0"
                                   value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0'; ?>">
                            <span class="help-text">Cantidad disponible en inventario</span>
                        </div>
                    </div>
                </div>
                
                <!-- Sección 3: Imágenes -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-images"></i>
                        Imágenes del Producto
                    </h3>
                    
                    <div class="upload-section">
                        <!-- Imagen principal -->
                        <div>
                            <h4 style="color: var(--dark-orange); margin-bottom: 15px;">Imagen Principal</h4>
                            <div class="upload-area" id="uploadMainArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Arrastre o haga clic aquí</h4>
                                <p>Formato: JPG, PNG, WEBP</p>
                                <p>Tamaño máximo: 5MB</p>
                                <input type="file" name="imagen_principal" id="imagenPrincipal" accept="image/*" class="d-none">
                            </div>
                            <div class="image-preview-container" id="mainImagePreview">
                                <!-- Vista previa de imagen principal aparecerá aquí -->
                            </div>
                        </div>
                        
                        <!-- Imágenes adicionales -->
                        <div>
                            <h4 style="color: var(--dark-orange); margin-bottom: 15px;">Imágenes Adicionales</h4>
                            <div class="upload-area" id="uploadAdditionalArea">
                                <i class="fas fa-layer-group"></i>
                                <h4>Arrastre o haga clic aquí</h4>
                                <p>Máximo 5 imágenes</p>
                                <p>Tamaño máximo: 5MB cada una</p>
                                <input type="file" name="imagenes_adicionales[]" id="imagenesAdicionales" 
                                       accept="image/*" multiple class="d-none">
                            </div>
                            <div class="image-preview-container" id="additionalImagesPreview">
                                <!-- Vistas previas de imágenes adicionales aparecerán aquí -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sección 4: Descripción -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-align-left"></i>
                        Descripción y Especificaciones
                    </h3>
                    
                    <div class="form-group full-width">
                        <label>Descripción del Producto</label>
                        <textarea name="descripcion" rows="4" 
                                  placeholder="Describa las características principales del producto..."><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
                        <span class="help-text">Descripción detallada para los clientes</span>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Especificaciones Técnicas</label>
                        <textarea name="especificaciones" rows="6"
                                  placeholder="Ingrese las especificaciones técnicas (una por línea)..."><?php echo isset($_POST['especificaciones']) ? htmlspecialchars($_POST['especificaciones']) : ''; ?></textarea>
                        <span class="help-text">Ej: Pantalla: 6.7 pulgadas, RAM: 8GB, Almacenamiento: 256GB</span>
                    </div>
                </div>
                
                <!-- Información de ayuda -->
                <div class="info-box">
                    <h5><i class="fas fa-lightbulb"></i> Recomendaciones</h5>
                    <ul>
                        <li>Use imágenes de alta calidad con fondo blanco o neutro</li>
                        <li>Complete todas las especificaciones técnicas disponibles</li>
                        <li>Verifique que el precio y stock sean correctos antes de guardar</li>
                        <li>La imagen principal será la que se muestre en el catálogo</li>
                    </ul>
                </div>
                
                <!-- Botones de acción -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-save" id="submitBtn">
                        <i class="fas fa-save btn-icon"></i>
                        <span>Guardar Producto</span>
                    </button>
                    <a href="productos.php" class="btn btn-cancel">
                        <i class="fas fa-times btn-icon"></i>
                        <span>Cancelar</span>
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Variables globales
        let mainImageFile = null;
        let additionalImageFiles = [];
        
        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Animación de entrada
            const formContainer = document.querySelector('.form-container');
            formContainer.style.opacity = '0';
            formContainer.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                formContainer.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                formContainer.style.opacity = '1';
                formContainer.style.transform = 'translateY(0)';
            }, 100);
            
            // Configurar áreas de subida
            setupUploadAreas();
            
            // Configurar validaciones
            setupValidations();
        });
        
        // Configurar áreas de subida de imágenes
        function setupUploadAreas() {
            // Imagen principal
            const uploadMainArea = document.getElementById('uploadMainArea');
            const imagenPrincipalInput = document.getElementById('imagenPrincipal');
            const mainImagePreview = document.getElementById('mainImagePreview');
            
            uploadMainArea.addEventListener('click', () => imagenPrincipalInput.click());
            uploadMainArea.addEventListener('dragover', (e) => handleDragOver(e, uploadMainArea));
            uploadMainArea.addEventListener('dragleave', () => handleDragLeave(uploadMainArea));
            uploadMainArea.addEventListener('drop', (e) => handleImageDrop(e, 'main'));
            
            imagenPrincipalInput.addEventListener('change', (e) => handleMainImageSelect(e));
            
            // Imágenes adicionales
            const uploadAdditionalArea = document.getElementById('uploadAdditionalArea');
            const imagenesAdicionalesInput = document.getElementById('imagenesAdicionales');
            const additionalImagesPreview = document.getElementById('additionalImagesPreview');
            
            uploadAdditionalArea.addEventListener('click', () => imagenesAdicionalesInput.click());
            uploadAdditionalArea.addEventListener('dragover', (e) => handleDragOver(e, uploadAdditionalArea));
            uploadAdditionalArea.addEventListener('dragleave', () => handleDragLeave(uploadAdditionalArea));
            uploadAdditionalArea.addEventListener('drop', (e) => handleImageDrop(e, 'additional'));
            
            imagenesAdicionalesInput.addEventListener('change', (e) => handleAdditionalImagesSelect(e));
        }
        
        // Manejar arrastrar sobre
        function handleDragOver(e, element) {
            e.preventDefault();
            element.style.background = 'linear-gradient(135deg, var(--light-orange), #ffe8d9)';
            element.style.borderColor = 'var(--primary-orange)';
            element.style.transform = 'translateY(-5px)';
        }
        
        // Manejar salida del arrastre
        function handleDragLeave(element) {
            element.style.background = 'var(--light-orange)';
            element.style.borderColor = 'var(--secondary-orange)';
            element.style.transform = 'translateY(0)';
        }
        
        // Manejar soltar imagen
        function handleImageDrop(e, type) {
            e.preventDefault();
            handleDragLeave(e.currentTarget);
            
            const files = e.dataTransfer.files;
            if (type === 'main') {
                handleMainImageSelect({ target: { files: [files[0]] } });
            } else {
                handleAdditionalImagesSelect({ target: { files: files } });
            }
        }
        
        // Manejar selección de imagen principal
        function handleMainImageSelect(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validar tamaño (5MB máximo)
            if (file.size > 5 * 1024 * 1024) {
                showAlert('error', 'La imagen principal no debe superar los 5MB');
                return;
            }
            
            // Validar tipo
            if (!file.type.match('image.*')) {
                showAlert('error', 'Por favor seleccione una imagen válida');
                return;
            }
            
            mainImageFile = file;
            previewMainImage(file);
        }
        
        // Vista previa de imagen principal
        function previewMainImage(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewContainer = document.getElementById('mainImagePreview');
                previewContainer.innerHTML = `
                    <div class="preview-img-container">
                        <img src="${e.target.result}" class="preview-main-image" alt="Vista previa">
                        <button type="button" class="remove-image" onclick="removeMainImage()">
                            <i class="fas fa-times"></i>
                        </button>
                        <div style="margin-top: 10px; text-align: center;">
                            <small style="color: #666;">${file.name} (${formatFileSize(file.size)})</small>
                        </div>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }
        
        // Remover imagen principal
        function removeMainImage() {
            mainImageFile = null;
            document.getElementById('imagenPrincipal').value = '';
            document.getElementById('mainImagePreview').innerHTML = '';
        }
        
        // Manejar selección de imágenes adicionales
        function handleAdditionalImagesSelect(e) {
            const files = Array.from(e.target.files);
            
            // Validar cantidad máxima (5 imágenes)
            const currentCount = additionalImageFiles.length;
            if (currentCount + files.length > 5) {
                showAlert('error', 'Máximo 5 imágenes adicionales permitidas');
                return;
            }
            
            // Validar cada archivo
            files.forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    showAlert('error', `La imagen "${file.name}" supera los 5MB`);
                    return;
                }
                
                if (!file.type.match('image.*')) {
                    showAlert('error', `"${file.name}" no es una imagen válida`);
                    return;
                }
                
                additionalImageFiles.push(file);
                previewAdditionalImage(file);
            });
            
            // Actualizar input file
            updateAdditionalImagesInput();
        }
        
        // Vista previa de imágenes adicionales
        function previewAdditionalImage(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewContainer = document.getElementById('additionalImagesPreview');
                const index = additionalImageFiles.length - 1;
                
                const previewDiv = document.createElement('div');
                previewDiv.className = 'preview-img-container';
                previewDiv.innerHTML = `
                    <img src="${e.target.result}" class="image-preview" alt="Vista previa ${index + 1}">
                    <button type="button" class="remove-image" onclick="removeAdditionalImage(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                previewContainer.appendChild(previewDiv);
            };
            reader.readAsDataURL(file);
        }
        
        // Remover imagen adicional
        function removeAdditionalImage(index) {
            additionalImageFiles.splice(index, 1);
            updateAdditionalImagesInput();
            refreshAdditionalImagesPreview();
        }
        
        // Actualizar input de imágenes adicionales
        function updateAdditionalImagesInput() {
            // No podemos actualizar el input file directamente por seguridad
            // En su lugar, manejaremos esto en el backend
            const input = document.getElementById('imagenesAdicionales');
            const dt = new DataTransfer();
            
            additionalImageFiles.forEach(file => {
                dt.items.add(file);
            });
            
            input.files = dt.files;
        }
        
        // Refrescar vista previa de imágenes adicionales
        function refreshAdditionalImagesPreview() {
            const previewContainer = document.getElementById('additionalImagesPreview');
            previewContainer.innerHTML = '';
            
            additionalImageFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'preview-img-container';
                    previewDiv.innerHTML = `
                        <img src="${e.target.result}" class="image-preview" alt="Vista previa ${index + 1}">
                        <button type="button" class="remove-image" onclick="removeAdditionalImage(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    previewContainer.appendChild(previewDiv);
                };
                reader.readAsDataURL(file);
            });
        }
        
        // Configurar validaciones
        function setupValidations() {
            const form = document.getElementById('productForm');
            const nombreInput = form.querySelector('input[name="nombre"]');
            const precioInput = form.querySelector('input[name="precio"]');
            const stockInput = form.querySelector('input[name="stock"]');
            const categoriaSelect = form.querySelector('select[name="categoria_id"]');
            const marcaSelect = form.querySelector('select[name="marca_id"]');
            
            // Validación en tiempo real
            const inputs = [nombreInput, precioInput, stockInput, categoriaSelect, marcaSelect];
            
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    clearFieldError(this);
                });
            });
            
            // Validar precio positivo
            precioInput.addEventListener('change', function() {
                if (parseFloat(this.value) <= 0) {
                    showFieldError(this, 'El precio debe ser mayor a 0');
                }
            });
            
            // Validar stock no negativo
            stockInput.addEventListener('change', function() {
                if (parseInt(this.value) < 0) {
                    showFieldError(this, 'El stock no puede ser negativo');
                }
            });
            
            // Validación al enviar formulario
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                const errors = [];
                
                // Validar campos requeridos
                if (!nombreInput.value.trim()) {
                    isValid = false;
                    showFieldError(nombreInput, 'El nombre es obligatorio');
                    errors.push('Nombre del producto');
                }
                
                if (!precioInput.value || parseFloat(precioInput.value) <= 0) {
                    isValid = false;
                    showFieldError(precioInput, 'Precio inválido');
                    errors.push('Precio');
                }
                
                if (!stockInput.value || parseInt(stockInput.value) < 0) {
                    isValid = false;
                    showFieldError(stockInput, 'Stock inválido');
                    errors.push('Stock');
                }
                
                if (!categoriaSelect.value) {
                    isValid = false;
                    showFieldError(categoriaSelect, 'Seleccione una categoría');
                    errors.push('Categoría');
                }
                
                if (!marcaSelect.value) {
                    isValid = false;
                    showFieldError(marcaSelect, 'Seleccione una marca');
                    errors.push('Marca');
                }
                
                if (!isValid) {
                    showAlert('error', 'Por favor complete los siguientes campos:<br>' + 
                            errors.map(err => `• ${err}`).join('<br>'));
                    return false;
                }
                
                // Confirmar antes de enviar
                confirmSubmit();
            });
        }
        
        // Validar campo individual
        function validateField(field) {
            if (field.tagName === 'SELECT') {
                if (!field.value) {
                    showFieldError(field, 'Este campo es requerido');
                    return false;
                }
            } else if (field.type === 'number') {
                if (!field.value || parseFloat(field.value) <= 0) {
                    showFieldError(field, 'Valor inválido');
                    return false;
                }
            } else {
                if (!field.value.trim()) {
                    showFieldError(field, 'Este campo es requerido');
                    return false;
                }
            }
            
            clearFieldError(field);
            return true;
        }
        
        // Mostrar error en campo
        function showFieldError(field, message) {
            clearFieldError(field);
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.innerHTML = `<small style="color: var(--danger-color);">${message}</small>`;
            errorDiv.style.marginTop = '5px';
            errorDiv.style.animation = 'slideDown 0.3s ease';
            
            field.parentNode.appendChild(errorDiv);
            field.style.borderColor = 'var(--danger-color)';
            field.style.boxShadow = '0 0 0 4px rgba(220, 53, 69, 0.1)';
        }
        
        // Limpiar error de campo
        function clearFieldError(field) {
            const existingError = field.parentNode.querySelector('.field-error');
            if (existingError) {
                existingError.remove();
            }
            field.style.borderColor = '';
            field.style.boxShadow = '';
        }
        
        // Mostrar alerta
        function showAlert(type, message) {
            // Remover alertas existentes
            const existingAlert = document.querySelector('.alert:not(.alert-error):not(.alert-success)');
            if (existingAlert) existingAlert.remove();
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i>
                <span>${message}</span>
            `;
            
            const formBody = document.querySelector('.form-body');
            formBody.insertBefore(alertDiv, formBody.firstChild);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alertDiv.remove(), 500);
                }
            }, 5000);
        }
        
        // Confirmar envío
        function confirmSubmit() {
            // Crear modal de confirmación
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
                animation: fadeIn 0.3s ease;
            `;
            
            const modalContent = `
                <div style="background: white; border-radius: 15px; padding: 30px; max-width: 500px; width: 90%;">
                    <h3 style="color: var(--dark-orange); margin-bottom: 15px;">
                        <i class="fas fa-question-circle"></i> Confirmar
                    </h3>
                    <p style="margin-bottom: 25px; color: #666;">
                        ¿Está seguro de que desea crear este nuevo producto?
                    </p>
                    <div style="display: flex; gap: 15px;">
                        <button id="confirmYes" style="
                            flex: 1;
                            padding: 12px;
                            background: linear-gradient(135deg, var(--primary-orange), var(--dark-orange));
                            color: white;
                            border: none;
                            border-radius: 10px;
                            font-weight: 600;
                            cursor: pointer;
                        ">
                            <i class="fas fa-check"></i> Sí, crear producto
                        </button>
                        <button id="confirmNo" style="
                            flex: 1;
                            padding: 12px;
                            background: #f8f9fa;
                            color: #666;
                            border: 2px solid #ddd;
                            border-radius: 10px;
                            font-weight: 600;
                            cursor: pointer;
                        ">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            `;
            
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            // Configurar botones
            document.getElementById('confirmYes').addEventListener('click', () => {
                // Mostrar indicador de carga
                const submitBtn = document.getElementById('submitBtn');
                const originalContent = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                submitBtn.disabled = true;
                
                // Enviar formulario
                setTimeout(() => {
                    document.getElementById('productForm').submit();
                }, 500);
                
                modal.remove();
            });
            
            document.getElementById('confirmNo').addEventListener('click', () => {
                modal.remove();
            });
            
            // Cerrar al hacer clic fuera
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        // Funciones auxiliares
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Animación CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            .fa-spin {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .d-none {
                display: none !important;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>