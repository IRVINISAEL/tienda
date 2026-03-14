<?php
// ============================================================
//  pedidos.php — API REST de Pedidos
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$allowedOrigins = ['http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Responder preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$accion  = $_GET['accion'] ?? '';
$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?? [];

// Token del body disponible para verificarCliente
$GLOBALS['_token_body'] = $body['token'] ?? '';

switch ($accion) {

    // --------------------------------------------------------
    // REGISTRAR CLIENTE (viene del QR)
    // --------------------------------------------------------
    case 'registrar_cliente':
        if ($method !== 'POST') responder(false, null, 'Método inválido');

        $nombre = trim($body['nombre'] ?? '');
        $tipo   = $body['tipo'] ?? 'mesa';       // 'mesa' o 'para_llevar'
        $mesa   = (int)($body['mesa'] ?? 0);

        if (!$nombre) responder(false, null, 'El nombre es requerido');
        if ($tipo === 'mesa' && $mesa < 1) responder(false, null, 'Número de mesa inválido');

        $token = generarToken();
        $db = db();
        $stmt = $db->prepare(
            "INSERT INTO usuarios (nombre, telefono, tipo, mesa_numero, token_sesion)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $nombre,
            $body['telefono'] ?? null,
            $tipo,
            $tipo === 'mesa' ? $mesa : null,
            $token
        ]);
        $userId = $db->lastInsertId();

        responder(true, [
            'token'   => $token,
            'usuario' => ['id' => $userId, 'nombre' => $nombre, 'tipo' => $tipo, 'mesa' => $mesa]
        ], "¡Bienvenido, $nombre!");

    // --------------------------------------------------------
    // CREAR PEDIDO
    // --------------------------------------------------------
    case 'crear_pedido':
        if ($method !== 'POST') responder(false, null, 'Método inválido');

        $usuario  = verificarCliente();
        $items    = $body['items'] ?? [];
        $notas    = trim($body['notas'] ?? '');

        if (empty($items)) responder(false, null, 'El pedido está vacío');

        $db     = db();
        $numero = siguienteNumeroOrden();
        $total  = 0;

        // Verificar precios del menú
        $ids = array_column($items, 'menu_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $menuStmt = $db->prepare("SELECT * FROM menu WHERE id IN ($placeholders) AND disponible = 1");
        $menuStmt->execute($ids);
        $menuItems = [];
        foreach ($menuStmt->fetchAll() as $m) $menuItems[$m['id']] = $m;

        foreach ($items as $item) {
            if (!isset($menuItems[$item['menu_id']])) responder(false, null, 'Ítem no disponible');
            $total += $menuItems[$item['menu_id']]['precio'] * $item['cantidad'];
        }

        // Insertar pedido
        $db->prepare(
            "INSERT INTO pedidos (usuario_id, numero_orden, tipo, mesa_numero, total, notas)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $usuario['id'], $numero, $usuario['tipo'],
            $usuario['mesa_numero'], $total, $notas
        ]);
        $pedidoId = $db->lastInsertId();

        // Insertar ítems
        $insItem = $db->prepare(
            "INSERT INTO pedido_items (pedido_id, menu_id, cantidad, precio_unit, subtotal)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($items as $item) {
            $precio   = $menuItems[$item['menu_id']]['precio'];
            $subtotal = $precio * $item['cantidad'];
            $insItem->execute([$pedidoId, $item['menu_id'], $item['cantidad'], $precio, $subtotal]);
        }

        $pedido = obtenerPedidoCompleto($pedidoId);
        responder(true, $pedido, "Pedido #$numero enviado");

    // --------------------------------------------------------
    // MIS PEDIDOS (cliente)
    // --------------------------------------------------------
    case 'mis_pedidos':
        $usuario = verificarCliente();
        $stmt = db()->prepare(
            "SELECT p.*, GROUP_CONCAT(CONCAT(pi.cantidad,'x ',m.nombre) SEPARATOR ', ') AS resumen
             FROM pedidos p
             JOIN pedido_items pi ON pi.pedido_id = p.id
             JOIN menu m ON m.id = pi.menu_id
             WHERE p.usuario_id = ?
             GROUP BY p.id ORDER BY p.creado_en DESC LIMIT 10"
        );
        $stmt->execute([$usuario['id']]);
        responder(true, $stmt->fetchAll());

    // --------------------------------------------------------
    // TODOS LOS PEDIDOS (admin)
    // --------------------------------------------------------
    case 'todos_pedidos':
        verificarAdmin();
        $fecha  = $_GET['fecha'] ?? date('Y-m-d');
        $estado = $_GET['estado'] ?? '';

        $sql  = "SELECT p.*, u.nombre AS cliente, u.telefono,
                        GROUP_CONCAT(CONCAT(pi.cantidad,'x ',m.nombre,' ($',pi.precio_unit,')') SEPARATOR '\n') AS items_texto,
                        SUM(pi.cantidad) AS total_items
                 FROM pedidos p
                 JOIN usuarios u ON u.id = p.usuario_id
                 JOIN pedido_items pi ON pi.pedido_id = p.id
                 JOIN menu m ON m.id = pi.menu_id
                 WHERE DATE(p.creado_en) = ?";
        $params = [$fecha];
        if ($estado) { $sql .= " AND p.estado = ?"; $params[] = $estado; }
        $sql .= " GROUP BY p.id ORDER BY p.creado_en ASC";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        responder(true, $stmt->fetchAll());

    // --------------------------------------------------------
    // ACTUALIZAR ESTADO (admin)
    // --------------------------------------------------------
    case 'actualizar_estado':
        verificarAdmin();
        if ($method !== 'PUT') responder(false, null, 'Método inválido');

        $pedidoId = (int)($body['pedido_id'] ?? 0);
        $estado   = $body['estado'] ?? '';
        $estados  = ['pendiente','en_preparacion','listo','entregado','cancelado'];

        if (!$pedidoId || !in_array($estado, $estados)) responder(false, null, 'Datos inválidos');

        db()->prepare("UPDATE pedidos SET estado = ? WHERE id = ?")->execute([$estado, $pedidoId]);
        responder(true, null, 'Estado actualizado');

    // --------------------------------------------------------
    // CORTE DE CAJA
    // --------------------------------------------------------
    case 'corte_caja':
        $admin = verificarAdmin();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $db    = db();

        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_pedidos,
                SUM(CASE WHEN tipo='mesa' THEN total ELSE 0 END) AS total_mesa,
                SUM(CASE WHEN tipo='para_llevar' THEN total ELSE 0 END) AS total_llevar,
                SUM(total) AS gran_total
             FROM pedidos
             WHERE DATE(creado_en) = ? AND estado != 'cancelado'"
        );
        $stmt->execute([$fecha]);
        $resumen = $stmt->fetch();

        // Detalle completo
        $detalle = $db->prepare(
            "SELECT p.numero_orden, p.tipo, p.mesa_numero, p.total, p.estado,
                    u.nombre AS cliente, p.creado_en,
                    GROUP_CONCAT(CONCAT(pi.cantidad,'x ',m.nombre) SEPARATOR ', ') AS items
             FROM pedidos p
             JOIN usuarios u ON u.id = p.usuario_id
             JOIN pedido_items pi ON pi.pedido_id = p.id
             JOIN menu m ON m.id = pi.menu_id
             WHERE DATE(p.creado_en) = ? AND p.estado != 'cancelado'
             GROUP BY p.id ORDER BY p.numero_orden ASC"
        );
        $detalle->execute([$fecha]);

        // Top vendidos
        $top = $db->prepare(
            "SELECT m.nombre, m.emoji, SUM(pi.cantidad) AS vendidos, SUM(pi.subtotal) AS ingresos
             FROM pedido_items pi
             JOIN pedidos p ON p.id = pi.pedido_id
             JOIN menu m ON m.id = pi.menu_id
             WHERE DATE(p.creado_en) = ? AND p.estado != 'cancelado'
             GROUP BY pi.menu_id ORDER BY vendidos DESC LIMIT 5"
        );
        $top->execute([$fecha]);

        responder(true, [
            'fecha'   => $fecha,
            'resumen' => $resumen,
            'pedidos' => $detalle->fetchAll(),
            'top'     => $top->fetchAll()
        ]);

    default:
        responder(false, null, 'Acción no reconocida');
}

// ---- Helper ----
function obtenerPedidoCompleto(int $id): array {
    $db = db();
    $stmt = $db->prepare(
        "SELECT p.*, u.nombre AS cliente, u.tipo AS cliente_tipo
         FROM pedidos p
         JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return [];
    $stmtI = $db->prepare(
        "SELECT pi.menu_id, m.nombre, m.emoji, pi.cantidad, pi.precio_unit AS precio, pi.subtotal
         FROM pedido_items pi
         JOIN menu m ON m.id = pi.menu_id
         WHERE pi.pedido_id = ?"
    );
    $stmtI->execute([$id]);
    $row['items'] = $stmtI->fetchAll();
    return $row;
}