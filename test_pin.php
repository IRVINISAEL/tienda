<?php
require_once __DIR__ . '/config.php';

$stmt = db()->prepare("SELECT * FROM empleados WHERE nombre = 'susan'");
$stmt->execute();
$emp = $stmt->fetch();

echo "PIN guardado: " . $emp['pin'] . "<br>";
echo "Verificando 7845: ";
echo password_verify('7845', $emp['pin']) ? '✅ Correcto' : '❌ Incorrecto';