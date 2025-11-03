<?php
require_once __DIR__ . '/check_session.php';

// Must be logged in to add to cart
if (!isLoggedIn()) {
  // Optionally set a redirect back after login
  header('Location: login.php');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: products.php');
  exit();
}

$user = getCurrentUser();

// CSRF check
$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
  $_SESSION['cart_flash_error'] = 'Invalid request. Please try again.';
  header('Location: products.php');
  exit();
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$productSlugFromPost = isset($_POST['product_slug']) ? trim($_POST['product_slug']) : '';
$color = isset($_POST['color']) ? trim($_POST['color']) : '';
$size = isset($_POST['size']) ? trim($_POST['size']) : '';
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

if ($productId <= 0 || $color === '' || $size === '') {
  $_SESSION['cart_flash_error'] = 'Please select a color and size.';
  $fallbackSlug = $productSlugFromPost !== '' ? $productSlugFromPost : ($_GET['slug'] ?? '');
  header('Location: product.php?slug=' . urlencode($fallbackSlug));
  exit();
}

$conn = getDBConnection();

// Ensure the current session user exists in customers (FK safety)
$stmt = $conn->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$customerExists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$customerExists) {
  $_SESSION['cart_flash_error'] = 'Your session needs to be refreshed. Please log in again.';
  $conn->close();
  header('Location: login.php');
  exit();
}

// Validate product is active and get slug for redirect
$stmt = $conn->prepare('SELECT id, slug, name FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
  $_SESSION['cart_flash_error'] = 'This product is not available.';
  $conn->close();
  header('Location: products.php');
  exit();
}

// Find variation by color
$stmt = $conn->prepare('SELECT id FROM product_variations WHERE product_id = ? AND LOWER(TRIM(colour)) = LOWER(TRIM(?)) AND is_active = 1 LIMIT 1');
$stmt->bind_param('is', $productId, $color);
$stmt->execute();
$varRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$varRow) {
  $_SESSION['cart_flash_error'] = 'Selected color is unavailable.';
  $conn->close();
  header('Location: product.php?slug=' . urlencode($product['slug']));
  exit();
}

$variationId = (int)$varRow['id'];

// Validate size and stock
$stmt = $conn->prepare('SELECT id, stock_quantity FROM variation_sizes WHERE variation_id = ? AND LOWER(TRIM(size)) = LOWER(TRIM(?)) LIMIT 1');
$stmt->bind_param('is', $variationId, $size);
$stmt->execute();
$sizeRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sizeRow) {
  $_SESSION['cart_flash_error'] = 'Selected size is unavailable.';
  $conn->close();
  header('Location: product.php?slug=' . urlencode($product['slug']) . '&color=' . urlencode($color));
  exit();
}

$variationSizeId = (int)$sizeRow['id'];
$stockQty = (int)$sizeRow['stock_quantity'];
if ($stockQty <= 0) {
  $_SESSION['cart_flash_error'] = 'This size is out of stock.';
  $conn->close();
  header('Location: product.php?slug=' . urlencode($product['slug']) . '&color=' . urlencode($color));
  exit();
}

// Check if item already exists in cart (same product/color/size)
$stmt = $conn->prepare('SELECT id, quantity FROM cart_items WHERE customer_id = ? AND product_id = ? AND variation_id = ? AND variation_size_id = ? LIMIT 1');
$stmt->bind_param('iiii', $user['id'], $productId, $variationId, $variationSizeId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
  $newQty = min(99, $existing['quantity'] + $quantity);
  // Optional: enforce stock cap
  $newQty = min($newQty, $stockQty);
  $stmt = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND customer_id = ?');
  $stmt->bind_param('iii', $newQty, $existing['id'], $user['id']);
  $ok = $stmt->execute();
  $stmt->close();
} else {
  $qtyToInsert = min($quantity, $stockQty);
  $stmt = $conn->prepare('INSERT INTO cart_items (customer_id, product_id, variation_id, variation_size_id, size, color, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $stmt->bind_param('iiiissi', $user['id'], $productId, $variationId, $variationSizeId, $size, $color, $qtyToInsert);
  $ok = $stmt->execute();
  $stmt->close();
}

$conn->close();

if (!empty($ok)) {
  $_SESSION['cart_flash_success'] = 'Added to cart: ' . htmlspecialchars($product['name']) . ' (' . htmlspecialchars($color) . ', ' . htmlspecialchars($size) . ')';
  header('Location: cart.php');
  exit();
}

$_SESSION['cart_flash_error'] = 'Could not add item to cart. Please try again.';
header('Location: product.php?slug=' . urlencode($product['slug']));
exit();
?>


