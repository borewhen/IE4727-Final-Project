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
(3, 4, 'Miznon\'s Checkered Blazer', 'miznons-checkered-blazer', 'Take style up a level with the checkered patterns.', 659.00, '0', '', 'assets/images/products/miznons-checkered-blazer/main.jpg', 1, 0, '2025-11-02 07:15:20', '2025-11-02 07:15:20'),
(4, 2, 'Kamakura Shirt', 'kamakura-shirt', 'The finest from Kamakura Shirts, Japan.', 79.00, 'Kamakura', 'Cotton', 'assets/images/products/kamakura-shirt/main.jpg', 1, 0, '2025-11-03 11:14:51', '2025-11-03 11:15:57'),
(6, 1, 'Joseph Cheaney Oxfords', 'joseph-cheaney-oxfords', 'Only the best from the well-known shoemakers, Joseph Cheaney.', 800.00, 'Joseph Cheaney', 'Calf', 'assets/images/products/joseph-cheaney-oxfords/main.jpg', 1, 0, '2025-11-03 11:24:27', '2025-11-03 11:25:33');

INSERT INTO `product_variations` (`id`, `product_id`, `colour`, `is_active`, `created_at`) VALUES
(2, 2, 'Cream', 1, '2025-11-02 06:48:13'),
(3, 3, 'Grey', 1, '2025-11-02 07:15:20'),
(4, 4, 'White', 1, '2025-11-03 11:14:51'),
(5, 4, 'Navy', 1, '2025-11-03 11:14:51'),
(6, 6, 'Black', 1, '2025-11-03 11:24:27'),
(7, 6, 'Brown', 1, '2025-11-03 11:24:27'),
(8, 7, 'Sand', 1, '2025-11-03 11:29:40'),
(9, 7, 'Basket Weave Brown', 1, '2025-11-03 11:29:40'),
(10, 7, 'Basket Weave White', 1, '2025-11-03 11:29:40'),
(11, 8, 'Beige', 1, '2025-11-03 11:35:51'),
(12, 8, 'Navy', 1, '2025-11-03 11:35:51');

INSERT INTO `variation_images` (`id`, `variation_id`, `image_filename`, `sort_order`, `created_at`) VALUES
(1, 2, 'assets/images/products/stirlings-blazer/2/img_69068e2ded3ef4.94023519.jpg', 0, '2025-11-02 06:48:13'),
(2, 3, 'assets/images/products/miznons-checkered-blazer/3/img_69069488a42bd0.50304460.jpg', 0, '2025-11-02 07:15:20'),
(3, 4, 'assets/images/products/kamakura-shirt/4/img_69081e2b073621.82521524.jpg', 0, '2025-11-03 11:14:51'),
(4, 4, 'assets/images/products/kamakura-shirt/4/img_69081e2b086304.48033206.jpg', 0, '2025-11-03 11:14:51'),
(5, 4, 'assets/images/products/kamakura-shirt/4/img_69081e2b08c875.64247383.jpg', 0, '2025-11-03 11:14:51'),
(6, 5, 'assets/images/products/kamakura-shirt/5/img_69081e2b09fab4.54652313.jpg', 0, '2025-11-03 11:14:51'),
(7, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf100f3.82273216.jpg', 0, '2025-11-03 11:24:27'),
(8, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf122b5.91784356.jpg', 0, '2025-11-03 11:24:27'),
(9, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf146a6.56424877.jpg', 0, '2025-11-03 11:24:27'),
(10, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf163b3.01208576.jpg', 0, '2025-11-03 11:24:27'),
(11, 6, 'assets/images/products/joseph-cheaney-oxfords/6/img_6908206bf17908.49413410.jpg', 0, '2025-11-03 11:24:27'),
(12, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf19f37.94130483.jpg', 0, '2025-11-03 11:24:27'),
(13, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf1b662.83405192.jpg', 0, '2025-11-03 11:24:27'),
(14, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf1d466.51964347.jpg', 0, '2025-11-03 11:24:27'),
(15, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf1ebc1.77565902.jpg', 0, '2025-11-03 11:24:27'),
(16, 7, 'assets/images/products/joseph-cheaney-oxfords/7/img_6908206bf20197.04757939.jpg', 0, '2025-11-03 11:24:27'),
(17, 8, 'assets/images/products/haru-loafers/8/img_690821a42bb7e0.40630042.jpg', 0, '2025-11-03 11:29:40'),
(18, 8, 'assets/images/products/haru-loafers/8/img_690821a42bff54.37251952.jpg', 0, '2025-11-03 11:29:40'),
(19, 8, 'assets/images/products/haru-loafers/8/img_690821a42c3d66.73598202.jpg', 0, '2025-11-03 11:29:40'),
(20, 8, 'assets/images/products/haru-loafers/8/img_690821a42c7637.47056749.jpg', 0, '2025-11-03 11:29:40'),
(21, 8, 'assets/images/products/haru-loafers/8/img_690821a42cd462.98173482.jpg', 0, '2025-11-03 11:29:40'),
(22, 9, 'assets/images/products/haru-loafers/9/img_690821a42d9866.00082481.jpg', 0, '2025-11-03 11:29:40'),
(23, 10, 'assets/images/products/haru-loafers/10/img_690821a42df655.95921641.jpg', 0, '2025-11-03 11:29:40'),
(24, 11, 'assets/images/products/echizenya-cotton-pants/11/img_69082317dbbcd1.76995365.jpg', 0, '2025-11-03 11:35:51'),
(25, 12, 'assets/images/products/echizenya-cotton-pants/12/img_69082317dcea52.17521766.jpg', 0, '2025-11-03 11:35:51');

INSERT INTO `variation_sizes` (`id`, `variation_id`, `size`, `stock_quantity`, `created_at`) VALUES
(3, 2, '46', 2, '2025-11-02 06:48:13'),
(4, 2, '48', 3, '2025-11-02 06:48:13'),
(5, 3, '44', 5, '2025-11-02 07:15:20'),
(6, 3, '46', 2, '2025-11-02 07:15:20'),
(7, 4, 'S', 1, '2025-11-03 11:14:51'),
(8, 4, 'M', 2, '2025-11-03 11:14:51'),
(9, 4, 'L', 3, '2025-11-03 11:14:51'),
(10, 5, 'S', 3, '2025-11-03 11:14:51'),
(11, 5, 'M', 3, '2025-11-03 11:14:51'),
(12, 5, 'L', 3, '2025-11-03 11:14:51'),
(13, 6, 'US 8', 1, '2025-11-03 11:24:27'),
(14, 6, 'US 9', 2, '2025-11-03 11:24:27'),
(15, 6, 'US 10', 3, '2025-11-03 11:24:27'),
(16, 7, '10', 5, '2025-11-03 11:24:27'),
(17, 8, 'US 9', 2, '2025-11-03 11:29:40'),
(18, 9, 'US 8', 2, '2025-11-03 11:29:40'),
(19, 10, 'US 8', 1, '2025-11-03 11:29:40'),
(20, 11, '32', 1, '2025-11-03 11:35:51'),
(21, 12, '32', 3, '2025-11-03 11:35:51');

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
