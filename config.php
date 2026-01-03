<?php
// Conexión PDO con manejo de errores
try {
    $pdo = new PDO("mysql:host=localhost;dbname=cashflow_db;charset=utf8mb4", "root", "");
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

// Filtro de año para la gráfica (por defecto todos)
$anio_grafica = $_GET['anio_grafica'] ?? 'todos';

// Datos para la gráfica mensual - Patrimonio total por periodo
// Para cada fecha de captura, suma el último saldo de cada cuenta en ese momento
$condicion_anio = $anio_grafica === 'todos' ? 'YEAR(fecha_captura) >= 2023' : 'YEAR(fecha_captura) = ' . intval($anio_grafica);

$grafica_sql = $pdo->query("
    SELECT 
        DATE_FORMAT(p.fecha_ref, '%b %y') as mes,
        p.fecha_ref as periodo,
        SUM(s1.monto) as total
    FROM (
        SELECT DISTINCT fecha_captura as fecha_ref
        FROM saldos 
        WHERE $condicion_anio
    ) p
    INNER JOIN saldos s1 ON s1.id = (
        SELECT s2.id
        FROM saldos s2
        WHERE s2.cuenta_id = s1.cuenta_id 
        AND s2.fecha_captura = (
            SELECT MAX(s3.fecha_captura)
            FROM saldos s3
            WHERE s3.cuenta_id = s1.cuenta_id
            AND s3.fecha_captura <= p.fecha_ref
        )
        LIMIT 1
    )
    GROUP BY p.fecha_ref
    ORDER BY p.fecha_ref ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Calcular mínimo, máximo, inicial y final de la gráfica
$totales = array_column($grafica_sql, 'total');
$min_grafica = !empty($totales) ? min($totales) : 0;
$max_grafica = !empty($totales) ? max($totales) : 0;
$inicial_grafica = !empty($totales) ? $totales[0] : 0;
$final_grafica = !empty($totales) ? end($totales) : 0;
$variacion_grafica = $inicial_grafica > 0 ? (($final_grafica - $inicial_grafica) / $inicial_grafica) * 100 : 0;

// Calcular gasto promedio del periodo seleccionado
$gasto_promedio = 0;
if (count($grafica_sql) > 1) {
    $diferencia_total = $final_grafica - $inicial_grafica;
    $fecha_inicial = new DateTime($grafica_sql[0]['periodo']);
    $fecha_final = new DateTime(end($grafica_sql)['periodo']);
    $dias_periodo = $fecha_inicial->diff($fecha_final)->days;
    $gasto_promedio = $dias_periodo > 0 ? $diferencia_total / $dias_periodo : 0;
}

// Obtener mes y año seleccionado (por defecto el actual)
$mes_filtro = $_GET['mes'] ?? date('m');
$anio_filtro = $_GET['anio'] ?? date('Y');

// Consulta filtrada para la tabla
$stmt_tabla = $pdo->prepare("
    SELECT s.id, c.nombre as cuenta, s.monto, s.fecha_captura, c.tipo 
    FROM saldos s
    JOIN cuentas c ON s.cuenta_id = c.id
    WHERE MONTH(s.fecha_captura) = ? AND YEAR(s.fecha_captura) = ?
    ORDER BY s.fecha_captura DESC
");
$stmt_tabla->execute([$mes_filtro, $anio_filtro]);
$registros_tabla = $stmt_tabla->fetchAll(PDO::FETCH_ASSOC);
?>