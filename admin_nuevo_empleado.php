<?php
require_once 'config.php';
verificarAdmin();
$admin = obtenerAdminActual();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $primer_apellido = trim($_POST['primer_apellido']);
    $segundo_apellido = trim($_POST['segundo_apellido']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $telefono = trim($_POST['telefono']);
    $puesto = trim($_POST['puesto']);
    $rol = $_POST['rol'];
    $es_responsable = isset($_POST['es_responsable']) ? 1 : 0;
    $fecha_contratacion = $_POST['fecha_contratacion'];
    $salario = floatval($_POST['salario']);
    
    // Validaciones
    if (empty($nombre) || empty($primer_apellido) || empty($email) || empty($password) || empty($puesto)) {
        $error = "Por favor complete todos los campos requeridos";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email no válido";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {
        // Verificar si el email ya existe
        $sql_check = "SELECT empleado_id FROM empleados WHERE email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "El email ya está registrado";
        } else {
            // Hash de contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar empleado
            $sql = "INSERT INTO empleados (nombre, primer_apellido, segundo_apellido, email, password, telefono, puesto, rol, es_responsable, fecha_contratacion, salario) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssss", $nombre, $primer_apellido, $segundo_apellido, $email, $password_hash, $telefono, $puesto, $rol, $es_responsable, $fecha_contratacion, $salario);
            
            if ($stmt->execute()) {
                $success = "Empleado creado exitosamente";
                // Limpiar campos
                $_POST = array();
            } else {
                $error = "Error al crear el empleado: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Empleado - Admin SAFI ELECTRÓNICOS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            min-height: 100vh;
            padding: 20px;
        }
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .btn-admin {
            background: #e74c3c;
            border-color: #e74c3c;
            color: white;
        }
        .btn-admin:hover {
            background: #c0392b;
            border-color: #c0392b;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="form-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0"><i class="bi bi-person-plus me-2"></i> Nuevo Empleado</h2>
                        <a href="admin_empleados.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i> Volver
                        </a>
                    </div>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <!-- Información Personal -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre" value="<?php echo $_POST['nombre'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Primer Apellido *</label>
                                <input type="text" class="form-control" name="primer_apellido" value="<?php echo $_POST['primer_apellido'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Segundo Apellido</label>
                                <input type="text" class="form-control" name="segundo_apellido" value="<?php echo $_POST['segundo_apellido'] ?? ''; ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña *</label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" value="<?php echo $_POST['telefono'] ?? ''; ?>">
                            </div>
                            
                            <!-- Información Laboral -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Puesto *</label>
                                <input type="text" class="form-control" name="puesto" value="<?php echo $_POST['puesto'] ?? ''; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol *</label>
                                <select class="form-select" name="rol" required>
                                    <option value="EMPLEADO GENERAL" <?php echo ($_POST['rol'] ?? '') == 'EMPLEADO GENERAL' ? 'selected' : ''; ?>>Empleado General</option>
                                    <option value="EMPLEADO ENCARGADO" <?php echo ($_POST['rol'] ?? '') == 'EMPLEADO ENCARGADO' ? 'selected' : ''; ?>>Empleado Encargado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Contratación *</label>
                                <input type="date" class="form-control" name="fecha_contratacion" value="<?php echo $_POST['fecha_contratacion'] ?? date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Salario ($) *</label>
                                <input type="number" step="0.01" class="form-control" name="salario" value="<?php echo $_POST['salario'] ?? '0'; ?>" required>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="es_responsable" id="es_responsable" value="1" <?php echo isset($_POST['es_responsable']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="es_responsable">
                                        Es responsable de productos
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-secondary me-md-2">
                                <i class="bi bi-x-circle me-2"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-admin">
                                <i class="bi bi-save me-2"></i> Guardar Empleado
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>