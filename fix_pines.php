<?php
require_once __DIR__ . '/config.php';

$stmt = db()->query("SELECT id, pin FROM empleados");
$empleados = $stmt->fetchAll();

foreach ($empleados as $emp) {
    if (strpos($emp['pin'], '$2y$') !== 0) {
        $hash = password_hash($emp['pin'], PASSWORD_DEFAULT);
        db()->prepare("UPDATE empleados SET pin = ? WHERE id = ?")
             ->execute([$hash, $emp['id']]);
        echo "PIN actualizado para empleado ID " . $emp['id'] . "<br>";
    }
}

echo "✅ Listo";