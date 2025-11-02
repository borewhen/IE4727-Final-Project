<?php
require_once 'config.php';

// Require login
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

$user = [
    'id' => $_SESSION['customer_id'],
    'name' => $_SESSION['customer_name'] ?? '',
    'email' => $_SESSION['customer_email'] ?? ''
];

$error_message = '';
$order_number = isset($_GET['order']) ? trim($_GET['order']) : '';

if (empty($order_number)) {
    header('Location: profile.php');
    exit();
}

// Get order details and verify ownership
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.customer_email,
        o.customer_name,
        o.customer_phone,
        o.shipping_address,
        o.order_total,
        o.order_status,
        o.payment_status,
        o.created_at
    FROM orders o
    WHERE o.order_number = ? AND o.customer_id = ?
");
$stmt->bind_param("si", $order_number, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $conn->close();
    header('Location: profile.php');
    exit();
}

// Check if return already exists for this order
$stmt = $conn->prepare("SELECT id FROM returns WHERE order_id = ?");
$stmt->bind_param("i", $order['id']);
$stmt->execute();
$existing_return = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing_return) {
    $conn->close();
    $_SESSION['error_message'] = 'A return has already been requested for this order.';
    header('Location: profile.php');
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT 
        oi.id,
        oi.product_name,
        oi.product_image_filename,
        oi.size,
        oi.color,
        oi.quantity,
        oi.unit_price,
        oi.line_total
    FROM order_items oi
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order['id']);
$stmt->execute();
$result = $stmt->get_result();
$order_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $return_items = isset($_POST['return_items']) ? $_POST['return_items'] : [];
    $return_reason = trim($_POST['return_reason'] ?? '');
    $return_details = trim($_POST['return_details'] ?? '');
    $refund_method = $_POST['refund_method'] ?? '';
    
    // Validation
    $validation_errors = [];
    
    if (empty($return_items)) {
        $validation_errors[] = 'Please select at least one item to return';
    }
    
    if (empty($return_reason)) {
        $validation_errors[] = 'Please select a return reason';
    }
    
    if (empty($return_details)) {
        $validation_errors[] = 'Please provide details about the return';
    } elseif (strlen($return_details) < 20) {
        $validation_errors[] = 'Return details must be at least 20 characters';
    }
    
    if (empty($refund_method)) {
        $validation_errors[] = 'Please select a refund method';
    }
    
    if (!empty($validation_errors)) {
        $error_message = implode('<br>', $validation_errors);
    } else {
        // Calculate return total and collect item details
        $return_total = 0;
        $selected_items = [];
        
        foreach ($return_items as $item_id) {
            foreach ($order_items as $item) {
                if ($item['id'] == $item_id) {
                    $return_total += $item['line_total'];
                    $selected_items[] = $item;
                    break;
                }
            }
        }
        
        // Generate unique return number
        $return_number = 'RET-' . strtoupper(uniqid());
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into returns table
            $stmt = $conn->prepare("
                INSERT INTO returns (
                    order_id, 
                    customer_id, 
                    return_number, 
                    return_status,
                    return_reason, 
                    return_details, 
                    refund_method, 
                    return_total,
                    order_status_at_request,
                    payment_status_at_request,
                    requested_at
                ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "iissssiss",
                $order['id'],
                $user['id'],
                $return_number,
                $return_reason,
                $return_details,
                $refund_method,
                $return_total,
                $order['order_status'],
                $order['payment_status']
            );
            $stmt->execute();
            $return_id = $conn->insert_id;
            $stmt->close();
            
            // Insert return items
            $stmt = $conn->prepare("
                INSERT INTO return_items (return_id, order_item_id, quantity)
                VALUES (?, ?, ?)
            ");
            
            foreach ($selected_items as $item) {
                $stmt->bind_param("iii", $return_id, $item['id'], $item['quantity']);
                $stmt->execute();
            }
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to confirmation
            $_SESSION['return_request_success'] = true;
            $_SESSION['return_number'] = $return_number;
            $_SESSION['return_order_number'] = $order_number;
            
            $conn->close();
            header('Location: return-confirmation.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = 'An error occurred while processing your return request. Please try again.';
        }
    }
}

$conn->close();
require __DIR__ . '/partials/header.php';
?>

<main id="main" class="container" style="padding:2rem 0;">
  <h1>Request Return</h1>
  <p class="subtitle">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>

  <?php if (!empty($error_message)): ?>
    <div class="error-message" style="margin:1rem 0;">
      <?php echo $error_message; ?>
    </div>
  <?php endif; ?>

  <div class="return-layout">
    <form method="POST" action="" id="returnForm" class="return-form">
      
      <!-- Order Information -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Order Information</h2>
        <table style="width:100%;">
          <tr>
            <td style="padding:.5rem 0; color:var(--muted); width:40%;">Order Number:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo htmlspecialchars($order['order_number']); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Order Date:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Order Status:</td>
            <td style="padding:.5rem 0;">
              <span class="order-status-badge order-status-<?php echo $order['order_status']; ?>">
                <?php echo ucfirst($order['order_status']); ?>
              </span>
            </td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Order Total:</td>
            <td style="padding:.5rem 0; font-weight:600;">$<?php echo number_format($order['order_total'], 2); ?></td>
          </tr>
        </table>
      </div>

      <!-- Select Items to Return -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Select Items to Return <span class="required">*</span></h2>
        <div class="return-items">
          <?php foreach ($order_items as $item): ?>
            <label class="return-item-box">
              <input type="checkbox" name="return_items[]" value="<?php echo $item['id']; ?>" class="return-checkbox">
              <div class="return-item-content">
                <?php if (!empty($item['product_image_filename'])): ?>
                  <img src="<?php echo htmlspecialchars($item['product_image_filename']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width:80px; height:80px; object-fit:cover; border-radius:.5rem;">
                <?php endif; ?>
                <div style="flex:1; min-width:0;">
                  <div style="font-weight:600; margin-bottom:.25rem;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                  <div style="font-size:.85rem; color:var(--muted); margin-bottom:.25rem;">
                    <?php if (!empty($item['size'])): ?>Size: <?php echo htmlspecialchars($item['size']); ?> • <?php endif; ?>
                    <?php if (!empty($item['color'])): ?>Color: <?php echo htmlspecialchars($item['color']); ?> • <?php endif; ?>
                    Qty: <?php echo $item['quantity']; ?>
                  </div>
                  <div style="font-weight:700; color:var(--brown-1);">$<?php echo number_format($item['line_total'], 2); ?></div>
                </div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Return Reason -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Reason for Return <span class="required">*</span></h2>
        <select name="return_reason" id="return_reason" required style="width:100%; padding:.75rem; border:1px solid rgba(0,0,0,.2); border-radius:.5rem; font:inherit; background:#fff;">
          <option value="">Select a reason</option>
          <option value="defective">Product is defective or damaged</option>
          <option value="wrong_item">Received wrong item</option>
          <option value="size_fit">Size or fit issue</option>
          <option value="quality">Quality not as expected</option>
          <option value="changed_mind">Changed my mind</option>
          <option value="not_delivered">Order not delivered yet</option>
          <option value="cancel_order">Want to cancel order</option>
          <option value="other">Other reason</option>
        </select>
      </div>

      <!-- Return Details -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Return Details <span class="required">*</span></h2>
        <textarea 
          id="return_details" 
          name="return_details" 
          rows="5" 
          required
          placeholder="Describe the issue or reason for return in detail (minimum 20 characters)"
          style="width:100%; padding:.75rem; border:1px solid rgba(0,0,0,.2); border-radius:.5rem; font:inherit; resize:vertical;"
        ></textarea>
        <small style="display:block; margin-top:.5rem; color:var(--muted); font-size:.85rem;">Minimum 20 characters required</small>
      </div>

      <!-- Refund Method -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Preferred Refund Method <span class="required">*</span></h2>
        <div class="refund-options">
          <label class="refund-option-box">
            <input type="radio" name="refund_method" value="original_payment" required>
            <div>
              <strong>Original Payment Method</strong>
              <p style="margin:.25rem 0 0; font-size:.85rem; color:var(--muted);">Refund to your original payment method</p>
            </div>
          </label>
          
          <label class="refund-option-box">
            <input type="radio" name="refund_method" value="store_credit">
            <div>
              <strong>Store Credit</strong>
              <p style="margin:.25rem 0 0; font-size:.85rem; color:var(--muted);">Receive store credit for future purchases</p>
            </div>
          </label>
          
          <label class="refund-option-box">
            <input type="radio" name="refund_method" value="exchange">
            <div>
              <strong>Exchange</strong>
              <p style="margin:.25rem 0 0; font-size:.85rem; color:var(--muted);">Exchange for a different product</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Important Notes -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem; background:rgba(255,193,7,.1); border-left:4px solid #ffc107;">
        <h3 style="margin:0 0 .75rem 0; font-size:1rem;">Important Return Information</h3>
        <ul style="margin:0; padding-left:1.5rem; line-height:1.8;">
          <li>Returns can be requested at any order status</li>
          <li>For pending/processing orders, this acts as a cancellation request</li>
          <li>For delivered orders, return shipping instructions will be sent</li>
          <li>Items must be in original condition with tags attached</li>
          <li>Refund will be processed within 5-7 business days after we receive the items</li>
          <li>Original shipping charges may be non-refundable depending on reason</li>
        </ul>
      </div>

      <div style="display:flex; gap:1rem; flex-wrap:wrap;">
        <a href="profile.php" class="btn-secondary">Cancel</a>
        <button type="submit" name="submit_return" class="btn btn--primary">
          Submit Return Request
        </button>
      </div>
    </form>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    document.getElementById('returnForm').addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.return-checkbox:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one item to return');
            return false;
        }
        
        const details = document.getElementById('return_details').value.trim();
        if (details.length < 20) {
            e.preventDefault();
            alert('Please provide at least 20 characters in return details');
            document.getElementById('return_details').focus();
            return false;
        }
    });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
