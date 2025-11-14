<?php
require_once 'check_session.php';

// Require login
requireLogin();

$user = getCurrentUser();
$order_number = isset($_GET['order']) ? trim($_GET['order']) : '';

if (empty($order_number)) {
    header('Location: index.php');
    exit();
}

// Get order details (using your exact schema)
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT 
        o.*
    FROM orders o
    WHERE o.order_number = ? AND o.customer_id = ?
");
$stmt->bind_param("si", $order_number, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: index.php');
    exit();
}

// Get order items (using your exact schema)
$stmt = $conn->prepare("
    SELECT 
        oi.*
    FROM order_items oi
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order['id']);
$stmt->execute();
$result = $stmt->get_result();
$order_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$elapsedStmt = $conn->prepare("SELECT GREATEST(0, 120 - TIMESTAMPDIFF(SECOND, created_at, NOW())) AS remaining FROM orders WHERE id = ? LIMIT 1");
$elapsedStmt->bind_param('i', $order['id']);
$elapsedStmt->execute();
$elapsedRes = $elapsedStmt->get_result()->fetch_assoc();
$elapsedStmt->close();
$remainingSeconds = isset($elapsedRes['remaining']) ? (int)$elapsedRes['remaining'] : 0;
$isWindowOpen = ($remainingSeconds > 0) && ($order['order_status'] === 'pending');
$conn->close();

// Calculate subtotal (order_total includes tax and shipping)
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['line_total'];
}

$tax_rate = 0.09;
$tax_amount = $subtotal * $tax_rate;
$shipping_fee = $subtotal > 100 ? 0 : 10.00;

require __DIR__ . '/partials/header.php';
?>

<main id="main">
  <section class="confirmation-hero container" aria-label="Order Confirmation">
    <h1 class="confirmation-title">Order Confirmed</h1>
    <p class="confirmation-subtitle">Thank you for your purchase, <?php echo htmlspecialchars(explode(' ', $order['customer_name'])[0]); ?>!</p>
  </section>

  <?php if ($isWindowOpen): ?>
    <div class="container">
      <div class="message" id="editWindowMsg" style="display:flex; align-items:center; justify-content:space-between; gap:.75rem;">
        <div>
          You can review or change your order for the next <strong><span id="remainingTime"><?php echo gmdate('i:s', $remainingSeconds); ?></span></strong>.
        </div>
        <a class="btn btn--primary" href="order_review.php?order=<?php echo urlencode($order['order_number']); ?>">Review / Edit</a>
      </div>
    </div>
    <script>
      (function(){
        var remaining = <?php echo (int)$remainingSeconds; ?>;
        var el = document.getElementById('remainingTime');
        var wrap = document.getElementById('editWindowMsg');
        function tick(){
          remaining -= 1;
          if (remaining <= 0) {
            if (wrap) wrap.style.display = 'none';
            return;
          }
          var m = Math.floor(remaining / 60);
          var s = remaining % 60;
          if (el) el.textContent = String(m).padStart(1,'0') + ':' + String(s).padStart(2,'0');
          setTimeout(tick, 1000);
        }
        setTimeout(tick, 1000);
      })();
    </script>
  <?php endif; ?>

  <div class="confirmation-layout container">
    <div class="confirmation-main">
      <!-- Order Details Card -->
      <section class="confirmation-card">
        <h2 class="card-title">Order Details</h2>
        
        <div class="order-info-grid">
          <div class="info-item">
            <div class="info-label">Order Number</div>
            <div class="info-value"><?php echo htmlspecialchars($order['order_number']); ?></div>
          </div>
          
          <div class="info-item">
            <div class="info-label">Order Date</div>
            <div class="info-value"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
          </div>
          
          <div class="info-item">
            <div class="info-label">Order Status</div>
            <div class="info-value">
              <span class="status-badge status-<?php echo $order['order_status']; ?>">
                <?php echo ucfirst($order['order_status']); ?>
              </span>
            </div>
          </div>  
        </div>
        <div class="info-item">
          <h2 class="card-title">Customer Information</h2>
          <div class="address-content">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
            <p><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
            <?php if (!empty($order['special_instructions'])): ?>
              <p><strong>Special Instructions:</strong> <?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
            <?php endif; ?>
          </div>
        </div>
      </section>
      
        <!-- Order Items Card -->
        <section class="confirmation-card">
          <h2 class="card-title">Order Items</h2>
          
          <div class="order-items-list">
            <?php foreach ($order_items as $item): ?>
              <div class="order-item">
                <div class="order-item__image">
                <?php if (!empty($item['product_image_filename'])): ?>
                  <img src="<?php echo htmlspecialchars($item['product_image_filename']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                  <?php else: ?>
                    <div class="order-item__placeholder"></div>
                  <?php endif; ?>
                </div>
                
                <div class="order-item__details">
                  <div class="order-item__name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                  <div class="order-item__meta">
                    <?php if (!empty($item['size'])): ?>
                      <span>Size: <?php echo htmlspecialchars($item['size']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($item['color'])): ?>
                      <span>Color: <?php echo htmlspecialchars($item['color']); ?></span>
                    <?php endif; ?>
                    <span>Qty: <?php echo $item['quantity']; ?></span>
                  </div>
                  <div class="order-item__price">$<?php echo number_format($item['unit_price'], 2); ?> each</div>
                </div>
                
                <div class="order-item__total">
                  $<?php echo number_format($item['line_total'], 2); ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <br>
          <div class="summary-row">
            <span>Subtotal</span>
            <span>$<?php echo number_format($subtotal, 2); ?></span>
          </div>
          
          <div class="summary-row">
            <span>Tax (9%)</span>
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

          <div class="summary-divider"></div>
          
          <div class="summary-row summary-row--total">
            <span>Total</span>
            <span>$<?php echo number_format($order['order_total'], 2); ?></span>
          </div>
        </section>
      </div>

      <section>
        <h2 class="card-title">What's Next?</h2>
        <div class="next-steps">
          <p>We've sent a confirmation email to <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong></p>
          <p>Your order will be processed within 1-2 business days, and you'll receive tracking information once shipped.</p>
        </div>
      </section>

      <div class="confirmation-actions">
        <a href="products.php" class="btn btn--primary btn--full">Continue Shopping</a>
      </div>
    </aside>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>