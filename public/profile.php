<?php
require_once 'config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

$customerId = (int)$_SESSION['customer_id'];
$customerName = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : '';
// Robust admin check: TINYINT(1) 0/1 stored as string or int
$isAdmin = !empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] == 1;

// Fetch recent orders for this customer
$orders = [];
$conn = getDBConnection();
$stmt = $conn->prepare('SELECT id, order_number, order_total, order_status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) { $orders[] = $row; }
}
$stmt->close();
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
    <?php endif; ?>
  </div>

  <?php if ($isAdmin): ?>
  <section style="margin-bottom:2rem;">
    <h2>Admin</h2>
    <ul style="margin:.5rem 0 0 1rem;">
      <li><a href="admin_add_product.php">Add Product</a></li>
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
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0;">
                <?php echo htmlspecialchars($o['order_number']); ?>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0;">
                <?php echo htmlspecialchars($o['order_status']); ?>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0; text-align:right;">
                $<?php echo number_format((float)$o['order_total'], 2); ?>
              </td>
              <td style="padding:.75rem; border-bottom:1px solid #f0f0f0;">
                <?php echo htmlspecialchars($o['created_at']); ?>
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


