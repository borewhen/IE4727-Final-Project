-- db name is "stirling"

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS order_items, orders, cart_items, customers, products, categories;
SET FOREIGN_KEY_CHECKS=1;

-- 1) Categories (e.g., Shirts, Pants, Shoes, Watches, Bags)
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2) Products (individual items like "Classic Oxford Shirt", "Leather Messenger Bag")
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  stock_quantity INT NOT NULL DEFAULT 0,
  brand VARCHAR(100),
  material VARCHAR(100),
  image_filename VARCHAR(255),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX(category_id),
  INDEX(is_active),
  INDEX(is_featured)
);

-- 3) Customers (registered users)
CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  shipping_address TEXT,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME,
  INDEX(email)
);

-- 4) Cart Items (shopping cart for logged-in users)
CREATE TABLE cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  size VARCHAR(10),
  color VARCHAR(50),
  quantity INT NOT NULL DEFAULT 1,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX(customer_id),
  INDEX(product_id)
);

-- 5) Orders (order header with customer and shipping info)
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT,
  order_number VARCHAR(50) NOT NULL UNIQUE,
  customer_email VARCHAR(150) NOT NULL,
  customer_name VARCHAR(200) NOT NULL,
  customer_phone VARCHAR(20),
  shipping_address TEXT NOT NULL,
  order_total DECIMAL(10,2) NOT NULL,
  order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  design_approved TINYINT(1) NOT NULL DEFAULT 0,
  mockup_filename VARCHAR(255),
  special_instructions TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  payment_completed_at DATETIME,
  shipped_at DATETIME,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  INDEX(customer_id),
  INDEX(order_number),
  INDEX(customer_email),
  INDEX(order_status),
  INDEX(created_at)
);

-- 6) Order Items (line items with locked prices)
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL,
  product_image_filename VARCHAR(255),
  size VARCHAR(10),
  color VARCHAR(50),
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX(order_id),
  INDEX(product_id)
);