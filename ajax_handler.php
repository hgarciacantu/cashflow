<?php
require_once 'credentials.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Error de conexión']));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'grafica_patrimonio':
        $anio_grafica = $_GET['anio_grafica'] ?? 'todos';
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
        
        $totales = array_column($grafica_sql, 'total');
        $min_grafica = !empty($totales) ? min($totales) : 0;
        $max_grafica = !empty($totales) ? max($totales) : 0;
        $inicial_grafica = !empty($totales) ? $totales[0] : 0;
        $final_grafica = !empty($totales) ? end($totales) : 0;
        
        $variacion_mensual = 0;
        $variacion_mensual_porcentaje = 0;
        if (count($grafica_sql) >= 2) {
            $datos_temp = $grafica_sql;
            $ultimo = array_pop($datos_temp);
            $penultimo = array_pop($datos_temp);
            $variacion_mensual = $ultimo['total'] - $penultimo['total'];
            if ($penultimo['total'] > 0) {
                $variacion_mensual_porcentaje = ($variacion_mensual / $penultimo['total']) * 100;
            }
        }
        
        echo json_encode([
            'data' => $grafica_sql,
            'stats' => [
                'min' => $min_grafica,
                'max' => $max_grafica,
                'inicial' => $inicial_grafica,
                'final' => $final_grafica,
                'variacion_mensual' => $variacion_mensual,
                'variacion_mensual_porcentaje' => $variacion_mensual_porcentaje
            ]
        ]);
        break;
        
    case 'grafica_cuenta':
        $cuenta_filtro = $_GET['cuenta_filtro'] ?? 'todas';
        $anio_cuenta_filtro = $_GET['anio_cuenta_filtro'] ?? 'todos';
        $condicion_anio_cuenta = $anio_cuenta_filtro === 'todos' ? 'YEAR(fecha_captura) >= 2023' : 'YEAR(fecha_captura) = ' . intval($anio_cuenta_filtro);
        
        if ($cuenta_filtro === 'todas') {
            $stmt_todas = $pdo->query("
                SELECT 
                    c.id as cuenta_id,
                    c.nombre as cuenta_nombre,
                    c.tipo,
                    DATE_FORMAT(s.fecha_captura, '%b %y') as mes,
                    s.fecha_captura,
                    s.monto
                FROM saldos s
                JOIN cuentas c ON s.cuenta_id = c.id
                WHERE $condicion_anio_cuenta
                ORDER BY c.nombre ASC, s.fecha_captura ASC
            ");
            $grafica_cuenta_sql = $stmt_todas->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt_cuenta = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(fecha_captura, '%b %y') as mes,
                    fecha_captura,
                    monto
                FROM saldos
                WHERE cuenta_id = ? AND $condicion_anio_cuenta
                ORDER BY fecha_captura ASC
            ");
            $stmt_cuenta->execute([$cuenta_filtro]);
            $grafica_cuenta_sql = $stmt_cuenta->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'data' => $grafica_cuenta_sql,
            'tipo' => $cuenta_filtro
        ]);
        break;
        
    case 'tabla_capturas':
        $mes_filtro = $_GET['mes'] ?? date('m');
        $anio_filtro = $_GET['anio'] ?? date('Y');
        
        $stmt_tabla = $pdo->prepare("
            SELECT s.id, c.nombre as cuenta, s.monto, s.fecha_captura, c.tipo 
            FROM saldos s
            JOIN cuentas c ON s.cuenta_id = c.id
            WHERE MONTH(s.fecha_captura) = ? AND YEAR(s.fecha_captura) = ?
            ORDER BY s.fecha_captura DESC
        ");
        $stmt_tabla->execute([$mes_filtro, $anio_filtro]);
        $registros_tabla = $stmt_tabla->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['data' => $registros_tabla]);
        break;
        
    default:
        echo json_encode(['error' => 'Acción no válida']);
}
