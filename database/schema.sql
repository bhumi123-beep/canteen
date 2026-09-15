-- CanteenPro Database Schema
CREATE DATABASE IF NOT EXISTS canteen_db;
USE canteen_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer','admin','staff') NOT NULL DEFAULT 'customer',
    salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('on_duty','on_leave','removed') NOT NULL DEFAULT 'on_duty',
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE auth_login_attempts (
    identifier VARCHAR(190) PRIMARY KEY,
    failed_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE staff_management (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_name VARCHAR(150) NOT NULL,
    staff_email VARCHAR(150) NOT NULL,
    staff_role VARCHAR(60) NOT NULL DEFAULT 'Service Staff',
    department VARCHAR(80) NOT NULL DEFAULT 'Operations',
    staff_phone VARCHAR(20) NULL,
    staff_shift ENUM('Morning','Evening','Night') NOT NULL DEFAULT 'Morning',
    staff_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    staff_status ENUM('on_duty','on_leave','removed') NOT NULL DEFAULT 'on_duty',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    performance_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    rating_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    current_stock INT DEFAULT 0,
    reorder_level INT DEFAULT 5,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    unit VARCHAR(30) NOT NULL DEFAULT 'unit',
    current_stock DECIMAL(10,3) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(10,3) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE recipe_ingredients (
    menu_item_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity_per_item DECIMAL(10,3) NOT NULL,
    PRIMARY KEY (menu_item_id, ingredient_id),
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
);

CREATE TABLE tables_ (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(20) NOT NULL,
    capacity INT NOT NULL,
    location VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE table_qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL UNIQUE,
    token CHAR(32) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables_(id) ON DELETE CASCADE
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    table_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    guests INT NOT NULL DEFAULT 1,
        status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables_(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    status ENUM('pending','preparing','ready','completed','cancelled') DEFAULT 'pending',
    order_type ENUM('dine-in','takeaway') DEFAULT 'takeaway',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    promo_code VARCHAR(40) DEFAULT NULL,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    service_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    special_instructions TEXT,
    table_number VARCHAR(20) DEFAULT NULL,
    served_by_staff_id INT DEFAULT NULL,
    prepared_by_staff_id INT DEFAULT NULL,
    public_review_token CHAR(64) NOT NULL UNIQUE,
    order_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (served_by_staff_id) REFERENCES staff_management(id) ON DELETE SET NULL,
    FOREIGN KEY (prepared_by_staff_id) REFERENCES staff_management(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

CREATE TABLE ingredient_inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    order_id INT NULL,
    action VARCHAR(40) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    previous_stock DECIMAL(10,3) NOT NULL,
    new_stock DECIMAL(10,3) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    method ENUM('esewa','khalti','bank_transfer','cash') NOT NULL,
    status ENUM('pending','success','failed') DEFAULT 'pending',
    transaction_ref VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    paid_at DATETIME NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE staff_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    customer_id INT NULL,
    order_id INT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_rating (customer_id, order_id),
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE customer_review_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    decision ENUM('skipped','completed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_item_id INT NOT NULL,
    action VARCHAR(40) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    previous_stock INT NOT NULL DEFAULT 0,
    new_stock INT NOT NULL DEFAULT 0,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
);

CREATE TABLE promo_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    promo_code VARCHAR(40) NOT NULL UNIQUE,
    discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 25,
    shop_name VARCHAR(100) NOT NULL DEFAULT 'CanteenPro',
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE staff_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    due_at DATETIME NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE
);

CREATE TABLE receiving_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier VARCHAR(150) NOT NULL,
    items_received VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending','received') NOT NULL DEFAULT 'pending',
    received_by_staff_id INT DEFAULT NULL,
    received_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (received_by_staff_id) REFERENCES staff_management(id) ON DELETE SET NULL
);

CREATE TABLE operating_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    department VARCHAR(80) NOT NULL DEFAULT 'Operations',
    amount DECIMAL(10,2) NOT NULL,
    cost_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cashier_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    cashier_staff_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','card') NOT NULL,
    status ENUM('paid','refunded') NOT NULL DEFAULT 'paid',
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (cashier_staff_id) REFERENCES staff_management(id) ON DELETE RESTRICT
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    transaction_uuid VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('PENDING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
    payment_ref VARCHAR(150) NULL,
    cashier_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (cashier_id) REFERENCES staff_management(id) ON DELETE RESTRICT
);

CREATE TABLE transaction_voids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_type ENUM('esewa','cashier') NOT NULL,
    source_id INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    voided_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_void (source_type, source_id),
    FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE waiter_workspace (
    staff_id INT PRIMARY KEY,
    assigned_section VARCHAR(80) NULL,
    workspace_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE
);

CREATE TABLE chef_workspace (
    staff_id INT PRIMARY KEY,
    station VARCHAR(80) NULL,
    workspace_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE
);

CREATE TABLE cashier_workspace (
    staff_id INT PRIMARY KEY,
    opening_float DECIMAL(10,2) NOT NULL DEFAULT 0,
    shift_status ENUM('open','closed') NOT NULL DEFAULT 'open',
    workspace_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE
);

CREATE TABLE inventory_workspace (
    staff_id INT PRIMARY KEY,
    storage_area VARCHAR(100) NULL,
    workspace_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE
);

CREATE TABLE finance_workspace (
    staff_id INT PRIMARY KEY,
    reporting_period ENUM('today','week','month') NOT NULL DEFAULT 'month',
    workspace_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE
);

-- Seed data
INSERT INTO users (name, email, username, password_hash, role) VALUES
('Admin Central', 'admin@canteenpro.com', 'admincentral', '$2y$10$3otWoTSyvjc5qLYqwTsJfOLYtKeVUF2FTCWNToBUtR9rK1LABRXT6', 'admin');
-- default admin password: admin123

INSERT INTO categories (name) VALUES ('Lunch'), ('Breakfast'), ('Drinks'), ('Snacks');

INSERT INTO menu_items (category_id, name, description, price, current_stock, reorder_level) VALUES
(1, 'Classic Cheeseburger', 'Beef patty, cheese, lettuce, tomato', 5.99, 42, 10),
(1, 'Grilled Chicken Quinoa Bowl', 'Fresh organic greens, quinoa, grilled chicken, vinaigrette', 8.50, 30, 10),
(1, 'Garden Fresh Salad', 'Mixed greens, cherry tomatoes, feta', 4.50, 2, 10),
(3, 'Fresh Iced Tea', 'Chilled iced tea', 3.50, 50, 10);

INSERT INTO tables_ (table_number, capacity, location) VALUES
('T1', 2, 'Window'), ('T2', 2, 'Window'), ('T3', 4, 'Center'),
('T4', 4, 'Center'), ('T5', 2, 'Patio'), ('T6', 2, 'Patio'), ('T7', 6, 'Patio');
