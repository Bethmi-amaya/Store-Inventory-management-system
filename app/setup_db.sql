CREATE DATABASE IF NOT EXISTS inventory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    section VARCHAR(100) DEFAULT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(10) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    default_reorder INT NOT NULL DEFAULT 10,
    color VARCHAR(20) DEFAULT '#0F766E'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    rack_no VARCHAR(50) DEFAULT NULL,
    rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reorder_level INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    entry_date DATE NOT NULL,
    reference VARCHAR(100) NOT NULL,
    entry_type ENUM('IN', 'OUT') NOT NULL,
    source VARCHAR(100) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_entries_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    section_name VARCHAR(100) NOT NULL,
    qty INT NOT NULL,
    type ENUM('red', 'blue') NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'To buy',
    order_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW item_balances AS
SELECT
    i.id,
    i.code,
    i.name,
    c.name AS category,
    c.code AS category_code,
    i.unit,
    i.rack_no,
    i.rate,
    i.reorder_level,
    COALESCE(SUM(CASE WHEN se.entry_type = 'IN' THEN se.quantity ELSE 0 END), 0) AS total_received,
    COALESCE(SUM(CASE WHEN se.entry_type = 'OUT' THEN se.quantity ELSE 0 END), 0) AS total_issued,
    (COALESCE(SUM(CASE WHEN se.entry_type = 'IN' THEN se.quantity ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN se.entry_type = 'OUT' THEN se.quantity ELSE 0 END), 0)) AS balance
FROM items i
LEFT JOIN categories c ON c.id = i.category_id
LEFT JOIN stock_entries se ON se.item_id = i.id
GROUP BY i.id, i.code, i.name, c.name, c.code, i.unit, i.rack_no, i.rate, i.reorder_level;

INSERT INTO users (full_name, username, email, section, role, status, password_hash)
VALUES (
    'System Admin',
    'admin',
    'admin@stationerypro.com',
    'Administration',
    'admin',
    'approved',
    '$2y$10$UIXCNS8tk9.ARrwenAkL9uvQHgb8k67xYlQC5LwvFv3N8XRpGQfeK'
)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    username = VALUES(username),
    section = VALUES(section),
    role = VALUES(role),
    status = VALUES(status),
    password_hash = VALUES(password_hash);

INSERT INTO categories (name, code, description, unit, default_reorder, color)
VALUES
    ('Nuts', 'NUT', 'Fasteners and nuts', 'pcs', 100, '#1D4ED8'),
    ('PVC', 'PVC', 'Pipes and fittings', 'pcs', 20, '#0F766E'),
    ('Oil', 'OIL', 'Lubricants and oils', 'L', 20, '#B45309'),
    ('Stationery', 'STA', 'Office and printing supplies', 'pcs', 20, '#BE123C')
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    unit = VALUES(unit),
    default_reorder = VALUES(default_reorder),
    color = VALUES(color);

INSERT INTO items (code, name, category_id, unit, rack_no, rate, reorder_level)
VALUES
    ('NUT-001', 'Nut M8', (SELECT id FROM categories WHERE code = 'NUT'), 'pcs', 'A1', 0.00, 200),
    ('NUT-002', 'Bolt M10', (SELECT id FROM categories WHERE code = 'NUT'), 'pcs', 'A1', 0.00, 100),
    ('PVC-001', 'PVC Pipe 1/2"', (SELECT id FROM categories WHERE code = 'PVC'), 'pcs', 'B2', 0.00, 30),
    ('PVC-002', 'PVC Elbow 2"', (SELECT id FROM categories WHERE code = 'PVC'), 'pcs', 'B2', 0.00, 20),
    ('OIL-001', 'Oil 15W-40', (SELECT id FROM categories WHERE code = 'OIL'), 'L', 'C3', 0.00, 20),
    ('OIL-002', 'Oil 20W-50', (SELECT id FROM categories WHERE code = 'OIL'), 'L', 'C3', 0.00, 20),
    ('STA-001', 'A4 Paper Ream', (SELECT id FROM categories WHERE code = 'STA'), 'pcs', 'D4', 0.00, 20)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    category_id = VALUES(category_id),
    unit = VALUES(unit),
    rack_no = VALUES(rack_no),
    rate = VALUES(rate),
    reorder_level = VALUES(reorder_level);

INSERT INTO stock_entries (item_id, entry_date, reference, entry_type, source, quantity)
VALUES
    ((SELECT id FROM items WHERE code = 'NUT-001'), '2026-11-04', 'GRN-0412', 'IN', 'Colombo Port', 200),
    ((SELECT id FROM items WHERE code = 'PVC-001'), '2026-11-06', 'GRN-0419', 'IN', 'Shop', 100)
ON DUPLICATE KEY UPDATE
    entry_date = VALUES(entry_date),
    reference = VALUES(reference),
    entry_type = VALUES(entry_type),
    source = VALUES(source),
    quantity = VALUES(quantity);

INSERT INTO orders (item_id, section_name, qty, type, status, order_date)
VALUES
    ((SELECT id FROM items WHERE code = 'OIL-002'), 'Workshop', 20, 'red', 'To buy', '2026-11-07'),
    ((SELECT id FROM items WHERE code = 'PVC-002'), 'Plumbing', 30, 'red', 'To buy', '2026-11-07'),
    ((SELECT id FROM items WHERE code = 'NUT-001'), 'Plumbing', 60, 'blue', 'Issued', '2026-11-07')
ON DUPLICATE KEY UPDATE
    section_name = VALUES(section_name),
    qty = VALUES(qty),
    type = VALUES(type),
    status = VALUES(status),
    order_date = VALUES(order_date);
