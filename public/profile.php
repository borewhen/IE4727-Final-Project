<?php
require_once 'config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

$customerId = (int)$_SESSION['customer_id'];
$customerName = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : '';
$isAdmin = !empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] == 1;

// Fetch recent orders for this customer
$orders = [];
$conn = getDBConnection();
$stmt = $conn->prepare('
    SELECT id, order_number, order_total, order_status, payment_status, created_at 
    FROM orders 
    WHERE customer_id = ? 
    ORDER BY created_at DESC
');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) { 
        $orders[] = $row; 
    }
}
$stmt->close();

// Check which orders have returns
$order_returns = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $conn->prepare("
        SELECT order_id, return_status, return_number 
        FROM returns 
        WHERE order_id IN ($placeholders)
    ");
    $types = str_repeat('i', count($order_ids));
    $stmt->bind_param($types, ...$order_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $order_returns[$row['order_id']] = $row;
    }
    $stmt->close();
}

$conn->close();

require __DIR__ . '/partials/header.php';
?>
<main id="main" class="container" style="padding:2rem 0;">
  <h1>Welcome, <?php echo htmlspecialchars($customerName ?: 'there'); ?></h1>
  <p class="subtitle" style="margin-top:.25rem;">Manage your account and view your orders.</p>

  <div style="margin:1rem 0 2rem; display:flex; gap:1rem; flex-wrap:wrap;">
    <a class="btn-secondary" href="logout.php">Log out</a>
    <?php if ($isAdmin): ?>
      <a class="btn-secondary" href="admin_add_product.php">Add Product</a>
      <a class="btn-secondary" href="sales-report.php">Sales Report</a>
    <?php endif; ?>
  </div>

  <?php if ($isAdmin): ?>
  <section style="margin-bottom:2rem;">
    <h2>Admin</h2>
    <ul style="margin:.5rem 0 0 1rem;">
      <li><a href="admin_add_product.php">Add Product</a></li>
      <li><a href="sales-report.php">View Sales Report</a></li>
    </ul>
  </section>
  <?php endif; ?>

  <section>
    <h2>Your Orders</h2>
    <?php if (empty($orders)): ?>
      <div class="notice" style="margin-top:.5rem;">You have no orders yet.</div>
    <?php else: ?>
      <div class="card" style="padding:0; overflow:auto;">
        <table class="table" style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:.75rem; border-bottom:1px solid #e5e7eb;">Order #</th>
              <th style="text-align:left; padding:.75rem; border-bottom:1px solid #e5e7eb;">Status</th>
              <th style="text-align:right; padding:.75rem; border-bottom:1px solid #e5e7eb;">Total</th>
              <th style="text-align:left; padding:.75rem; border-bottom:1px solid #e5e7eb;">Date</th>
              <th style="text-align:center; padding:.75rem; border-bottom:1px solid #e5e7eb;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <?php 
              $hasReturn = isset($order_returns[$o['id']]);
              $returnData = $hasReturn ? $order_returns[$o['id']] : null;
            ?>
            <tr>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0;">
                <?php echo htmlspecialchars($o['order_number']); ?>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0;">
                <span class="order-status-badge order-status-<?php echo $o['order_status']; ?>">
                  <?php echo ucfirst($o['order_status']); ?>
                </span>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0; text-align:right;">
                $<?php echo number_format((float)$o['order_total'], 2); ?>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0;">
                <?php echo date('M d, Y', strtotime($o['created_at'])); ?>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0; text-align:center;">
                <?php if ($hasReturn): ?>
                  <!-- Return already exists -->
                  <a href="return-status.php?return=<?php echo urlencode($returnData['return_number']); ?>" class="btn-view-return">
                    View Return
                  </a>
                  <div style="margin-top:.25rem;">
                    <span class="return-status-badge return-status-<?php echo $returnData['return_status']; ?>">
                      <?php echo ucfirst(str_replace('_', ' ', $returnData['return_status'])); ?>
                    </span>
                  </div>
                <?php elseif ($o['order_status'] !== 'cancelled'): ?>
                  <!-- Can request return -->
                  <a href="return-request.php?order=<?php echo urlencode($o['order_number']); ?>" class="btn-return">
                    Request Return
                  </a>
                <?php else: ?>
                  <!-- Cancelled order -->
                  <span style="color: #999; font-size: .85rem;">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
