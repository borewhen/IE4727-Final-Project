<?php
require_once 'config.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($token === '') {
  header('Location: index.php');
  exit();
}

$conn = getDBConnection();

// Validate token and load order
$stmt = $conn->prepare("SELECT t.order_id, t.expires_at, o.order_number, o.customer_name, o.customer_email, o.shipping_address, o.order_total, o.order_status, o.payment_status, o.created_at FROM order_edit_tokens t INNER JOIN orders o ON o.id = t.order_id WHERE t.token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$tok = $stmt->get_result()->fetch_assoc();
$stmt->close();

$now = time();
$expired = true;
if ($tok) {
  $expired = ($now > strtotime($tok['expires_at']));
}

if (!$tok) {
  require __DIR__ . '/partials/header.php';
  echo '<main id="main" class="container" style="padding:2rem 0;"><h1>Invalid Link</h1><p>The link is invalid. Please contact support.</p></main>';
  require __DIR__ . '/partials/footer.php';
  exit();
}

// Handle actions (POST preferred); support GET cancel quick action
if (!$expired) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = isset($_POST['action']) ? $_POST['action'] : '';
    if ($do === 'update') {
      $addr = isset($_POST['shipping_address']) ? trim($_POST['shipping_address']) : '';
      if ($addr !== '') {
        $u = $conn->prepare('UPDATE orders SET shipping_address = ? WHERE id = ?');
        $u->bind_param('si', $addr, $tok['order_id']);
        $u->execute();
        $u->close();
        $tok['shipping_address'] = $addr;
        $message = 'Shipping address updated.';
      }
    } elseif ($do === 'cancel') {
      $u = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
      $u->bind_param('i', $tok['order_id']);
      $u->execute();
      $u->close();
      $tok['order_status'] = 'cancelled';
      $message = 'Order cancelled.';
    }
  }
  if ($action === 'cancel') {
    $u = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ?");
    $u->bind_param('i', $tok['order_id']);
    $u->execute();
    $u->close();
    $tok['order_status'] = 'cancelled';
    $message = 'Order cancelled.';
  }
}

// Load order items
$items = [];
$is = $conn->prepare('SELECT product_name, size, color, quantity, unit_price, line_total FROM order_items WHERE order_id = ?');
$is->bind_param('i', $tok['order_id']);
$is->execute();
$r = $is->get_result();
$items = $r->fetch_all(MYSQLI_ASSOC);
$is->close();
$conn->close();

require __DIR__ . '/partials/header.php';
?>
<main id="main" class="container" style="padding:2rem 0;">
  <h1>Review Order #<?php echo htmlspecialchars($tok['order_number']); ?></h1>
  <p class="subtitle" style="margin:.25rem 0 1rem;">Link expires at <?php echo htmlspecialchars($tok['expires_at']); ?>.</p>
  <?php if (!empty($message)): ?><div class="success-message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if ($expired): ?><div class="error-message">This link has expired. You can no longer edit this order.</div><?php endif; ?>

  <div class="confirmation-layout" style="display:grid; grid-template-columns: 2fr 1fr; gap:1rem;">
    <div>
      <section class="confirmation-card">
        <h2 class="card-title">Items</h2>
        <div class="order-items-list">
          <?php foreach ($items as $item): ?>
            <div class="order-item">
              <div class="order-item__details">
                <div class="order-item__name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                <div class="order-item__meta">
                  <?php if (!empty($item['size'])): ?><span>Size: <?php echo htmlspecialchars($item['size']); ?></span><?php endif; ?>
                  <?php if (!empty($item['color'])): ?><span>Colour: <?php echo htmlspecialchars($item['color']); ?></span><?php endif; ?>
                  <span>Qty: <?php echo (int)$item['quantity']; ?></span>
                </div>
              </div>
              <div class="order-item__total">$<?php echo number_format((float)$item['line_total'], 2); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
    <aside>
      <section class="confirmation-card">
        <h2 class="card-title">Order</h2>
        <div class="summary-row"><span>Status</span><span><?php echo htmlspecialchars($tok['order_status']); ?></span></div>
        <div class="summary-row"><span>Total</span><span>$<?php echo number_format((float)$tok['order_total'], 2); ?></span></div>
      </section>
      <section class="confirmation-card">
        <h2 class="card-title">Shipping Address</h2>
        <?php if ($expired): ?>
          <div class="address-content"><?php echo nl2br(htmlspecialchars($tok['shipping_address'])); ?></div>
        <?php else: ?>
        <form method="POST" action="order_review.php?token=<?php echo urlencode($token); ?>">
          <textarea name="shipping_address" rows="4" style="width:100%;"><?php echo htmlspecialchars($tok['shipping_address']); ?></textarea>
          <div style="display:flex; gap:.5rem; margin-top:.5rem;">
            <button type="submit" name="action" value="update" class="btn btn--primary">Save Changes</button>
            <button type="submit" name="action" value="cancel" class="btn" style="background:#b3261e; color:#fff;">Cancel Order</button>
          </div>
        </form>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>


