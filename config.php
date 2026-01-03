<?php
// Conexión PDO con manejo de errores
try {
    $pdo = new PDO("mysql:host=linkercard.com;dbname=linkewjr_cashflow_db;charset=utf8mb4", "roolinkewjr_casht", "Papa$2025.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// 1. Agregar nueva cuenta al catálogo
if (isset($_POST['action']) && $_POST['action'] == 'nueva_cuenta') {
    try {
        $stmt = $pdo->prepare("INSERT INTO cuentas (nombre, tipo) VALUES (?, ?)");
        $stmt->execute([$_POST['nombre_cuenta'], $_POST['tipo_cuenta']]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        error_log("Error al agregar cuenta: " . $e->getMessage());
    }
}

// 2. Capturar saldo de una cuenta
if (isset($_POST['action']) && $_POST['action'] == 'nuevo_saldo') {
    try {
        $stmt = $pdo->prepare("INSERT INTO saldos (cuenta_id, monto, fecha_captura) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['cuenta_id'], $_POST['monto'], $_POST['fecha']]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        error_log("Error al guardar saldo: " . $e->getMessage());
    }
}

// --- CONSULTAS PARA EL DASHBOARD ---

// Obtener todas las cuentas para el catálogo
$cuentas = $pdo->query("SELECT * FROM cuentas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

// Monto Total Actual (Suma del último registro de cada cuenta)
$total_actual = $pdo->query("
    SELECT SUM(monto) FROM saldos s 
    WHERE s.fecha_captura = (SELECT MAX(fecha_captura) FROM saldos WHERE cuenta_id = s.cuenta_id)
")->fetchColumn() ?: 0;

// Gasto Diario y Diferencia de Días (Comparando las últimas dos fechas generales de captura)
$fechas_totales = $pdo->query("
    SELECT fecha_captura, SUM(monto) as total 
    FROM saldos 
    GROUP BY fecha_captura 
    ORDER BY fecha_captura DESC LIMIT 2
")->fetchAll();

$dias_dif = 0; $gasto_diario = 0;
if (count($fechas_totales) > 1) {
    $d1 = new DateTime($fechas_totales[0]['fecha_captura']);
    $d2 = new DateTime($fechas_totales[1]['fecha_captura']);
    $dias_dif = $d1->diff($d2)->days;
    $gasto_diario = ($fechas_totales[0]['total'] - $fechas_totales[1]['total']) / ($dias_dif ?: 1);
}

// Datos para la gráfica mensual
$grafica_sql = $pdo->query("
    SELECT DATE_FORMAT(fecha_captura, '%b') as mes, SUM(monto) as total 
    FROM saldos 
    GROUP BY MONTH(fecha_captura) 
    ORDER BY fecha_captura ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>