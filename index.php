<?php 
require_once 'auth.php';
verificarAutenticacion();
require_once 'config.php'; 
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashflow Pro v2</title>
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

    .stat-card {
        background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        color: white;
    }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="fw-bold">Dashboard Financiero</h1>
                <p class="text-muted">Gestión de activos y flujo de caja</p>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 justify-content-end mb-2">
                    <a href="flujo.php" class="btn btn-sm btn-outline-success">💰 Flujo de Efectivo</a>
                    <span class="text-muted">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="auth.php?logout=1" class="text-danger text-decoration-none" title="Cerrar sesión">
                        Salir →
                    </a>
                </div>
                <div class="card stat-card p-3 text-center">
                    <small class="opacity-75">BALANCE TOTAL</small>
                    <h2 class="fw-bold">$<?php echo number_format($total_actual, 2); ?></h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">GASTO DIARIO (ÚLTIMO PERIODO)</small>
                        <h4 class="fw-bold text-danger">$<?php echo number_format(abs($gasto_diario), 2); ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">FRECUENCIA DE CAPTURA</small>
                        <h4 class="fw-bold text-primary"><?php echo $dias_dif; ?> días</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">GASTO PROMEDIO
                            (<?php echo $anio_grafica === 'todos' ? 'TODOS' : $anio_grafica; ?>)</small>
                        <h4 class="fw-bold <?php echo $gasto_promedio >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $gasto_promedio >= 0 ? '+' : ''; ?>$<?php echo number_format($gasto_promedio, 2); ?>/día
                        </h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3">
                        <small class="text-muted">VARIACIÓN MENSUAL</small>
                        <h4 class="fw-bold <?php echo $variacion_mensual >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $variacion_mensual >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($variacion_mensual), 2); ?>
                        </h4>

                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card p-4 mb-4" style="height: 500px; display: flex; flex-direction: column;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="fw-bold mb-0">Evolución del Patrimonio</h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-primary-subtle text-primary-emphasis" style="font-size: 0.75rem;">
                                    Inicial: $<?php echo number_format($inicial_grafica, 2); ?>
                                </span>
                                <span class="badge bg-info-subtle text-info-emphasis" style="font-size: 0.75rem;">
                                    Final: $<?php echo number_format($final_grafica, 2); ?>
                                </span>
                                <span class="badge bg-success-subtle text-success-emphasis" style="font-size: 0.75rem;">
                                    Máx: $<?php echo number_format($max_grafica, 2); ?>
                                </span>
                                <span class="badge bg-danger-subtle text-danger-emphasis" style="font-size: 0.75rem;">
                                    Mín: $<?php echo number_format($min_grafica, 2); ?>
                                </span>
                                <?php if($variacion_grafica != 0): ?>
                                <span class="badge <?php echo $variacion_grafica > 0 ? 'bg-success' : 'bg-danger'; ?>"
                                    style="font-size: 0.75rem;">
                                    <?php echo $variacion_grafica > 0 ? '+' : ''; ?><?php echo number_format($variacion_grafica, 1); ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form id="filtroPatrimonio" method="GET" class="d-flex gap-2">
                            <select name="anio_grafica" class="form-select form-select-sm" id="anioGrafica">
                                <option value="todos"
                                    <?php if(!isset($_GET['anio_grafica']) || $_GET['anio_grafica'] == 'todos') echo 'selected'; ?>>
                                    Todos</option>
                                <?php foreach($anios_disponibles as $anio): ?>
                                <option value="<?php echo $anio; ?>"
                                    <?php if(isset($_GET['anio_grafica']) && $_GET['anio_grafica'] == $anio) echo 'selected'; ?>>
                                    <?php echo $anio; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div style="flex: 1; position: relative;">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>

                <!-- Gráfica de Evolución por Cuenta -->
                <div class="card p-4 mb-4 mt-4" style="height: 400px; display: flex; flex-direction: column;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">Evolución por Cuenta</h5>
                        <form id="filtroCuenta" method="GET" class="d-flex gap-2">
                            <?php if(isset($_GET['anio_grafica'])): ?>
                            <input type="hidden" name="anio_grafica" value="<?php echo $_GET['anio_grafica']; ?>">
                            <?php endif; ?>
                            <select name="cuenta_filtro" class="form-select form-select-sm" id="cuentaFiltro">
                                <option value="todas" <?php if($cuenta_filtro == 'todas') echo 'selected'; ?>>Todas las
                                    cuentas</option>
                                <?php foreach($cuentas as $c): ?>
                                <option value="<?php echo $c['id']; ?>"
                                    <?php if($cuenta_filtro == $c['id']) echo 'selected'; ?>>
                                    <?php echo $c['nombre']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="anio_cuenta_filtro" class="form-select form-select-sm" id="anioCuentaFiltro">
                                <option value="todos" <?php if($anio_cuenta_filtro == 'todos') echo 'selected'; ?>>Todos
                                </option>
                                <?php foreach($anios_disponibles as $anio): ?>
                                <option value="<?php echo $anio; ?>"
                                    <?php if($anio_cuenta_filtro == $anio) echo 'selected'; ?>>
                                    <?php echo $anio; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div style="flex: 1; position: relative;">
                        <?php if(!empty($grafica_cuenta_sql)): ?>
                        <canvas id="cuentaChart"></canvas>
                        <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <p class="text-muted">Selecciona una cuenta para ver su evolución</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                                <h5 class="fw-bold mb-0">Detalle de Capturas</h5>

                                <form id="filtroTabla" class="d-flex gap-2 align-items-center mt-2 mt-md-0"
                                    method="GET">
                                    <select name="mes" class="form-select form-select-sm" id="mesFiltro">
                                        <?php
                        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                        foreach ($meses as $i => $nombre):
                            $val = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                            echo "<option value='$val' ".($mes_filtro == $val ? 'selected' : '').">$nombre</option>";
                        endforeach;
                        ?>
                                    </select>
                                    <select name="anio" class="form-select form-select-sm" id="anioFiltro">
                                        <?php foreach($anios_disponibles as $anio): ?>
                                        <option value="<?php echo $anio; ?>"
                                            <?php if($anio_filtro == $anio) echo 'selected'; ?>>
                                            <?php echo $anio; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Cuenta</th>
                                            <th>Tipo</th>
                                            <th class="text-end">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaBody">
                                        <?php if (empty($registros_tabla)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No hay capturas en este
                                                periodo.</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($registros_tabla as $reg): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($reg['fecha_captura'])); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($reg['cuenta']); ?></td>
                                            <td><span
                                                    class="badge bg-soft-primary text-primary border border-primary-subtle rounded-pill"><?php echo $reg['tipo']; ?></span>
                                            </td>
                                            <td class="text-end fw-bold">$<?php echo number_format($reg['monto'], 2); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4"></div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 mb-4">
                    <h5 class="fw-bold mb-3">Registrar Saldo</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="nuevo_saldo">
                        <div class="mb-2">
                            <label class="small fw-bold">Cuenta</label>
                            <select name="cuenta_id" class="form-select" required>
                                <?php foreach($cuentas as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="small fw-bold">Monto Actual</label>
                            <input type="number" step="0.01" name="monto" class="form-control" placeholder="0.00"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <button class="btn btn-dark w-100 py-2">Guardar Registro</button>
                    </form>
                </div>

                <div class="card p-4">
                    <h5 class="fw-bold mb-3">Configurar Catálogo</h5>
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="nueva_cuenta">
                        <div class="input-group mb-2">
                            <input type="text" name="nombre_cuenta" class="form-control form-control-sm"
                                placeholder="Nombre cuenta..." required>
                            <select name="tipo_cuenta" class="form-select form-select-sm">
                                <option value="Ahorro">Ahorro</option>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Inversión">Inversión</option>
                            </select>
                        </div>
                        <button class="btn btn-outline-primary btn-sm w-100">+ Agregar al Catálogo</button>
                    </form>
                    <div class="list-group list-group-flush">
                        <?php foreach($cuentas as $c): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <small><?php echo $c['nombre']; ?></small>
                            <span class="badge bg-light text-dark rounded-pill"><?php echo $c['tipo']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('mainChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($grafica_sql, 'mes')); ?>,
            datasets: [{
                label: 'Patrimonio Total',
                data: <?php echo json_encode(array_column($grafica_sql, 'total')); ?>,
                borderColor: '#6366f1',
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(99, 102, 241, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        maxTicksLimit: 10,
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
                            return 'Patrimonio: $' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    <?php if(!empty($grafica_cuenta_sql)): ?>
    // Gráfica de evolución por cuenta
    const ctxCuenta = document.getElementById('cuentaChart').getContext('2d');

    <?php if($cuenta_filtro === 'todas'): ?>
    // Preparar datos para múltiples cuentas
    const cuentasData = <?php 
        $cuentas_agrupadas = [];
        foreach ($grafica_cuenta_sql as $registro) {
            $cuenta_id = $registro['cuenta_id'];
            if (!isset($cuentas_agrupadas[$cuenta_id])) {
                $cuentas_agrupadas[$cuenta_id] = [
                    'nombre' => $registro['cuenta_nombre'],
                    'fechas' => [],
                    'montos' => []
                ];
            }
            $cuentas_agrupadas[$cuenta_id]['fechas'][] = $registro['mes'];
            $cuentas_agrupadas[$cuenta_id]['montos'][] = floatval($registro['monto']);
        }
        
        // Obtener todas las fechas únicas
        $todas_fechas = [];
        foreach ($grafica_cuenta_sql as $registro) {
            if (!in_array($registro['mes'], $todas_fechas)) {
                $todas_fechas[] = $registro['mes'];
            }
        }
        
        echo json_encode([
            'cuentas' => array_values($cuentas_agrupadas),
            'fechas' => $todas_fechas
        ]);
    ?>;

    const colores = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

    const datasets = cuentasData.cuentas.map((cuenta, index) => ({
        label: cuenta.nombre,
        data: cuenta.montos,
        borderColor: colores[index % colores.length],
        tension: 0.4,
        fill: false
    }));

    new Chart(ctxCuenta, {
        type: 'line',
        data: {
            labels: cuentasData.fechas,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        maxTicksLimit: 10,
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
                }
            }
        }
    });
    <?php else: ?>
    // Gráfica de una sola cuenta
    new Chart(ctxCuenta, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($grafica_cuenta_sql, 'mes')); ?>,
            datasets: [{
                label: 'Saldo de Cuenta',
                data: <?php echo json_encode(array_column($grafica_cuenta_sql, 'monto')); ?>,
                borderColor: '#10b981',
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(16, 185, 129, 0.1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        maxTicksLimit: 10,
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
                            return 'Saldo: $' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    <?php endif; ?>

    // ========== AJAX HANDLERS ==========
    let chartPatrimonio, chartCuenta;

    // Filtro de gráfica de patrimonio
    document.getElementById('anioGrafica').addEventListener('change', function() {
        const anio = this.value;
        fetch(`ajax_handler.php?action=grafica_patrimonio&anio_grafica=${anio}`)
            .then(res => res.json())
            .then(data => {
                // Actualizar gráfica
                chartPatrimonio.data.labels = data.data.map(d => d.mes);
                chartPatrimonio.data.datasets[0].data = data.data.map(d => d.total);
                chartPatrimonio.update();

                // Actualizar badges
                document.querySelector('.badge.bg-primary-subtle').innerHTML = 'Inicial: $' + Number(data
                    .stats.inicial).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.querySelector('.badge.bg-info-subtle').innerHTML = 'Final: $' + Number(data.stats
                    .final).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.querySelector('.badge.bg-success-subtle').innerHTML = 'Máx: $' + Number(data.stats
                    .max).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.querySelector('.badge.bg-danger-subtle').innerHTML = 'Mín: $' + Number(data.stats
                    .min).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Actualizar card de variación mensual
                const varCard = document.querySelector('.col-md-3:nth-child(4) .card h4');
                const varSmall = document.querySelector('.col-md-3:nth-child(4) .card small');
                const signo = data.stats.variacion_mensual >= 0 ? '+' : '';
                varCard.className = 'fw-bold ' + (data.stats.variacion_mensual >= 0 ? 'text-success' :
                    'text-danger');
                varCard.innerHTML = signo + '$' + Number(Math.abs(data.stats.variacion_mensual))
                    .toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                varSmall.className = data.stats.variacion_mensual >= 0 ? 'text-success' : 'text-danger';
                varSmall.innerHTML = (data.stats.variacion_mensual >= 0 ? '↑' : '↓') + ' ' + Math.abs(data
                    .stats.variacion_mensual_porcentaje).toFixed(1) + '%';
            });
    });

    // Filtro de gráfica por cuenta
    function actualizarGraficaCuenta() {
        const cuenta = document.getElementById('cuentaFiltro').value;
        const anio = document.getElementById('anioCuentaFiltro').value;

        fetch(`ajax_handler.php?action=grafica_cuenta&cuenta_filtro=${cuenta}&anio_cuenta_filtro=${anio}`)
            .then(res => res.json())
            .then(data => {
                const container = document.querySelector('#cuentaChart').parentElement;

                if (data.data.length === 0) {
                    container.innerHTML =
                        '<div class="d-flex align-items-center justify-content-center h-100"><p class="text-muted">No hay datos para este filtro</p></div>';
                    return;
                }

                if (!container.querySelector('canvas')) {
                    container.innerHTML = '<canvas id="cuentaChart"></canvas>';
                }

                if (chartCuenta) {
                    chartCuenta.destroy();
                }

                const ctx = document.getElementById('cuentaChart').getContext('2d');

                if (data.tipo === 'todas') {
                    // Múltiples cuentas
                    const cuentasAgrupadas = {};
                    data.data.forEach(reg => {
                        if (!cuentasAgrupadas[reg.cuenta_id]) {
                            cuentasAgrupadas[reg.cuenta_id] = {
                                nombre: reg.cuenta_nombre,
                                data: []
                            };
                        }
                        cuentasAgrupadas[reg.cuenta_id].data.push({
                            x: reg.mes,
                            y: parseFloat(reg.monto)
                        });
                    });

                    const colores = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                    const datasets = Object.values(cuentasAgrupadas).map((cuenta, i) => ({
                        label: cuenta.nombre,
                        data: cuenta.data.map(d => d.y),
                        borderColor: colores[i % colores.length],
                        tension: 0.4,
                        fill: false
                    }));

                    const todasFechas = [...new Set(data.data.map(d => d.mes))];

                    chartCuenta = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: todasFechas,
                            datasets: datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: v => '$' + v.toLocaleString()
                                    }
                                }
                            }
                        }
                    });
                } else {
                    // Una sola cuenta
                    chartCuenta = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.data.map(d => d.mes),
                            datasets: [{
                                label: 'Saldo de Cuenta',
                                data: data.data.map(d => d.monto),
                                borderColor: '#10b981',
                                tension: 0.4,
                                fill: true,
                                backgroundColor: 'rgba(16, 185, 129, 0.1)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: {
                                        callback: v => '$' + v.toLocaleString()
                                    }
                                }
                            }
                        }
                    });
                }
            });
    }

    document.getElementById('cuentaFiltro').addEventListener('change', actualizarGraficaCuenta);
    document.getElementById('anioCuentaFiltro').addEventListener('change', actualizarGraficaCuenta);

    // Filtro de tabla
    function actualizarTabla() {
        const mes = document.getElementById('mesFiltro').value;
        const anio = document.getElementById('anioFiltro').value;

        fetch(`ajax_handler.php?action=tabla_capturas&mes=${mes}&anio=${anio}`)
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tablaBody');

                if (data.data.length === 0) {
                    tbody.innerHTML =
                        '<tr><td colspan="4" class="text-center text-muted py-4">No hay capturas en este periodo.</td></tr>';
                } else {
                    tbody.innerHTML = data.data.map(reg => `
                        <tr>
                            <td>${new Date(reg.fecha_captura).toLocaleDateString('es-MX')}</td>
                            <td class="fw-bold">${reg.cuenta}</td>
                            <td><span class="badge bg-soft-primary text-primary border border-primary-subtle rounded-pill">${reg.tipo}</span></td>
                            <td class="text-end fw-bold">$${Number(reg.monto).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `).join('');
                }
            });
    }

    document.getElementById('mesFiltro').addEventListener('change', actualizarTabla);
    document.getElementById('anioFiltro').addEventListener('change', actualizarTabla);

    // Guardar referencias de las gráficas iniciales
    chartPatrimonio = Chart.getChart('mainChart');
    <?php if(!empty($grafica_cuenta_sql)): ?>
    chartCuenta = Chart.getChart('cuentaChart');
    <?php endif; ?>
    </script>
</body>

</html>