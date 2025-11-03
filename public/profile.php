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
    SELECT id, order_number, order_total, order_status, created_at 
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
  <div style="width: 40vw; display:flex; justify-content:space-between; align-items:baseline;">
    <h1>Welcome, <?php echo htmlspecialchars(($customerName ?: '')).'.'; ?></h1> 
    <a href="logout.php" style="text-decoration:underline; color:var(--brown-1); min-width:80px;">Log out?</a>
  </div>

  <div style="width: 25vw; justify-content:space-between; align-items:baseline;">
    <?php if ($isAdmin): ?>
      <a class="btn-secondary" href="admin_add_product.php">Add Product</a>
      <!-- <a class="btn-secondary" href="sales-report.php">Sales Report</a> -->
    <?php endif; ?>
  </div>

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

  <?php if ($isAdmin): ?>
  <section style="margin-top:2rem;">
    <h2>Admin • Sales Overview</h2>
    <?php
      $adminConn = getDBConnection();
      $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
      $endDate = date('Y-m-d 23:59:59');

      // Sales metrics
      $salesMetrics = [
        'total_orders' => 0,
        'total_revenue' => 0,
        'delivered_revenue' => 0,
        'cancelled_orders' => 0,
        'avg_order_value' => 0,
      ];
      $stmt = $adminConn->prepare(
        "SELECT COUNT(*) as total_orders, 
        COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN order_total ELSE 0 END),0) as total_revenue, 
        COALESCE(SUM(CASE WHEN order_status='delivered' THEN order_total ELSE 0 END),0) as delivered_revenue, 
        SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) as cancelled_orders, 
        AVG(CASE WHEN order_status!='cancelled' THEN order_total ELSE NULL END) as avg_order_value FROM orders WHERE created_at BETWEEN ? AND ?");
      $stmt->bind_param('ss', $startDate, $endDate);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) { $salesMetrics = $res->fetch_assoc(); }
      $stmt->close();

      // Sales by status
      $salesByStatus = [];
      $stmt = $adminConn->prepare("SELECT order_status, COUNT(*) as count, COALESCE(SUM(order_total),0) as total FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY order_status ORDER BY count DESC");
      $stmt->bind_param('ss', $startDate, $endDate);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res) { $salesByStatus = $res->fetch_all(MYSQLI_ASSOC); }
      $stmt->close();
    ?>
    <?php 
      $maxVarUnits = 0; foreach (($topVariations ?? []) as $r) { $u = (int)($r['units'] ?? 0); if ($u > $maxVarUnits) $maxVarUnits = $u; }
      $maxSizeUnits = 0; foreach (($topSizes ?? []) as $r) { $u = (int)($r['units'] ?? 0); if ($u > $maxSizeUnits) $maxSizeUnits = $u; }
    ?>
    <div class="card" style="padding:1rem; margin-top:.75rem;">
      <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem;">
        <div>
          <div style="font-size:1.5rem; font-weight:700;">$<?php echo number_format($salesMetrics['total_revenue'] ?? 0, 2); ?></div>
          <div class="muted" style="font-size:.9rem;">Gross Revenue (30d)</div>
        </div>
        <div>
          <div style="font-size:1.5rem; font-weight:700;"><?php echo number_format($salesMetrics['total_orders'] ?? 0); ?></div>
          <div class="muted" style="font-size:.9rem;">Orders (30d)</div>
        </div>
        <div>
          <div style="font-size:1.5rem; font-weight:700;">$<?php echo number_format($salesMetrics['avg_order_value'] ?? 0, 2); ?></div>
          <div class="muted" style="font-size:.9rem;">Avg Order Value</div>
        </div>
      </div>
      <?php if (!empty($salesByStatus)): ?>
      <div style="margin-top:1rem;">
        <h3 style="margin:0 0 .5rem; font-size:1.05rem;">Sales by Status</h3>
        <?php foreach ($salesByStatus as $row): 
          $pct = ($salesMetrics['total_orders'] ?? 0) > 0 ? ($row['count'] / $salesMetrics['total_orders']) * 100 : 0;
        ?>
          <div style="margin:.5rem 0;">
            <div style="display:flex; width: 30%; justify-content:space-between; font-size:.9rem;">
              <span style="text-transform:capitalize; font-weight:600;"><?php echo htmlspecialchars(str_replace('_',' ',$row['order_status'])); ?></span>
              <span class="muted"><?php echo (int)$row['count']; ?> order(s)</span>
            </div>
            <!-- <div style="height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
              <div style="width:<?php echo $pct; ?>%; height:100%; background:var(--brown-1);"></div>
            </div> -->
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php $adminConn->close(); ?>
  </section>

  <section style="margin-top:2rem;">
    <h2>Admin • Inventory</h2>
    <?php
      $invConn = getDBConnection();
      $q = $invConn->prepare("SELECT p.id AS product_id, p.name AS product_name, pv.id AS variation_id, pv.colour AS colour, vs.id AS vs_id, vs.size AS size, vs.stock_quantity AS stock FROM products p JOIN product_variations pv ON pv.product_id = p.id JOIN variation_sizes vs ON vs.variation_id = pv.id WHERE p.is_active = 1 ORDER BY p.name, pv.id, vs.id");
      $q->execute();
      $r = $q->get_result();
      $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
      $q->close();
      $invConn->close();
      // Group by product and variation
      $byProduct = [];
      foreach ($rows as $row) {
        $pid = (int)$row['product_id'];
        $vid = (int)$row['variation_id'];
        if (!isset($byProduct[$pid])) { $byProduct[$pid] = ['name'=>$row['product_name'], 'vars'=>[]]; }
        if (!isset($byProduct[$pid]['vars'][$vid])) { $byProduct[$pid]['vars'][$vid] = ['colour'=>$row['colour'], 'sizes'=>[]]; }
        $byProduct[$pid]['vars'][$vid]['sizes'][] = ['size'=>$row['size'], 'stock'=>$row['stock']];
      }
    ?>
    <?php if (empty($byProduct)): ?>
      <div class="notice" style="margin-top:.5rem;">No inventory records found.</div>
    <?php else: ?>
      <div class="card" style="padding:1rem; margin-top:.75rem;">
        <?php foreach ($byProduct as $pid => $pdata): ?>
          <div style="margin-bottom:1rem;">
            <h3 style="margin:.25rem 0; font-size:1.05rem;"><?php echo htmlspecialchars($pdata['name']); ?></h3>
            <?php foreach ($pdata['vars'] as $vid => $vdata): ?>
              <div style="padding:.5rem .75rem; background:rgba(0,0,0,.03); border-radius:.5rem; margin:.5rem 0;">
                <div style="font-weight:600; margin-bottom:.35rem;">Colour: <?php echo htmlspecialchars($vdata['colour'] ?: 'Default'); ?></div>
                <div style="overflow-x:auto;">
                  <table style="width:100%; border-collapse:collapse; font-size:.95rem;">
                    <thead>
                      <tr style="border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left; padding:.5rem;">Size</th>
                        <th style="text-align:right; padding:.5rem;">Stock</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($vdata['sizes'] as $sz): ?>
                        <tr style="border-bottom:1px solid #f6f6f6;">
                          <td style="padding:.5rem;"><?php echo htmlspecialchars($sz['size']); ?></td>
                          <td style="padding:.5rem; text-align:right; font-weight:600;"><?php echo (int)$sz['stock']; ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  
  <section style="margin-top:2rem;">
    <h2>Admin • Most Popular</h2>
    <?php
      $popConn = getDBConnection();
      $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
      $endDate = date('Y-m-d 23:59:59');

      // Top variations by units sold (30d) — include size as well
      $topVariations = [];
      $sqlVar = "SELECT oi.product_id, p.name AS product_name, COALESCE(pv.colour, oi.color) AS colour, oi.size AS size, SUM(oi.quantity) AS units
                 FROM order_items oi
                 JOIN orders o ON oi.order_id = o.id
                 JOIN products p ON oi.product_id = p.id
                 LEFT JOIN product_variations pv
                   ON pv.product_id = oi.product_id
                  AND LOWER(TRIM(pv.colour)) = LOWER(TRIM(oi.color))
                 WHERE o.order_status != 'cancelled' AND o.created_at BETWEEN ? AND ?
                 GROUP BY oi.product_id, product_name, colour, oi.size
                 ORDER BY units DESC
                 LIMIT 10";
      $st = $popConn->prepare($sqlVar);
      $st->bind_param('ss', $startDate, $endDate);
      $st->execute();
      $res = $st->get_result();
      if ($res) { $topVariations = $res->fetch_all(MYSQLI_ASSOC); }
      $st->close();

      // Top sizes by category (30d)
      $topSizes = [];
      $sqlSize = "SELECT c.name AS category_name, oi.size AS size, SUM(oi.quantity) AS units
                  FROM order_items oi
                  JOIN orders o ON oi.order_id = o.id
                  JOIN products p ON oi.product_id = p.id
                  JOIN categories c ON p.category_id = c.id
                  WHERE o.order_status != 'cancelled' AND o.created_at BETWEEN ? AND ?
                  GROUP BY category_name, oi.size
                  ORDER BY units DESC
                  LIMIT 10";
      $st = $popConn->prepare($sqlSize);
      $st->bind_param('ss', $startDate, $endDate);
      $st->execute();
      $res = $st->get_result();
      if ($res) { $topSizes = $res->fetch_all(MYSQLI_ASSOC); }
      $st->close();
      $popConn->close();
    ?>
    <div class="card" style="padding:1rem; margin-top:.75rem;">
      <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:1rem;">
        <div>
          <h3 style="margin:0 0 .5rem; font-size:1.05rem;">Top Variations (30d)</h3>
          <?php if (empty($topVariations)): ?>
            <div class="notice">No sales yet.</div>
          <?php else: ?>
          <div>
            <?php foreach ($topVariations as $row): 
              $units = (int)$row['units'];
              $pct = $maxVarUnits > 0 ? ($units / $maxVarUnits) * 100 : 0;
            ?>
              <div style="margin-bottom:.75rem;">
                <div style="display:flex; justify-content:space-between; font-size:.95rem; margin-bottom:.25rem;">
                  <div style="min-width:0;">
                    <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      <?php echo htmlspecialchars($row['product_name']); ?>
                    </div>
                    <div class="muted" style="font-size:.85rem;">
                      Colour: <?php echo htmlspecialchars($row['colour'] ?: 'Default'); ?> · Size: <?php echo htmlspecialchars($row['size'] ?: 'N/A'); ?>
                    </div>
                  </div>
                  <div style="margin-left:1rem; font-weight:700;">
                    <?php echo $units; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <div>
          <h3 style="margin:0 0 .5rem; font-size:1.05rem;">Top Sizes by Category (30d)</h3>
          <?php if (empty($topSizes)): ?>
            <div class="notice">No sales yet.</div>
          <?php else: ?>
          <div>
            <?php foreach ($topSizes as $row): 
              $units = (int)$row['units'];
              $pct = $maxSizeUnits > 0 ? ($units / $maxSizeUnits) * 100 : 0;
            ?>
              <div style="margin-bottom:.75rem;">
                <div style="display:flex; justify-content:space-between; font-size:.95rem; margin-bottom:.25rem;">
                  <div>
                    <div style="font-weight:600;">Size: <?php echo htmlspecialchars($row['size'] ?: 'N/A'); ?></div>
                    <div class="muted" style="font-size:.85rem;">Category: <?php echo htmlspecialchars($row['category_name']); ?></div>
                  </div>
                  <div style="margin-left:1rem; font-weight:700;">
                    <?php echo $units; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
