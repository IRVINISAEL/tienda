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

echo json_encode(['ok' => false, 'mensaje' => 'Acción no reconocida']);