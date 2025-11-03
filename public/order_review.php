<?php
require_once __DIR__ . '/check_session.php';

// Require login – simpler and safer than unauthenticated token-only links
requireLogin();
$user = getCurrentUser();

$conn = getDBConnection();

$orderNumber = isset($_GET['order']) ? trim($_GET['order']) : '';
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

$error_message = '';
$success_message = '';

// Resolve order by order number for current user
$order = null;
if ($orderNumber !== '') {
  $stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ? AND customer_id = ? LIMIT 1");
  $stmt->bind_param('si', $orderNumber, $user['id']);
  $stmt->execute();
  $order = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$order) { $error_message = 'Order not found.'; }
} else {
  $error_message = 'Missing order reference.';
}

if (!$order) {
  require __DIR__ . '/partials/header.php';
  echo '<main id="main" class="container"><section class="empty-cart"><div class="empty-cart__content"><h2>Order review unavailable</h2><p>' . htmlspecialchars($error_message) . '</p><a class="btn btn--primary" href="index.php">Back to Home</a></div></section></main>';
  require __DIR__ . '/partials/footer.php';
  exit();
}

// Check 2-minute edit window via MySQL TIMESTAMPDIFF to avoid TZ drift
$elapsedQ = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed FROM orders WHERE id = ? LIMIT 1");
$elapsedQ->bind_param('i', $order['id']);
$elapsedQ->execute();
$elapsedRow = $elapsedQ->get_result()->fetch_assoc();
$elapsedQ->close();
$elapsed = isset($elapsedRow['elapsed']) ? (int)$elapsedRow['elapsed'] : 9999;
$remaining = max(0, 120 - $elapsed);
$isWindowOpen = ($remaining > 0) && ($order['order_status'] === 'confirmed');

// Handle POST actions: confirm / cancel / save_changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $error_message = 'Invalid request.';
  } elseif (!$isWindowOpen) {
    $error_message = 'The edit window has expired.';
  } else {
    if ($action === 'confirm') {
      $stmt = $conn->prepare("UPDATE orders SET order_status = 'confirmed' WHERE id = ? AND customer_id = ? LIMIT 1");
      $stmt->bind_param('ii', $order['id'], $user['id']);
      $stmt->execute();
      $stmt->close();
      header('Location: order_confirmation.php?order=' . urlencode($order['order_number']));
      exit();
    }
    if ($action === 'cancel') {
      $stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE id = ? AND customer_id = ? LIMIT 1");
      $stmt->bind_param('ii', $order['id'], $user['id']);
      $stmt->execute();
      $stmt->close();
      header('Location: order_confirmation.php?order=' . urlencode($order['order_number']));
      exit();
    }
    if ($action === 'save_changes') {
      $shipping_address = trim($_POST['shipping_address'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      $special_instructions = trim($_POST['special_instructions'] ?? '');

      if ($shipping_address === '' || $phone === '') {
        $error_message = 'Shipping address and phone are required.';
      } else {
        $stmt = $conn->prepare("UPDATE orders SET shipping_address = ?, customer_phone = ?, special_instructions = ? WHERE id = ? AND customer_id = ? LIMIT 1");
        $stmt->bind_param('sssii', $shipping_address, $phone, $special_instructions, $order['id'], $user['id']);
        $stmt->execute();
        $stmt->close();
        $success_message = 'Order details updated.';
        // Refresh order
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $order['id']);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Recompute window
        $elapsedQ = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed FROM orders WHERE id = ? LIMIT 1");
        $elapsedQ->bind_param('i', $order['id']);
        $elapsedQ->execute();
        $elapsedRow = $elapsedQ->get_result()->fetch_assoc();
        $elapsedQ->close();
        $elapsed = isset($elapsedRow['elapsed']) ? (int)$elapsedRow['elapsed'] : 9999;
        $remaining = max(0, 120 - $elapsed);
        $isWindowOpen = ($remaining > 0) && ($order['order_status'] === 'confirmed');
      }
    }
    if ($action === 'change_item') {
      $order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
      $new_color = isset($_POST['color']) ? trim($_POST['color']) : '';
      $new_size = isset($_POST['size']) ? trim($_POST['size']) : '';
      if ($order_item_id <= 0 || $new_color === '' || $new_size === '') {
        $error_message = 'Please select both color and size.';
      } else {
        $conn->begin_transaction();
        try {
          // Load order item
          $oi = null;
          $stmt = $conn->prepare("SELECT id, product_id, product_name, size, color, quantity, unit_price FROM order_items WHERE id = ? AND order_id = ? LIMIT 1");
          $stmt->bind_param('ii', $order_item_id, $order['id']);
          $stmt->execute();
          $oi = $stmt->get_result()->fetch_assoc();
          $stmt->close();
          if (!$oi) { throw new Exception('Order item not found.'); }

          // Resolve OLD variation_size by product + color + size (for restock)
          $oldVarId = null; $oldVsId = null;
          $qs = $conn->prepare("SELECT pv.id FROM product_variations pv WHERE pv.product_id = ? AND LOWER(TRIM(pv.colour)) = LOWER(TRIM(?)) LIMIT 1");
          $qs->bind_param('is', $oi['product_id'], $oi['color']);
          $qs->execute();
          $r = $qs->get_result()->fetch_assoc();
          $qs->close();
          if ($r) { $oldVarId = (int)$r['id']; }
          if ($oldVarId) {
            $qs = $conn->prepare("SELECT id FROM variation_sizes WHERE variation_id = ? AND LOWER(TRIM(size)) = LOWER(TRIM(?)) LIMIT 1");
            $qs->bind_param('is', $oldVarId, $oi['size']);
            $qs->execute();
            $rs = $qs->get_result()->fetch_assoc();
            $qs->close();
            if ($rs) { $oldVsId = (int)$rs['id']; }
          }

          // Resolve NEW variation_size by product + color + size
          $newVarId = null; $newVsId = null; $newImage = null;
          $qs = $conn->prepare("SELECT pv.id FROM product_variations pv WHERE pv.product_id = ? AND LOWER(TRIM(pv.colour)) = LOWER(TRIM(?)) AND pv.is_active = 1 LIMIT 1");
          $qs->bind_param('is', $oi['product_id'], $new_color);
          $qs->execute();
          $r = $qs->get_result()->fetch_assoc();
          $qs->close();
          if (!$r) { throw new Exception('Selected color unavailable.'); }
          $newVarId = (int)$r['id'];
          $qs = $conn->prepare("SELECT id, stock_quantity FROM variation_sizes WHERE variation_id = ? AND LOWER(TRIM(size)) = LOWER(TRIM(?)) LIMIT 1");
          $qs->bind_param('is', $newVarId, $new_size);
          $qs->execute();
          $rs = $qs->get_result()->fetch_assoc();
          $qs->close();
          if (!$rs) { throw new Exception('Selected size unavailable.'); }
          $newVsId = (int)$rs['id'];
          $newStock = (int)$rs['stock_quantity'];
          if ($newStock < (int)$oi['quantity']) { throw new Exception('Insufficient stock for selected variant.'); }

          // Get first image for new variation (optional)
          $qi = $conn->prepare("SELECT image_filename FROM variation_images WHERE variation_id = ? ORDER BY sort_order, id LIMIT 1");
          $qi->bind_param('i', $newVarId);
          $qi->execute();
          $imgRow = $qi->get_result()->fetch_assoc();
          $qi->close();
          if ($imgRow) { $newImage = $imgRow['image_filename']; }

          // Stock movements: restock old, consume new
          if ($oldVsId) {
            $up = $conn->prepare("UPDATE variation_sizes SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $up->bind_param('ii', $oi['quantity'], $oldVsId);
            $up->execute();
            $up->close();
          }
          $up = $conn->prepare("UPDATE variation_sizes SET stock_quantity = stock_quantity - ? WHERE id = ?");
          $up->bind_param('ii', $oi['quantity'], $newVsId);
          $up->execute();
          $up->close();

          // Update order item (keep unit_price same)
          if ($newImage) {
            $uu = $conn->prepare("UPDATE order_items SET color = ?, size = ?, product_image_filename = ? WHERE id = ? AND order_id = ?");
            $uu->bind_param('sssii', $new_color, $new_size, $newImage, $order_item_id, $order['id']);
          } else {
            $uu = $conn->prepare("UPDATE order_items SET color = ?, size = ? WHERE id = ? AND order_id = ?");
            $uu->bind_param('ssii', $new_color, $new_size, $order_item_id, $order['id']);
          }
          $uu->execute();
          $uu->close();

          $conn->commit();
          $success_message = 'Item updated.';
        } catch (Exception $e) {
          $conn->rollback();
          $error_message = $e->getMessage();
        }
      }
    }
  }
}

require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <section class="confirmation-hero container" aria-label="Review Order">
    <h1 class="confirmation-title">Review Your Order</h1>
    <p class="confirmation-subtitle">Order #<?php echo htmlspecialchars($order['order_number']); ?> · 
      <?php if ($isWindowOpen): ?>
        Edit window open · <strong><span id="edit-timer" data-remaining="<?php echo (int)$remaining; ?>"><?php echo gmdate('i:s', $remaining); ?></span></strong>
      <?php else: ?>
        Edit window closed
      <?php endif; ?>
    </p>
  </section>

  <div class="confirmation-layout container">
    <div class="confirmation-main">
      <section class="confirmation-card">
        <h2 class="card-title">Modify Items</h2>
        <?php
          // Load items for UI
          $items = [];
          $stmt = $conn->prepare("SELECT id, product_id, product_name, color, size, quantity, unit_price, line_total FROM order_items WHERE order_id = ?");
          $stmt->bind_param('i', $order['id']);
          $stmt->execute();
          $res = $stmt->get_result();
          while ($row = $res->fetch_assoc()) { $items[] = $row; }
          $stmt->close();

          // Build variation maps per product
          $productIds = array_values(array_unique(array_map(function($i){ return (int)$i['product_id']; }, $items)));
          $productVariations = [];
          if (!empty($productIds)) {
            $in = implode(',', array_fill(0, count($productIds), '?'));
            $types = str_repeat('i', count($productIds));
            $qv = $conn->prepare("SELECT id, product_id, colour FROM product_variations WHERE product_id IN ($in) AND is_active = 1");
            $qv->bind_param($types, ...$productIds);
            $qv->execute();
            $rv = $qv->get_result();
            $varIds = [];
            while ($vr = $rv->fetch_assoc()) { $productVariations[(int)$vr['product_id']][] = $vr; $varIds[] = (int)$vr['id']; }
            $qv->close();
            $variationSizes = [];
            if (!empty($varIds)) {
              $in2 = implode(',', array_fill(0, count($varIds), '?'));
              $t2 = str_repeat('i', count($varIds));
              $qs = $conn->prepare("SELECT variation_id, size, stock_quantity FROM variation_sizes WHERE variation_id IN ($in2)");
              $qs->bind_param($t2, ...$varIds);
              $qs->execute();
              $rs = $qs->get_result();
              while ($s = $rs->fetch_assoc()) { $variationSizes[(int)$s['variation_id']][] = $s; }
              $qs->close();
            }
          }
        ?>
        <?php foreach ($items as $it): ?>
          <form method="post" action="order_review.php?<?php echo $token ? 'token=' . urlencode($token) : 'order=' . urlencode($order['order_number']); ?>" style="display:grid; gap:.5rem; padding:.75rem 0; border-bottom:1px solid rgba(0,0,0,.06);">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="change_item">
            <input type="hidden" name="order_item_id" value="<?php echo (int)$it['id']; ?>">
            <div style="font-weight:600;"><?php echo htmlspecialchars($it['product_name']); ?> <span style="color:var(--muted); font-weight:400;">(Qty: <?php echo (int)$it['quantity']; ?>)</span></div>
            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
              <label>Color:
                <select name="color" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>
                  <?php foreach (($productVariations[(int)$it['product_id']] ?? []) as $vr): ?>
                    <option value="<?php echo htmlspecialchars($vr['colour']); ?>" <?php echo (strtolower(trim($vr['colour'])) === strtolower(trim($it['color']))) ? 'selected' : ''; ?>><?php echo htmlspecialchars($vr['colour']); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Size:
                <select name="size" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>
                  <?php
                    // Aggregate sizes across variations for simplicity; server validates color/size pair on submit
                    $sizesPrinted = [];
                    foreach (($productVariations[(int)$it['product_id']] ?? []) as $vr) {
                      foreach (($variationSizes[(int)$vr['id']] ?? []) as $sz) {
                        $val = (string)$sz['size'];
                        if (!isset($sizesPrinted[$val])) {
                          $sizesPrinted[$val] = true;
                          echo '<option value="' . htmlspecialchars($val) . '"' . (strtolower(trim($val)) === strtolower(trim($it['size'])) ? ' selected' : '') . '>' . htmlspecialchars($val) . '</option>';
                        }
                      }
                    }
                  ?>
                </select>
              </label>
              <button type="submit" class="btn btn--primary" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>Apply</button>
            </div>
          </form>
        <?php endforeach; ?>
      </section>
      <?php if (!empty($error_message)): ?>
        <div class="message message--error"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>
      <?php if (!empty($success_message)): ?>
        <div class="message message--success"><?php echo htmlspecialchars($success_message); ?></div>
      <?php endif; ?>

      <section class="confirmation-card">
        <h2 class="card-title">Shipping Details</h2>
        <form method="post" action="order_review.php?order=<?php echo urlencode($order['order_number']); ?>">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="save_changes">
          <div class="form-group">
            <label for="shipping_address">Shipping Address</label>
            <textarea id="shipping_address" name="shipping_address" rows="3" <?php echo $isWindowOpen ? '' : 'disabled'; ?>><?php echo htmlspecialchars($order['shipping_address']); ?></textarea>
          </div>
          <div class="form-group">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="tel" value="<?php echo htmlspecialchars($order['customer_phone']); ?>" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>
          </div>
          <div class="form-group">
            <label for="special_instructions">Special Instructions</label>
            <textarea id="special_instructions" name="special_instructions" rows="2" <?php echo $isWindowOpen ? '' : 'disabled'; ?>><?php echo htmlspecialchars($order['special_instructions']); ?></textarea>
          </div>
          <button type="submit" class="btn btn--primary" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>Save Changes</button>
        </form>
      </section>

      <section class="confirmation-card">
        <h2 class="card-title">Actions</h2>
        <form method="post" action="order_review.php?order=<?php echo urlencode($order['order_number']); ?>" style="display:inline-block; margin-right:.5rem;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="confirm">
          <button type="submit" class="btn btn--primary" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>Confirm Order</button>
        </form>
        <form method="post" action="order_review.php?order=<?php echo urlencode($order['order_number']); ?>" style="display:inline-block;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="cancel">
          <button type="submit" class="btn btn-secondary" <?php echo $isWindowOpen ? '' : 'disabled'; ?>>Cancel Order</button>
        </form>
        <p style="margin-top:.5rem; color: var(--muted);">Edit window closes at <?php echo date('H:i:s', $expiresTs); ?>.</p>
      </section>
    </div>
  </div>
</main>

<?php if ($isWindowOpen): ?>
<script>
(function(){
  var el = document.getElementById('edit-timer');
  if (!el) return;
  var remain = parseInt(el.getAttribute('data-remaining')||'0',10) || 0;
  function tick(){
    if (remain <= 0) { el.textContent = '00:00'; return; }
    remain -= 1;
    var m = Math.floor(remain/60), s = remain%60;
    el.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    if (remain > 0) setTimeout(tick, 1000);
  }
  setTimeout(tick, 1000);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
