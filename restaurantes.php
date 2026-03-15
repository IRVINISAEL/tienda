<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$accion = $_GET['accion'] ?? '';

// ── Obtener nombre por ID ──
if ($accion === 'nombre') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false]); exit; }
    $stmt = db()->prepare("SELECT nombre FROM restaurantes WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    echo json_encode($r ? ['ok' => true, 'nombre' => $r['nombre']] : ['ok' => false]);
    exit;
}

// ── Buscar restaurante por nombre ──
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
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
    exit;
}

// ── Obtener puntuación de un restaurante ──
if ($accion === 'puntuacion') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false]); exit; }

    $stmt = db()->prepare(
        "SELECT ROUND(AVG(estrellas), 1) AS promedio, COUNT(*) AS total
         FROM calificaciones WHERE restaurante_id = ?"
    );
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    echo json_encode([
        'ok'       => true,
        'promedio' => $data['promedio'] ?? 0,
        'total'    => (int)($data['total'] ?? 0)
    ]);
    exit;
}

// ── Calificar restaurante ──
if ($accion === 'calificar') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $rid      = (int)($body['restaurante_id'] ?? 0);
    $token    = trim($body['token'] ?? '');
    $estrellas = (int)($body['estrellas'] ?? 0);
    $comentario = trim($body['comentario'] ?? '');

    if (!$rid || !$token || $estrellas < 1 || $estrellas > 5) {
        echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos']); exit;
    }

    try {
        db()->prepare(
            "INSERT INTO calificaciones (restaurante_id, token_sesion, estrellas, comentario)
             VALUES (?, ?, ?, ?)"
        )->execute([$rid, $token, $estrellas, $comentario ?: null]);

        echo json_encode(['ok' => true, 'mensaje' => '¡Gracias por tu calificación!']);
    } catch (Exception $e) {
        // UNIQUE KEY violation = ya calificó
        echo json_encode(['ok' => false, 'mensaje' => 'Ya calificaste este restaurante en esta visita']);
    }
    exit;
}

echo json_encode(['ok' => false, 'mensaje' => 'Acción no reconocida']);