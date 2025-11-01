<?php
/*
require_once 'check_session.php';

// Require login for cart
requireLogin();

$user = getCurrentUser();
$success_message = '';
$error_message = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Update quantity
        if ($action === 'update' && isset($_POST['cart_item_id']) && isset($_POST['quantity'])) {
            $cart_item_id = intval($_POST['cart_item_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($quantity > 0 && $quantity <= 99) {
                $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND customer_id = ?");
                $stmt->bind_param("iii", $quantity, $cart_item_id, $user['id']);
                
                if ($stmt->execute()) {
                    $success_message = 'Cart updated successfully';
                } else {
                    $error_message = 'Failed to update cart';
                }
                $stmt->close();
            } else {
                $error_message = 'Invalid quantity';
            }
        }
        
        // Remove item
        if ($action === 'remove' && isset($_POST['cart_item_id'])) {
            $cart_item_id = intval($_POST['cart_item_id']);
            
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $cart_item_id, $user['id']);
            
            if ($stmt->execute()) {
                $success_message = 'Item removed from cart';
            } else {
                $error_message = 'Failed to remove item';
            }
            $stmt->close();
        }
        
        // Clear cart
        if ($action === 'clear') {
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ?");
            $stmt->bind_param("i", $user['id']);
            
            if ($stmt->execute()) {
                $success_message = 'Cart cleared';
            } else {
                $error_message = 'Failed to clear cart';
            }
            $stmt->close();
        }
    }
    
    $conn->close();
}

// Get cart items
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT 
        ci.id as cart_item_id,
        ci.quantity,
        ci.size,
        ci.color,
        p.id as product_id,
        p.name,
        p.price,
        p.image_filename,
        p.stock_quantity,
        p.brand,
        (ci.quantity * p.price) as subtotal
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.customer_id = ? AND p.is_active = 1
    ORDER BY ci.added_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Calculate totals
$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['subtotal'];
}

$tax_rate = 0.08; // 8% tax
$tax_amount = $cart_total * $tax_rate;
$shipping_fee = $cart_total > 100 ? 0 : 10.00; // Free shipping over $100
$grand_total = $cart_total + $tax_amount + $shipping_fee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Menswear Store</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/cart.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">Menswear Store</a>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Products</a></li>
                <li><a href="cart.php" class="active">Cart (<?php echo count($cart_items); ?>)</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin_dashboard.php">Admin</a></li>
                <?php endif; ?>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($user['name']); ?></span>
            </div>
        </div>
    </nav>

    <div class="cart-container">
        <h1>Shopping Cart</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h2>Your cart is empty</h2>
                <p>Start adding items to your cart!</p>
                <a href="products.php" class="btn-primary">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="cart-content">
                <!-- Cart Items -->
                <div class="cart-items-section">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if (!empty($item['image_filename'])): ?>
                                    <img src="images/products/<?php echo htmlspecialchars($item['image_filename']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="no-image">No Image</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="item-brand"><?php echo htmlspecialchars($item['brand']); ?></p>
                                
                                <?php if (!empty($item['size'])): ?>
                                    <p class="item-variant"><strong>Size:</strong> <?php echo htmlspecialchars($item['size']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['color'])): ?>
                                    <p class="item-variant"><strong>Color:</strong> <?php echo htmlspecialchars($item['color']); ?></p>
                                <?php endif; ?>
                                
                                <p class="item-price">$<?php echo number_format($item['price'], 2); ?></p>
                                
                                <?php if ($item['stock_quantity'] < 5): ?>
                                    <p class="low-stock">⚠️ Only <?php echo $item['stock_quantity']; ?> left in stock!</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-actions">
                                <form method="POST" class="quantity-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <div class="quantity-control">
                                        <button type="button" class="qty-btn" onclick="decrementQty(this)">−</button>
                                        <input 
                                            type="number" 
                                            name="quantity" 
                                            value="<?php echo $item['quantity']; ?>" 
                                            min="1" 
                                            max="<?php echo $item['stock_quantity']; ?>"
                                            class="qty-input"
                                            onchange="this.form.submit()"
                                        >
                                        <button type="button" class="qty-btn" onclick="incrementQty(this, <?php echo $item['stock_quantity']; ?>)">+</button>
                                    </div>
                                </form>
                                
                                <p class="item-subtotal">
                                    <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
                                </p>
                                
                                <form method="POST" onsubmit="return confirm('Remove this item from cart?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <button type="submit" class="btn-remove">🗑️ Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="cart-footer-actions">
                        <a href="products.php" class="btn-secondary">← Continue Shopping</a>
                        <form method="POST" onsubmit="return confirm('Clear all items from cart?');" style="display: inline;">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="btn-danger">Clear Cart</button>
                        </form>
                    </div>
                </div>
                
                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo count($cart_items); ?> items)</span>
                        <span>$<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Tax (8%)</span>
                        <span>$<?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>
                            <?php if ($shipping_fee > 0): ?>
                                $<?php echo number_format($shipping_fee, 2); ?>
                            <?php else: ?>
                                <span class="free-shipping">FREE</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($cart_total < 100 && $cart_total > 0): ?>
                        <div class="shipping-notice">
                            💡 Add $<?php echo number_format(100 - $cart_total, 2); ?> more for free shipping!
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span>$<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    
                    <a href="checkout.php" class="btn-checkout">Proceed to Checkout →</a>
                    
                    <div class="payment-methods">
                        <p>We accept:</p>
                        <div class="payment-icons">
                            💳 Visa | 💳 Mastercard | 💳 Amex
                        </div>
                    </div>
                    
                    <div class="security-badge">
                        🔒 Secure Checkout
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/cart.js"></script>
</body>
</html>
*/
require_once 'check_session.php';

// Require login for cart
requireLogin();

$user = getCurrentUser();
$success_message = '';
$error_message = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Update quantity
        if ($action === 'update' && isset($_POST['cart_item_id']) && isset($_POST['quantity'])) {
            $cart_item_id = intval($_POST['cart_item_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($quantity > 0 && $quantity <= 99) {
                $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND customer_id = ?");
                $stmt->bind_param("iii", $quantity, $cart_item_id, $user['id']);
                
                if ($stmt->execute()) {
                    $success_message = 'Cart updated successfully';
                } else {
                    $error_message = 'Failed to update cart';
                }
                $stmt->close();
            } else {
                $error_message = 'Invalid quantity';
            }
        }
        
        // Remove item
        if ($action === 'remove' && isset($_POST['cart_item_id'])) {
            $cart_item_id = intval($_POST['cart_item_id']);
            
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND customer_id = ?");
            $stmt->bind_param("ii", $cart_item_id, $user['id']);
            
            if ($stmt->execute()) {
                $success_message = 'Item removed from cart';
            } else {
                $error_message = 'Failed to remove item';
            }
            $stmt->close();
        }
        
        // Clear cart
        if ($action === 'clear') {
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ?");
            $stmt->bind_param("i", $user['id']);
            
            if ($stmt->execute()) {
                $success_message = 'Cart cleared';
            } else {
                $error_message = 'Failed to clear cart';
            }
            $stmt->close();
        }
    }
    
    $conn->close();
}

// Get cart items
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT 
        ci.id as cart_item_id,
        ci.quantity,
        ci.size,
        ci.color,
        p.id as product_id,
        p.name,
        p.price,
        p.image_filename,
        p.stock_quantity,
        p.brand,
        (ci.quantity * p.price) as subtotal
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.customer_id = ? AND p.is_active = 1
    ORDER BY ci.added_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Calculate totals
$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['subtotal'];
}

$tax_rate = 0.08; // 8% tax
$tax_amount = $cart_total * $tax_rate;
$shipping_fee = $cart_total > 100 ? 0 : 10.00; // Free shipping over $100
$grand_total = $cart_total + $tax_amount + $shipping_fee;

require __DIR__ . '/partials/header.php';
?>

<main id="main">
  <section class="cart-hero container" aria-label="Shopping Cart">
    <h1 class="cart-title">Shopping Cart</h1>
    <p class="cart-subtitle">Review your items before checkout</p>
  </section>

  <?php if (!empty($success_message)): ?>
    <div class="container">
      <div class="message message--success">
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($error_message)): ?>
    <div class="container">
      <div class="message message--error">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    </div>
  <?php endif; ?>
  
  <?php if (empty($cart_items)): ?>
    <section class="empty-cart container">
      <div class="empty-cart__content">
        <div class="empty-cart__icon" aria-hidden="true">🛒</div>
        <h2>Your cart is empty</h2>
        <p>Start adding items to your cart!</p>
        <a href="products.php" class="btn btn--primary">Browse Products</a>
      </div>
    </section>
  <?php else: ?>
    <div class="cart-layout container">
      <!-- Cart Items -->
      <section class="cart-items" aria-label="Cart items">
        <?php foreach ($cart_items as $item): ?>
          <article class="cart-item">
            <div class="cart-item__image">
              <?php if (!empty($item['image_filename'])): ?>
                <img src="images/products/<?php echo htmlspecialchars($item['image_filename']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
              <?php else: ?>
                <div class="cart-item__placeholder" aria-hidden="true"></div>
              <?php endif; ?>
            </div>
            
            <div class="cart-item__details">
              <h3 class="cart-item__title"><?php echo htmlspecialchars($item['name']); ?></h3>
              <?php if (!empty($item['brand'])): ?>
                <p class="cart-item__brand"><?php echo htmlspecialchars($item['brand']); ?></p>
              <?php endif; ?>
              
              <div class="cart-item__meta">
                <?php if (!empty($item['size'])): ?>
                  <span class="cart-item__variant">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                <?php endif; ?>
                
                <?php if (!empty($item['color'])): ?>
                  <span class="cart-item__variant">Color: <?php echo htmlspecialchars($item['color']); ?></span>
                <?php endif; ?>
              </div>
              
              <p class="cart-item__price">$<?php echo number_format($item['price'], 2); ?></p>
              
              <?php if ($item['stock_quantity'] < 5): ?>
                <p class="cart-item__stock-warning">Only <?php echo $item['stock_quantity']; ?> left in stock</p>
              <?php endif; ?>
            </div>
            
            <div class="cart-item__actions">
              <form method="POST" class="qty-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                <div class="qty-control">
                  <button type="button" class="qty-btn" onclick="decrementQty(this)" aria-label="Decrease quantity">−</button>
                  <input 
                    type="number" 
                    name="quantity" 
                    value="<?php echo $item['quantity']; ?>" 
                    min="1" 
                    max="<?php echo $item['stock_quantity']; ?>"
                    class="qty-input"
                    onchange="this.form.submit()"
                    aria-label="Quantity"
                  >
                  <button type="button" class="qty-btn" onclick="incrementQty(this, <?php echo $item['stock_quantity']; ?>)" aria-label="Increase quantity">+</button>
                </div>
              </form>
              
              <p class="cart-item__subtotal">
                <strong>$<?php echo number_format($item['subtotal'], 2); ?></strong>
              </p>
              
              <form method="POST" onsubmit="return confirm('Remove this item from cart?');">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                <button type="submit" class="btn-link btn-link--danger">Remove</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
        
        <div class="cart-footer-actions">
          <a href="products.php" class="link">← Continue Shopping</a>
          <form method="POST" onsubmit="return confirm('Clear all items from cart?');" style="display: inline;">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn-link btn-link--danger">Clear Cart</button>
          </form>
        </div>
      </section>
      
      <!-- Cart Summary -->
      <aside class="cart-summary" aria-label="Order summary">
        <h2 class="cart-summary__title">Order Summary</h2>
        
        <div class="summary-row">
          <span>Subtotal (<?php echo count($cart_items); ?> items)</span>
          <span>$<?php echo number_format($cart_total, 2); ?></span>
        </div>
        
        <div class="summary-row">
          <span>Tax (8%)</span>
          <span>$<?php echo number_format($tax_amount, 2); ?></span>
        </div>
        
        <div class="summary-row">
          <span>Shipping</span>
          <span>
            <?php if ($shipping_fee > 0): ?>
              $<?php echo number_format($shipping_fee, 2); ?>
            <?php else: ?>
              <span class="free-badge">FREE</span>
            <?php endif; ?>
          </span>
        </div>
        
        <?php if ($cart_total < 100 && $cart_total > 0): ?>
          <div class="shipping-notice">
            Add $<?php echo number_format(100 - $cart_total, 2); ?> more for free shipping
          </div>
        <?php endif; ?>
        
        <div class="summary-divider"></div>
        
        <div class="summary-row summary-row--total">
          <span>Total</span>
          <span>$<?php echo number_format($grand_total, 2); ?></span>
        </div>
        
        <a href="checkout.php" class="btn btn--primary btn--full">Proceed to Checkout</a>
        
        <div class="payment-info">
          <p class="payment-info__text">We accept all major cards</p>
          <div class="security-badge">
            🔒 Secure Checkout
          </div>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</main>

<script src="assets/js/cart.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>