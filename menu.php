<?php
$allowedOrigins = ['http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// DEBUG TEMPORAL - borrar después
error_log("SESSION en menu.php: " . json_encode($_SESSION));
error_log("admin_id: " . ($_SESSION['admin_id'] ?? 'NO EXISTE'));

$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($accion) {

    case 'lista':
        header('Cache-Control: public, max-age=60');
        $restaurante_id = null;

        // Prioridad 1: admin en sesión
        if (!empty($_SESSION['admin'])) {
            $restaurante_id = $_SESSION['admin']['restaurante_id'];
        }
        // Prioridad 2: rid desde URL (clientes en order.html)
        if (!$restaurante_id && !empty($_GET['rid'])) {
            $restaurante_id = (int)$_GET['rid'];
        }

        if ($restaurante_id) {
            $stmt = db()->prepare("SELECT * FROM menu WHERE restaurante_id = ? AND disponible = 1 ORDER BY categoria, nombre");
            $stmt->execute([$restaurante_id]);
        } else {
            $stmt = db()->prepare("SELECT * FROM menu WHERE disponible = 1 ORDER BY categoria, nombre");
            $stmt->execute();
        }
        responder(true, $stmt->fetchAll());

    case 'crear':
        if (empty($_SESSION['admin_id'])) responder(false, null, 'No autorizado');
        $admin = $_SESSION['admin'];
        $nombre = trim($body['nombre'] ?? '');
        if (!$nombre) responder(false, null, 'Nombre requerido');

        $stmt = db()->prepare(
            "INSERT INTO menu (restaurante_id, nombre, descripcion, precio, categoria, emoji, imagen, disponible) VALUES (?,?,?,?,?,?,?,1)"
        );
        $stmt->execute([
            $admin['restaurante_id'],
            $nombre,
            $body['descripcion'] ?? '',
            (float)($body['precio'] ?? 0),
            $body['categoria'] ?? 'comida',
            $body['emoji'] ?? '🍽️',
            $body['imagen'] ?? null,
        ]);
        responder(true, ['id' => db()->lastInsertId()], 'Platillo creado');

    case 'toggle':
        if (empty($_SESSION['admin_id'])) responder(false, null, 'No autorizado');
        $id    = (int)($body['id'] ?? 0);
        $disp  = $body['disponible'] ? 1 : 0;
        $ridT  = (int)$_SESSION['admin']['restaurante_id'];
        db()->prepare("UPDATE menu SET disponible = ? WHERE id = ? AND restaurante_id = ?")
        ->execute([$disp, $id, $ridT]);
        responder(true, null, 'Actualizado');

    case 'eliminar':
        if (empty($_SESSION['admin_id'])) responder(false, null, 'No autorizado');
        $id   = (int)($body['id'] ?? 0);
        $ridE = (int)$_SESSION['admin']['restaurante_id'];
        db()->prepare("DELETE FROM menu WHERE id = ? AND restaurante_id = ?")
        ->execute([$id, $ridE]);
        responder(true, null, 'Eliminado');

    case 'subir_imagen':
        if (empty($_SESSION['admin_id'])) responder(false, null, 'No autorizado');
        if (empty($_FILES['imagen'])) responder(false, null, 'No se recibió imagen');

        // Verificar MIME type real del archivo (no solo el nombre)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($_FILES['imagen']['tmp_name']);
        $mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mimeReal, $mimesPermitidos)) {
            responder(false, null, 'Formato no permitido');
        }

        // Extensión basada en MIME real, no en el nombre del cliente
        $extensiones = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $ext = $extensiones[$mimeReal];

        $dir = __DIR__ . '/uploads/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Nombre aleatorio seguro, nunca el nombre original del cliente
        $nombreArchivo = 'plato_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['imagen']['tmp_name'], $dir . $nombreArchivo);
        responder(true, ['url' => 'uploads/' . $nombreArchivo]);
        break;

    default:
        responder(false, null, 'Acción no reconocida');
}