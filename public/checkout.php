<?php
require_once 'check_session.php';

requireLogin();

$user = getCurrentUser();
$error_message = '';
$success_message = '';

function debug_log($message) {
    error_log('[CHECKOUT DEBUG] ' . $message);
}

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT 
        ci.id as cart_item_id,
        ci.quantity,
        ci.size,
        ci.color,
        ci.variation_id,
        ci.variation_size_id,
        p.id as product_id,
        p.name,
        p.price,
        p.image_filename,
        p.brand,
        (ci.quantity * p.price) as subtotal,
        (SELECT vs.stock_quantity FROM variation_sizes vs WHERE vs.id = ci.variation_size_id LIMIT 1) AS variant_stock
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

// If cart is empty, redirect back to cart
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['subtotal'];
}

$tax_rate = 0.09;
$tax_amount = $cart_total * $tax_rate;
$shipping_fee = $cart_total > 100 ? 0 : 10.00;
$grand_total = $cart_total + $tax_amount + $shipping_fee;

// Get user's saved address
$stmt = $conn->prepare("
    SELECT shipping_address, phone, first_name, last_name, email
    FROM customers 
    WHERE id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$customer_data = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    debug_log('Form submitted');
    debug_log('POST data: ' . print_r($_POST, true));
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid security token. Please try again.';
        debug_log('CSRF token validation failed');
    } else {
        debug_log('CSRF token validated');
        
        // Get form data
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $special_instructions = trim($_POST['special_instructions'] ?? '');
        
        debug_log("Shipping: $shipping_address, Phone: $phone");
        
        // Validate
        $validation_errors = [];
        
        if (empty($shipping_address)) {
            $validation_errors[] = 'Shipping address is required';
        }
        
        if (empty($phone)) {
            $validation_errors[] = 'Phone number is required';
        }
        
        if (!empty($validation_errors)) {
            $error_message = implode('<br>', $validation_errors);
            debug_log('Validation errors: ' . implode(', ', $validation_errors));
        } else {
            debug_log('Validation passed, starting transaction');
            // Start transaction
            $conn->begin_transaction();
            
            try {
                debug_log('Transaction started');
                
                $order_number = 'ORD-' . strtoupper(uniqid());
                debug_log("Generated order number: $order_number");
                
                $customer_name = $customer_data['first_name'] . ' ' . $customer_data['last_name'];
                $customer_email = $customer_data['email'];
                
                debug_log("Customer: $customer_name ($customer_email)");
                
                $stmt = $conn->prepare("
                    INSERT INTO orders (
                        customer_id, 
                        order_number, 
                        customer_email, 
                        customer_name, 
                        customer_phone, 
                        shipping_address, 
                        order_total, 
                        order_status, 
                        special_instructions,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW())
                ");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param(
                    "isssssds",
                    $user['id'],
                    $order_number,
                    $customer_email,
                    $customer_name,
                    $phone,
                    $shipping_address,
                    $grand_total,
                    $special_instructions
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Order insert failed: " . $stmt->error);
                }
                
                $order_id = $conn->insert_id;
                debug_log("Order created with ID: $order_id");
                $stmt->close();
                
                // Prepare variation image lookup
                $viStmt = $conn->prepare("SELECT image_filename FROM variation_images WHERE variation_id = ? ORDER BY sort_order, id LIMIT 1");

                // Insert order items (matching your exact schema)
                $item_count = 0;
                foreach ($cart_items as $item) {
                    $line_total = $item['subtotal'];
                    // Determine image from variation_images (fallback to product image)
                    $imageForItem = $item['image_filename'];
                    if (!empty($item['variation_id'])) {
                        $vid = (int)$item['variation_id'];
                        $viStmt->bind_param('i', $vid);
                        $viStmt->execute();
                        $viRes = $viStmt->get_result()->fetch_assoc();
                        if ($viRes && !empty($viRes['image_filename'])) {
                            $imageForItem = $viRes['image_filename'];
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO order_items (
                            order_id, 
                            product_id, 
                            product_name, 
                            product_image_filename,
                            size, 
                            color, 
                            quantity, 
                            unit_price, 
                            line_total
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if (!$stmt) {
                        throw new Exception("Prepare order items failed: " . $conn->error);
                    }
                    
                    $stmt->bind_param(
                        "iissssidd",
                        $order_id,
                        $item['product_id'],
                        $item['name'],
                        $imageForItem,
                        $item['size'],
                        $item['color'],
                        $item['quantity'],
                        $item['price'],
                        $line_total
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Order item insert failed: " . $stmt->error);
                    }
                    
                    $stmt->close();
                    $item_count++;
                    
                    // Update variant size stock (instead of product stock)
                    $stmt = $conn->prepare("
                        UPDATE variation_sizes 
                        SET stock_quantity = GREATEST(0, stock_quantity - ?) 
                        WHERE id = ?
                    ");
                    if (!$stmt) { throw new Exception("Prepare variant stock update failed: " . $conn->error); }
                    $stmt->bind_param("ii", $item['quantity'], $item['variation_size_id']);
                    if (!$stmt->execute()) { throw new Exception("Variant stock update failed: " . $stmt->error); }
                    $stmt->close();
                }
                $viStmt->close();
                debug_log("Inserted $item_count order items");
                
                // Clear cart
                $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare cart clear failed: " . $conn->error);
                }
                
                $stmt->bind_param("i", $user['id']);
                if (!$stmt->execute()) {
                    throw new Exception("Cart clear failed: " . $stmt->error);
                }
                $stmt->close();
                
                debug_log("Cart cleared");
                
                // Commit transaction
                $conn->commit();
                debug_log("Transaction committed successfully");
                
                // Send order confirmation email with 2-minute edit window
                require_once __DIR__ . '/email_config.php';
                @sendOrderConfirmationEmail($order_id);
                
                // Redirect to order confirmation
                debug_log("Redirecting to order_confirmation.php?order=$order_number");
                header('Location: order_confirmation.php?order=' . $order_number);
                exit();
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error_message = 'Failed to place order. Please try again.';
                debug_log('Order creation error: ' . $e->getMessage());
                error_log('Order creation error: ' . $e->getMessage());
            }
        }
    }
}

$conn->close();

require __DIR__ . '/partials/header.php';
?>

<main id="main">
  <section class="checkout-hero container" aria-label="Checkout">
    <h1 class="checkout-title">Checkout</h1>
    <p class="checkout-subtitle">Review and complete your order</p>
  </section>

  <?php if (!empty($error_message)): ?>
    <div class="container">
      <div class="message message--error">
        <?php echo $error_message; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="checkout-layout container">
    <!-- Left Column: Checkout Form -->
    <div class="checkout-form-section">
      <form method="POST" action="checkout.php" id="checkoutForm" class="checkout-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <!-- Hidden field to ensure place_order is always sent, even when button is disabled -->
        <input type="hidden" name="place_order" value="1">
        
        <!-- Customer Information -->
        <section class="checkout-section">
          <h2 class="section-title">Customer Information</h2>
          
          <div class="info-display">
            <div class="info-row">
              <span class="info-label">Name:</span>
              <span class="info-value"><?php echo htmlspecialchars($customer_data['first_name'] . ' ' . $customer_data['last_name']); ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Email:</span>
              <span class="info-value"><?php echo htmlspecialchars($customer_data['email']); ?></span>
            </div>
          </div>
        </section>

        <!-- Shipping Information -->
        <section class="checkout-section">
          <h2 class="section-title">Shipping Information</h2>
          
          <div class="form-group">
            <label for="shipping_address">Shipping Address <span class="required">*</span></label>
            <textarea 
              id="shipping_address" 
              name="shipping_address" 
              rows="3" 
              required
              placeholder="Enter your complete shipping address"
            ><?php echo isset($_POST['shipping_address']) ? htmlspecialchars($_POST['shipping_address']) : htmlspecialchars($customer_data['shipping_address'] ?? ''); ?></textarea>
          </div>
          
          <div class="form-group">
            <label for="phone">Phone Number <span class="required">*</span></label>
            <input 
              type="tel" 
              id="phone" 
              name="phone" 
              required
              pattern="[0-9+\s\-()]{8,20}"
              placeholder="Enter phone number"
              value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($customer_data['phone'] ?? ''); ?>"
            >
          </div>

          <div class="form-group">
            <label for="special_instructions">Special Instructions (Optional)</label>
            <textarea 
              id="special_instructions" 
              name="special_instructions" 
              rows="2" 
              placeholder="Any special delivery instructions?"
            ><?php echo isset($_POST['special_instructions']) ? htmlspecialchars($_POST['special_instructions']) : ''; ?></textarea>
          </div>
        </section>

        <button type="submit" name="place_order" class="btn btn--primary btn--full btn--large">
          Place Order - $<?php echo number_format($grand_total, 2); ?>
        </button>
      </form>
    </div>

    <!-- Right Column: Order Summary -->
    <aside class="checkout-summary" aria-label="Order summary">
      <h2 class="summary-title">Order Summary</h2>
      
      <div class="summary-items">
        <?php foreach ($cart_items as $item): ?>
          <div class="summary-item">
            <div class="summary-item__details">
              <div class="summary-item__name"><?php echo htmlspecialchars($item['name']); ?></div>
              <div class="summary-item__meta">
                <?php if (!empty($item['size'])): ?>
                  <span>Size: <?php echo htmlspecialchars($item['size']); ?></span>
                <?php endif; ?>
                <?php if (!empty($item['color'])): ?>
                  <span>Color: <?php echo htmlspecialchars($item['color']); ?></span>
                <?php endif; ?>
                <span>Qty: <?php echo $item['quantity']; ?></span>
              </div>
            </div>
            <div class="summary-item__price">
              $<?php echo number_format($item['subtotal'], 2); ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="summary-divider"></div>

      <div class="summary-row">
        <span>Subtotal</span>
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
    </aside>
  </div>
</main>

<script src="assets/js/checkout.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>