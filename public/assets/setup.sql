-- db name is "stirling"

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS order_edit_tokens, variation_sizes, variation_images, product_variations, order_items, orders, cart_items, customers, products, categories,
returns, return_items;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- default categories
INSERT IGNORE INTO categories (name, slug, description, is_active) VALUES
 ('Shoes', 'shoes', '', 1),
 ('Shirts', 'shirts', '', 1),
 ('Pants', 'pants', '', 1),
 ('Outerwear', 'outerwear', '', 1),
 ('Accessories', 'accessories', '', 1);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
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

CREATE TABLE product_variations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  colour VARCHAR(50),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE variation_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  variation_id INT NOT NULL,
  image_filename VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(variation_id),
  FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE
);

CREATE TABLE variation_sizes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  variation_id INT NOT NULL,
  size VARCHAR(32) NOT NULL,
  stock_quantity INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(variation_id),
  FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE
);

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

CREATE TABLE cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  variation_id INT NOT NULL,
  variation_size_id INT NOT NULL,
  size VARCHAR(32),
  color VARCHAR(50),
  quantity INT NOT NULL DEFAULT 1,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE CASCADE,
  FOREIGN KEY (variation_size_id) REFERENCES variation_sizes(id) ON DELETE CASCADE,
  INDEX(customer_id),
  INDEX(product_id),
  INDEX(variation_id),
  INDEX(variation_size_id)
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT,
  order_number VARCHAR(50) NOT NULL UNIQUE,
  customer_email VARCHAR(150) NOT NULL,
  customer_name VARCHAR(200) NOT NULL,
  customer_phone VARCHAR(20),
  shipping_address TEXT NOT NULL,
  order_total DECIMAL(10,2) NOT NULL,
  order_status ENUM('confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'confirmed',
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

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `brand`, `material`, `image_filename`, `is_active`, `is_featured`, `created_at`, `updated_at`) VALUES
(2, 4, 'Stirling\'s Blazer', 'stirlings-blazer', 'Our signature look.', 529.00, '0', 'Wool', 'assets/images/products/stirlings-blazer/main.jpg', 1, 0, '2025-11-02 06:48:13', '2025-11-02 06:48:13'),
(3, 4, 'Miznon\'s Checkered Blazer', 'miznons-checkered-blazer', 'Take style up a level with the checkered patterns.', 659.00, '0', '', 'assets/images/products/miznons-checkered-blazer/main.jpg', 1, 0, '2025-11-02 07:15:20', '2025-11-02 07:15:20');

INSERT INTO `product_variations` (`id`, `product_id`, `colour`, `is_active`, `created_at`) VALUES
(2, 2, 'Cream', 1, '2025-11-02 06:48:13'),
(3, 3, 'Grey', 1, '2025-11-02 07:15:20');

INSERT INTO `variation_images` (`id`, `variation_id`, `image_filename`, `sort_order`, `created_at`) VALUES
(1, 2, 'assets/images/products/stirlings-blazer/2/img_69068e2ded3ef4.94023519.jpg', 0, '2025-11-02 06:48:13'),
(2, 3, 'assets/images/products/miznons-checkered-blazer/3/img_69069488a42bd0.50304460.jpg', 0, '2025-11-02 07:15:20');

INSERT INTO `variation_sizes` (`id`, `variation_id`, `size`, `stock_quantity`, `created_at`) VALUES
(3, 2, '46', 2, '2025-11-02 06:48:13'),
(4, 2, '48', 3, '2025-11-02 06:48:13'),
(5, 3, '44', 5, '2025-11-02 07:15:20'),
(6, 3, '46', 2, '2025-11-02 07:15:20');

CREATE TABLE returns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  customer_id INT NOT NULL,
  return_number VARCHAR(50) NOT NULL UNIQUE,
  return_status ENUM('pending', 'approved', 'rejected', 'items_received', 'completed', 'refunded') 
    NOT NULL DEFAULT 'pending',
  return_total DECIMAL(10,2) NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at DATETIME,
  rejected_at DATETIME,
  items_received_at DATETIME,
  completed_at DATETIME,
  refunded_at DATETIME,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX(order_id),
  INDEX(customer_id),
  INDEX(return_number),
  INDEX(return_status),
  INDEX(requested_at)
);

CREATE TABLE return_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  order_item_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  INDEX(return_id),
  INDEX(order_item_id)
);
