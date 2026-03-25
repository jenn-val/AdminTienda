<?php
$servername = "localhost";
$username = "root"; // por defecto en XAMPP
$password = "";     // vacío por defecto
$database = "tienda"; // pon aquí el nombre exacto

$conexion = new mysqli($servername, $username, $password, $database);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
// echo "Conectado correctamente";
?>