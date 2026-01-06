-- Tabla para categorías de transacciones
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('ingreso', 'gasto') NOT NULL,
    icono VARCHAR(50) DEFAULT '💰',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para transacciones de flujo de efectivo
CREATE TABLE IF NOT EXISTS transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    descripcion VARCHAR(255),
    monto DECIMAL(12,2) NOT NULL,
    fecha DATE NOT NULL,
    recurrente BOOLEAN DEFAULT FALSE,
    frecuencia ENUM('mensual', 'quincenal', 'semanal', 'unico') DEFAULT 'unico',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);

-- Insertar categorías por defecto
INSERT INTO categorias (nombre, tipo, icono) VALUES
('Salario', 'ingreso', '💼'),
('Freelance', 'ingreso', '💻'),
('Inversiones', 'ingreso', '📈'),
('Otros Ingresos', 'ingreso', '💰'),
('Renta/Hipoteca', 'gasto', '🏠'),
('Servicios', 'gasto', '💡'),
('Alimentación', 'gasto', '🍔'),
('Transporte', 'gasto', '🚗'),
('Entretenimiento', 'gasto', '🎬'),
('Salud', 'gasto', '🏥'),
('Educación', 'gasto', '📚'),
('Otros Gastos', 'gasto', '💸');
