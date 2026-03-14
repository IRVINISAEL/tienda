<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Protección contra registro masivo — rate limit por IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
verificarIntentos($ip);

$data = json_decode(file_get_contents('php://input'), true);

$nombre     = trim($data['nombre'] ?? '');
$tipo       = trim($data['tipo'] ?? '');
$direccion  = trim($data['direccion'] ?? '');
$descripcion= trim($data['descripcion'] ?? '');
$mesas      = intval($data['mesas'] ?? 10);
$moneda     = trim($data['moneda'] ?? 'MXN');
$llevar     = intval($data['acepta_llevar'] ?? 1);
$efectivo   = intval($data['acepta_efectivo'] ?? 1);
$anombre    = trim($data['admin_nombre'] ?? '');
$aapellido  = trim($data['admin_apellido'] ?? '');
$telefono   = trim($data['telefono'] ?? '');
$email      = trim($data['email'] ?? '');
$usuario    = trim($data['usuario'] ?? '');
$password   = trim($data['password'] ?? '');

if (!$nombre || !$usuario || !$password) {
    echo json_encode(['ok' => false, 'mensaje' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $db = db();

    // Verificar si el usuario ya existe
    $stmt = $db->prepare("SELECT id FROM administradores WHERE usuario = ?");
    $stmt->execute([$usuario]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'mensaje' => 'Ese usuario ya está en uso']);
        exit;
    }

    $token = bin2hex(random_bytes(16));

    // Insertar restaurante
    $stmt = $db->prepare("INSERT INTO restaurantes (nombre, tipo, direccion, descripcion, mesas, moneda, acepta_llevar, acepta_efectivo, token) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$nombre, $tipo, $direccion, $descripcion, $mesas, $moneda, $llevar, $efectivo, $token]);
    $restaurante_id = $db->lastInsertId();

    // Insertar administrador
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO administradores (restaurante_id, nombre, apellido, telefono, email, usuario, password) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$restaurante_id, $anombre, $aapellido, $telefono, $email, $usuario, $hash]);

    // Limpiar intentos tras registro exitoso
    limpiarIntentos($ip);
    echo json_encode(['ok' => true, 'token' => $token, 'restaurante_id' => $restaurante_id]);

} catch (Exception $e) {
    // Registrar intento fallido
    registrarIntentoFallido($ip);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
}