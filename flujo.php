<?php
require_once 'auth.php';
verificarAutenticacion();
require_once 'credentials.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Procesar nueva transacción
if (isset($_POST['action']) && $_POST['action'] == 'nueva_transaccion') {
    try {
        $stmt = $pdo->prepare("INSERT INTO transacciones (categoria_id, descripcion, monto, fecha, recurrente, frecuencia) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['categoria_id'],
            $_POST['descripcion'],
            $_POST['monto'],
            $_POST['fecha'],
            isset($_POST['recurrente']) ? 1 : 0,
            $_POST['frecuencia']
        ]);
        header("Location: flujo.php");
        exit;
    } catch (PDOException $e) {
        error_log("Error al agregar transacción: " . $e->getMessage());
    }
}

// Procesar nueva categoría
if (isset($_POST['action']) && $_POST['action'] == 'nueva_categoria') {
    try {
        $stmt = $pdo->prepare("INSERT INTO categorias (nombre, tipo, icono) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['nombre_categoria'], $_POST['tipo_categoria'], $_POST['icono']]);
        header("Location: flujo.php");
        exit;
    } catch (PDOException $e) {
        error_log("Error al agregar categoría: " . $e->getMessage());
    }
}

// Editar transacción
if (isset($_POST['action']) && $_POST['action'] == 'editar_transaccion') {
    try {
        $stmt = $pdo->prepare("UPDATE transacciones SET monto = ?, descripcion = ?, fecha = ? WHERE id = ?");
        $stmt->execute([
            $_POST['monto'],
            $_POST['descripcion'],
            $_POST['fecha'],
            $_POST['id']
        ]);
        
        // Si es una transacción recurrente base (sin "(auto)"), actualizar todas las futuras auto-generadas
        $check = $pdo->prepare("SELECT recurrente, descripcion FROM transacciones WHERE id = ?");
        $check->execute([$_POST['id']]);
        $transaccion = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($transaccion['recurrente'] && !strpos($transaccion['descripcion'], '(auto)')) {
            // Actualizar descripción y monto de todas las auto-generadas futuras
            $desc_nueva = str_replace(' (auto)', '', $_POST['descripcion']);
            $desc_vieja = str_replace(' (auto)', '', $transaccion['descripcion']);
            
            $update_auto = $pdo->prepare("
                UPDATE transacciones 
                SET monto = ?,
                    descripcion = ?
                WHERE descripcion = ?
                AND recurrente = 1
                AND fecha >= CURDATE()
            ");
            $update_auto->execute([
                $_POST['monto'],
                $desc_nueva . ' (auto)',
                $desc_vieja . ' (auto)'
            ]);
        }
        
        header("Location: flujo.php?mes=" . $_POST['mes_actual'] . "&anio=" . $_POST['anio_actual']);
        exit;
    } catch (PDOException $e) {
        error_log("Error al editar transacción: " . $e->getMessage());
    }
}

// Eliminar transacción
if (isset($_POST['action']) && $_POST['action'] == 'eliminar_transaccion') {
    try {
        $stmt = $pdo->prepare("DELETE FROM transacciones WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: flujo.php?mes=" . $_POST['mes_actual'] . "&anio=" . $_POST['anio_actual']);
        exit;
    } catch (PDOException $e) {
        error_log("Error al eliminar transacción: " . $e->getMessage());
    }
}

// Obtener mes y año seleccionado
$mes_filtro = $_GET['mes'] ?? date('m');
$anio_filtro = $_GET['anio'] ?? date('Y');

// Auto-generar transacciones recurrentes para el mes seleccionado
function generarTransaccionesRecurrentes($pdo, $mes, $anio) {
    // Buscar transacciones recurrentes base (sin el sufijo "(auto)")
    $stmt = $pdo->query("
        SELECT DISTINCT
            t.categoria_id,
            t.descripcion,
            t.monto,
            t.frecuencia
        FROM transacciones t
        WHERE t.recurrente = 1
        AND t.descripcion NOT LIKE '%(auto)%'
    ");
    $recurrentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recurrentes as $rec) {
        // Verificar EXACTAMENTE cuántas transacciones ya existen para este concepto
        $check = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM transacciones 
            WHERE categoria_id = ? 
            AND descripcion LIKE ?
            AND MONTH(fecha) = ?
            AND YEAR(fecha) = ?
        ");
        
        $descripcion_base = str_replace(' (auto)', '', $rec['descripcion']);
        $check->execute([
            $rec['categoria_id'],
            $descripcion_base . '%',
            $mes,
            $anio
        ]);
        
        $existentes = $check->fetchColumn();
        
        // Determinar cuántas deberían existir según frecuencia
        $cantidad_esperada = 1; // Por defecto mensual
        switch($rec['frecuencia']) {
            case 'mensual':
                $cantidad_esperada = 1;
                break;
            case 'quincenal':
                $cantidad_esperada = 2;
                break;
            case 'semanal':
                $cantidad_esperada = 4;
                break;
        }
        
        // Si ya existen las suficientes, saltar
        if ($existentes >= $cantidad_esperada) {
            continue;
        }
        
        // Calcular cuántas faltan por crear
        $faltantes = $cantidad_esperada - $existentes;
        
        // Determinar las fechas a crear
        $registros = [];
        switch($rec['frecuencia']) {
            case 'mensual':
                $registros[] = sprintf("%04d-%02d-01", $anio, $mes);
                break;
            case 'quincenal':
                if ($existentes == 0) {
                    $registros[] = sprintf("%04d-%02d-01", $anio, $mes);
                    $registros[] = sprintf("%04d-%02d-15", $anio, $mes);
                } elseif ($existentes == 1) {
                    $registros[] = sprintf("%04d-%02d-15", $anio, $mes);
                }
                break;
            case 'semanal':
                $dias_base = [1, 8, 15, 22];
                for ($i = $existentes; $i < 4; $i++) {
                    if (isset($dias_base[$i])) {
                        $registros[] = sprintf("%04d-%02d-%02d", $anio, $mes, $dias_base[$i]);
                    }
                }
                break;
        }
        
        // Insertar solo las transacciones faltantes
        foreach ($registros as $fecha) {
            $insert = $pdo->prepare("
                INSERT INTO transacciones (categoria_id, descripcion, monto, fecha, recurrente, frecuencia)
                VALUES (?, ?, ?, ?, 1, ?)
            ");
            $insert->execute([
                $rec['categoria_id'],
                $descripcion_base . ' (auto)',
                $rec['monto'],
                $fecha,
                $rec['frecuencia']
            ]);
        }
    }
}

// Ejecutar auto-generación solo cuando se visualiza un mes (no cuando se hace POST)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['action'])) {
    generarTransaccionesRecurrentes($pdo, $mes_filtro, $anio_filtro);
}

// Obtener categorías
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY tipo, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$categorias_ingresos = array_filter($categorias, function($c) { return $c['tipo'] == 'ingreso'; });
$categorias_gastos = array_filter($categorias, function($c) { return $c['tipo'] == 'gasto'; });

// Obtener años disponibles
$anios_disponibles = $pdo->query("
    SELECT DISTINCT YEAR(fecha) as anio 
    FROM transacciones 
    ORDER BY anio DESC
")->fetchAll(PDO::FETCH_COLUMN);

if (empty($anios_disponibles)) {
    $anios_disponibles = [date('Y')];
}

// Resumen de ingresos y gastos del mes seleccionado
$stmt_resumen = $pdo->prepare("
    SELECT 
        c.tipo,
        SUM(t.monto) as total
    FROM transacciones t
    JOIN categorias c ON t.categoria_id = c.id
    WHERE MONTH(t.fecha) = ? AND YEAR(t.fecha) = ?
    GROUP BY c.tipo
");
$stmt_resumen->execute([$mes_filtro, $anio_filtro]);
$resumen = $stmt_resumen->fetchAll(PDO::FETCH_KEY_PAIR);

$total_ingresos = $resumen['ingreso'] ?? 0;
$total_gastos = $resumen['gasto'] ?? 0;
$flujo_neto = $total_ingresos - $total_gastos;

// Transacciones por categoría del mes
$stmt_por_categoria = $pdo->prepare("
    SELECT 
        c.nombre,
        c.tipo,
        c.icono,
        SUM(t.monto) as total,
        COUNT(t.id) as cantidad
    FROM transacciones t
    JOIN categorias c ON t.categoria_id = c.id
    WHERE MONTH(t.fecha) = ? AND YEAR(t.fecha) = ?
    GROUP BY c.id, c.nombre, c.tipo, c.icono
    ORDER BY c.tipo, total DESC
");
$stmt_por_categoria->execute([$mes_filtro, $anio_filtro]);
$transacciones_por_categoria = $stmt_por_categoria->fetchAll(PDO::FETCH_ASSOC);

// Flujo mensual para gráfica (últimos 12 meses)
$flujo_mensual = $pdo->prepare("
    SELECT 
        DATE_FORMAT(t.fecha, '%b %y') as mes,
        DATE_FORMAT(t.fecha, '%Y-%m') as periodo,
        c.tipo,
        SUM(t.monto) as total
    FROM transacciones t
    JOIN categorias c ON t.categoria_id = c.id
    WHERE t.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY periodo, mes, c.tipo
    ORDER BY periodo ASC
");
$flujo_mensual->execute();
$datos_flujo = $flujo_mensual->fetchAll(PDO::FETCH_ASSOC);

// Organizar datos para la gráfica
$meses_grafica = [];
$ingresos_grafica = [];
$gastos_grafica = [];
$neto_grafica = [];

$datos_organizados = [];
foreach ($datos_flujo as $dato) {
    $periodo = $dato['mes'];
    if (!isset($datos_organizados[$periodo])) {
        $datos_organizados[$periodo] = ['ingreso' => 0, 'gasto' => 0];
    }
    $datos_organizados[$periodo][$dato['tipo']] = floatval($dato['total']);
}

foreach ($datos_organizados as $mes => $datos) {
    $meses_grafica[] = $mes;
    $ingresos_grafica[] = $datos['ingreso'];
    $gastos_grafica[] = $datos['gasto'];
    $neto_grafica[] = $datos['ingreso'] - $datos['gasto'];
}

// Transacciones detalladas del mes
$stmt_detalle = $pdo->prepare("
    SELECT 
        t.id,
        t.descripcion,
        t.monto,
        t.fecha,
        t.recurrente,
        t.frecuencia,
        c.nombre as categoria,
        c.tipo,
        c.icono
    FROM transacciones t
    JOIN categorias c ON t.categoria_id = c.id
    WHERE MONTH(t.fecha) = ? AND YEAR(t.fecha) = ?
    ORDER BY t.fecha DESC, c.tipo
");
$stmt_detalle->execute([$mes_filtro, $anio_filtro]);
$transacciones_detalle = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flujo de Efectivo - Cashflow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    :root {
        --glass: rgba(255, 255, 255, 0.8);
    }

    body {
        background: #f0f2f5;
        font-family: 'Segoe UI', sans-serif;
    }

    .card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
    }

    .stat-card-ingreso {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .stat-card-gasto {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .stat-card-neto {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        color: white;
    }

    .nav-pills .nav-link {
        border-radius: 0.75rem;
        font-weight: 600;
    }

    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
    }

    .categoria-item {
        border-left: 4px solid #6366f1;
        transition: transform 0.2s;
    }

    .categoria-item:hover {
        transform: translateX(5px);
    }
    </style>
</head>

<body>

    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="fw-bold">💰 Flujo de Efectivo</h1>
                <p class="text-muted">Gestión de ingresos y gastos mensuales</p>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 justify-content-end mb-2">
                    <a href="index.php" class="btn btn-sm btn-outline-primary">← Dashboard</a>
                    <span class="text-muted">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="auth.php?logout=1" class="text-danger text-decoration-none">Salir →</a>
                </div>
            </div>
        </div>

        <!-- Filtro de mes/año -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card p-3">
                    <form method="GET" class="d-flex gap-2 align-items-center">
                        <label class="fw-bold">Período:</label>
                        <select name="mes" class="form-select form-select-sm" style="max-width: 150px;">
                            <?php
                            $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                            foreach ($meses as $i => $nombre):
                                $val = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                                echo "<option value='$val' ".($mes_filtro == $val ? 'selected' : '').">$nombre</option>";
                            endforeach;
                            ?>
                        </select>
                        <select name="anio" class="form-select form-select-sm" style="max-width: 100px;">
                            <?php foreach($anios_disponibles as $anio): ?>
                            <option value="<?php echo $anio; ?>" <?php if($anio_filtro == $anio) echo 'selected'; ?>>
                                <?php echo $anio; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cards de resumen -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card-ingreso p-3 text-center">
                    <small class="opacity-75">INGRESOS DEL MES</small>
                    <h3 class="fw-bold mb-0">$<?php echo number_format($total_ingresos, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-gasto p-3 text-center">
                    <small class="opacity-75">GASTOS DEL MES</small>
                    <h3 class="fw-bold mb-0">$<?php echo number_format($total_gastos, 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card-neto p-3 text-center">
                    <small class="opacity-75">FLUJO NETO</small>
                    <h3 class="fw-bold mb-0">
                        <?php echo $flujo_neto >= 0 ? '+' : ''; ?>$<?php echo number_format($flujo_neto, 2); ?>
                    </h3>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Columna izquierda: Gráficas -->
            <div class="col-lg-8">
                <!-- Gráfica de flujo mensual -->
                <div class="card p-4 mb-4" style="height: 400px; display: flex; flex-direction: column;">
                    <h5 class="fw-bold mb-3">Evolución de Flujo (12 Meses)</h5>
                    <div style="flex: 1; position: relative;">
                        <canvas id="flujoChart"></canvas>
                    </div>
                </div>

                <!-- Por categoría -->
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Desglose por Categoría</h5>
                    <div class="row g-3">
                        <?php foreach($transacciones_por_categoria as $cat): ?>
                        <div class="col-md-6">
                            <div class="card categoria-item p-3"
                                style="border-left-color: <?php echo $cat['tipo'] == 'ingreso' ? '#10b981' : '#ef4444'; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fs-4"><?php echo $cat['icono']; ?></span>
                                        <strong class="ms-2"><?php echo $cat['nombre']; ?></strong>
                                        <small class="text-muted d-block ms-5"><?php echo $cat['cantidad']; ?>
                                            transacciones</small>
                                    </div>
                                    <h5
                                        class="fw-bold mb-0 <?php echo $cat['tipo'] == 'ingreso' ? 'text-success' : 'text-danger'; ?>">
                                        $<?php echo number_format($cat['total'], 2); ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Formularios -->
            <div class="col-lg-4">
                <!-- Agregar transacción -->
                <div class="card p-4 mb-4">
                    <h5 class="fw-bold mb-3">Nueva Transacción</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="nueva_transaccion">
                        <div class="mb-2">
                            <label class="small fw-bold">Categoría</label>
                            <select name="categoria_id" class="form-select form-select-sm" required>
                                <optgroup label="💰 Ingresos">
                                    <?php foreach($categorias_ingresos as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo $c['icono'] . ' ' . $c['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="💸 Gastos">
                                    <?php foreach($categorias_gastos as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo $c['icono'] . ' ' . $c['nombre']; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">Descripción</label>
                            <input type="text" name="descripcion" class="form-control form-control-sm"
                                placeholder="Ej: Pago de renta">
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">Monto</label>
                            <input type="number" step="0.01" name="monto" class="form-control form-control-sm"
                                placeholder="0.00" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">Fecha</label>
                            <input type="date" name="fecha" class="form-control form-control-sm"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">Frecuencia</label>
                            <select name="frecuencia" class="form-select form-select-sm">
                                <option value="unico">Único</option>
                                <option value="mensual">Mensual</option>
                                <option value="quincenal">Quincenal</option>
                                <option value="semanal">Semanal</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="recurrente" id="recurrente">
                            <label class="form-check-label small" for="recurrente">
                                Gasto/Ingreso fijo
                            </label>
                        </div>
                        <button class="btn btn-primary w-100">Agregar</button>
                    </form>
                </div>

                <!-- Detalle de transacciones -->
                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Transacciones del Mes</h5>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php if(empty($transacciones_detalle)): ?>
                        <p class="text-muted text-center py-3">No hay transacciones</p>
                        <?php else: ?>
                        <?php foreach($transacciones_detalle as $t): ?>
                        <div class="border-bottom pb-2 mb-2" id="trans-<?php echo $t['id']; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <span><?php echo $t['icono']; ?></span>
                                    <strong class="ms-1"><?php echo $t['categoria']; ?></strong>
                                    <?php if($t['descripcion']): ?>
                                    <small
                                        class="text-muted d-block ms-4"><?php echo htmlspecialchars($t['descripcion']); ?></small>
                                    <?php endif; ?>
                                    <small class="text-muted d-block ms-4">
                                        <?php echo date('d/m/Y', strtotime($t['fecha'])); ?>
                                        <?php if($t['recurrente']): ?>
                                        <span class="badge bg-info"><?php echo $t['frecuencia']; ?></span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <strong
                                        class="<?php echo $t['tipo'] == 'ingreso' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $t['tipo'] == 'ingreso' ? '+' : '-'; ?>$<?php echo number_format($t['monto'], 2); ?>
                                    </strong>
                                    <div class="mt-1">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick='editarTransaccion(<?php echo json_encode($t); ?>)'>✏️</button>
                                        <button class="btn btn-sm btn-outline-danger"
                                            onclick="eliminarTransaccion(<?php echo $t['id']; ?>, '<?php echo htmlspecialchars($t['descripcion'], ENT_QUOTES); ?>')">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para editar transacción -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Transacción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="editar_transaccion">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="mes_actual" value="<?php echo $mes_filtro; ?>">
                        <input type="hidden" name="anio_actual" value="<?php echo $anio_filtro; ?>">

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <input type="text" class="form-control" name="descripcion" id="edit_descripcion" required>
                            <small class="text-muted">Si es recurrente sin "(auto)", actualizará todos los meses
                                futuros</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" class="form-control" name="monto" id="edit_monto" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha" id="edit_fecha" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Funciones de edición/eliminación
    function editarTransaccion(trans) {
        document.getElementById('edit_id').value = trans.id;
        document.getElementById('edit_descripcion').value = trans.descripcion;
        document.getElementById('edit_monto').value = trans.monto;
        document.getElementById('edit_fecha').value = trans.fecha;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function eliminarTransaccion(id, descripcion) {
        if (confirm('¿Eliminar "' + descripcion + '"?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="eliminar_transaccion">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="mes_actual" value="<?php echo $mes_filtro; ?>">
                <input type="hidden" name="anio_actual" value="<?php echo $anio_filtro; ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Gráfica de flujo mensual
    const ctx = document.getElementById('flujoChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($meses_grafica); ?>,
            datasets: [{
                    label: 'Ingresos',
                    data: <?php echo json_encode($ingresos_grafica); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: '#10b981',
                    borderWidth: 2
                },
                {
                    label: 'Gastos',
                    data: <?php echo json_encode($gastos_grafica); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: '#ef4444',
                    borderWidth: 2
                },
                {
                    label: 'Flujo Neto',
                    data: <?php echo json_encode($neto_grafica); ?>,
                    type: 'line',
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                        }
                    }
                },
                legend: {
                    position: 'top'
                }
            }
        }
    });
    </script>
</body>

</html>