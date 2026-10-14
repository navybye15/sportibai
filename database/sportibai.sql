-- Sportibai database (v2, matches the proposal)
-- HOW TO USE: create an empty database named sportibai, click it, then Import this file.
-- WARNING: it drops and recreates the tables, so re-importing wipes existing data.
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items, orders, expenses, items, categories, customer, users;
SET FOREIGN_KEY_CHECKS = 1;

-- Login accounts (customers AND admin). Never delete rows, set status to 'inactive'.
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,             -- bcrypt hash, never plain text
    role VARCHAR(10) NOT NULL DEFAULT 'user',   -- 'user' or 'admin'
    status VARCHAR(10) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customer profile, filled in before the first checkout
CREATE TABLE customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,                -- one profile per account
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1     -- 0 = hidden (soft delete)
);

CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(50),
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,               -- 0 = Out of Stock
    image VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,    -- 0 = hidden (soft delete)
    CHECK (stock >= 0),
    CHECK (price > 0),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_type VARCHAR(10) NOT NULL,            -- 'pickup' or 'delivery'
    delivery_address VARCHAR(255),              -- empty if pickup
    contact_number VARCHAR(20) NOT NULL,
    payment_method VARCHAR(10) NOT NULL DEFAULT 'COD',   -- COD only
    total DECIMAL(10,2) NOT NULL,
    -- Pending, Preparing, Ready for Pickup, Out for Delivery, Completed, Cancelled
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    date_ordered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,                 -- set when status becomes Completed
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id)
);

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,               -- price at the time of the order
    CHECK (quantity > 0),
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id)
);

-- Store expenses, used by the sales report (sales minus expenses = net income)
CREATE TABLE expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    description VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,              -- e.g. Supplies, Rent, Delivery, Other
    amount DECIMAL(10,2) NOT NULL
);

-- Starter data
INSERT INTO categories (name) VALUES ('Ball Sports'), ('Fitness and Gym'), ('Apparel and Footwear'), ('Accessories');

INSERT INTO items (category_id, name, brand, description, price, stock) VALUES
(1, 'Basketball Size 7', 'Spalding', 'Outdoor rubber basketball', 1200.00, 20),
(1, 'Badminton Racket', 'Yonex', 'Lightweight beginner racket', 1500.00, 15),
(2, 'Yoga Mat 6mm', 'Generic', 'Non-slip yoga mat', 450.00, 30),
(2, 'Dumbbell Set 10kg', 'Generic', 'Adjustable dumbbell pair', 900.00, 12),
(3, 'Dri-Fit Jersey', 'Nike', 'Breathable sports jersey', 800.00, 25),
(3, 'Running Shoes', 'Adidas', 'Lightweight running shoes', 3200.00, 10),
(4, 'Water Bottle 1L', 'Generic', 'BPA-free bottle', 250.00, 40),
(4, 'Gym Duffel Bag', 'Puma', 'Medium gym bag', 1100.00, 18);
