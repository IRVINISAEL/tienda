<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$accion = $_GET['accion'] ?? '';

if ($accion === 'nombre') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false]); exit; }
    $stmt = db()->prepare("SELECT nombre FROM restaurantes WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    echo json_encode($r ? ['ok' => true, 'nombre' => $r['nombre']] : ['ok' => false]);
    exit;
}

if ($accion === 'buscar') {
    $nombre = trim($_GET['nombre'] ?? '');
    if (!$nombre) { echo json_encode(['ok' => false, 'data' => []]); exit; }

    $stmt = db()->prepare(
        "SELECT id, nombre, tipo, direccion 
         FROM restaurantes 
         WHERE nombre LIKE ? AND activo = 1 
         ORDER BY nombre ASC 
         LIMIT 5"
    );
    $stmt->execute(['%' . $nombre . '%']);
    $resultados = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'data' => $resultados]);
    exit;
}

echo json_encode(['ok' => false, 'mensaje' => 'Acción no reconocida']);