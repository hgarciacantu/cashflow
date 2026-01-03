<?php require_once 'config.php'; ?>
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
                <div class="card stat-card p-3 text-center">
                    <small class="opacity-75">BALANCE TOTAL</small>
                    <h2 class="fw-bold">$<?php echo number_format($total_actual, 2); ?></h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 mb-4">
                    <h5 class="fw-bold mb-4">Evolución del Patrimonio</h5>
                    <canvas id="mainChart" height="120"></canvas>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card p-3">
                            <small class="text-muted">GASTO DIARIO (ÚLTIMO PERIODO)</small>
                            <h4 class="fw-bold text-danger">$<?php echo number_format(abs($gasto_diario), 2); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3">
                            <small class="text-muted">FRECUENCIA DE CAPTURA</small>
                            <h4 class="fw-bold text-primary"><?php echo $dias_dif; ?> días</h4>
                        </div>
                    </div>
                </div>
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
        }
    });
    </script>
</body>

</html>