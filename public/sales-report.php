<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Admin only
if (!isset($_SESSION['customer_id']) || empty($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] != 1) {
    header('Location: profile.php');
    exit();
}

$conn = getDBConnection();

// Date filter (default: last 30 days)
$dateFilter = isset($_GET['filter']) ? $_GET['filter'] : '30days';
$startDate = '';
$endDate = date('Y-m-d 23:59:59');

switch ($dateFilter) {
    case 'yesterday':
        $startDate = date('Y-m-d 00:00:00', strtotime('yesterday'));
        $endDate = date('Y-m-d 23:59:59', strtotime('yesterday'));
        break;
    case '7days':
        $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
        break;
    case '30days':
        $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
        break;
    case '90days':
        $startDate = date('Y-m-d 00:00:00', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d 00:00:00', strtotime('-1 year'));
        break;
    case 'all':
        $startDate = '2000-01-01 00:00:00';
        break;
}

// Helper function to format large numbers
function formatNumber($num) {
    if ($num >= 1000000) {
        return number_format($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return number_format($num / 1000, 1) . 'K';
    }
    return number_format($num, 0);
}

// ==================== RETURN METRICS FIRST ====================
// We need this to calculate net revenue

$returnMetrics = [
    'total_returns' => 0,
    'total_return_amount' => 0,
    'pending_returns' => 0,
    'approved_returns' => 0,
    'rejected_returns' => 0,
    'refunded_returns' => 0,
    'total_refunded' => 0
];

try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_returns,
            COALESCE(SUM(return_total), 0) as total_return_amount,
            SUM(CASE WHEN return_status = 'pending' THEN 1 ELSE 0 END) as pending_returns,
            SUM(CASE WHEN return_status = 'approved' THEN 1 ELSE 0 END) as approved_returns,
            SUM(CASE WHEN return_status = 'rejected' THEN 1 ELSE 0 END) as rejected_returns,
            SUM(CASE WHEN return_status = 'refunded' THEN 1 ELSE 0 END) as refunded_returns,
            COALESCE(SUM(CASE WHEN return_status = 'refunded' THEN return_total ELSE 0 END), 0) as total_refunded
        FROM returns
        WHERE requested_at BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $returnMetrics = $result->fetch_assoc();
    }
    $stmt->close();
} catch (Exception $e) {
    // If returns table doesn't exist or error, continue with empty metrics
}

// ==================== SALES METRICS ====================

$salesMetrics = [
    'total_orders' => 0,
    'total_revenue' => 0,
    'delivered_revenue' => 0,
    'cancelled_orders' => 0,
    'avg_order_value' => 0,
    'net_revenue' => 0
];

try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN order_total ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN order_status = 'delivered' THEN order_total ELSE 0 END), 0) as delivered_revenue,
            SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            AVG(CASE WHEN order_status != 'cancelled' THEN order_total ELSE NULL END) as avg_order_value
        FROM orders
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $salesMetrics = $result->fetch_assoc();
        // Calculate NET REVENUE (subtract refunded returns)
        $salesMetrics['net_revenue'] = $salesMetrics['total_revenue'] - ($returnMetrics['total_refunded'] ?? 0);
    }
    $stmt->close();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Sales by status
$salesByStatus = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            order_status,
            COUNT(*) as count,
            COALESCE(SUM(order_total), 0) as total
        FROM orders
        WHERE created_at BETWEEN ? AND ?
        GROUP BY order_status
        ORDER BY count DESC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $salesByStatus = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    // Continue with empty array
}

// Top customers
$topCustomers = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            customer_name,
            customer_email,
            COUNT(*) as order_count,
            COALESCE(SUM(order_total), 0) as total_spent
        FROM orders
        WHERE created_at BETWEEN ? AND ?
        GROUP BY customer_id, customer_name, customer_email
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $topCustomers = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    // Continue with empty array
}

// Top products
$topProducts = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            oi.product_name,
            SUM(oi.quantity) as total_quantity,
            COALESCE(SUM(oi.line_total), 0) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.created_at BETWEEN ? AND ?
        AND o.order_status != 'cancelled'
        GROUP BY oi.product_name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $topProducts = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    // Continue with empty array
}

// Daily sales trend (last 30 days for chart)
$dailySales = [];
try {
    $chartStartDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
    $chartEnd = date('Y-m-d 23:59:59');
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            COALESCE(SUM(CASE WHEN order_status != 'cancelled' THEN order_total ELSE 0 END), 0) as revenue
        FROM orders
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->bind_param("ss", $chartStartDate, $chartEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $dailySales = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    // Continue with empty array
}

// Returns by reason
$returnsByReason = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            return_reason,
            COUNT(*) as count,
            COALESCE(SUM(return_total), 0) as total_amount
        FROM returns
        WHERE requested_at BETWEEN ? AND ?
        GROUP BY return_reason
        ORDER BY count DESC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $returnsByReason = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    // Continue with empty array
}

// Recent returns
$recentReturns = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            r.return_number,
            r.return_status,
            r.return_total,
            r.return_reason,
            r.requested_at,
            o.order_number,
            o.customer_name
        FROM returns r
        JOIN orders o ON r.order_id = o.id
        WHERE r.requested_at BETWEEN ? AND ?
        ORDER BY r.requested_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $recentReturns = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} catch (Exception $e) {
    // Continue with empty array
}

$conn->close();

// Calculate return rate
$returnRate = $salesMetrics['total_orders'] > 0 
    ? (($returnMetrics['total_returns'] ?? 0) / $salesMetrics['total_orders']) * 100 
    : 0;

require __DIR__ . '/partials/header.php';
?>

<main id="main" class="container" style="padding:2rem 0;">
  <div style="margin-bottom:1.5rem;">
    <a href="profile.php" style="color:var(--brown-2); text-decoration:none; font-size:.9rem;">← Back to Profile</a>
  </div>

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:2rem;">
    <div>
      <h1 style="margin:0 0 .25rem;">Sales Report</h1>
      <p class="subtitle" style="margin:0;">Overview of sales performance and returns</p>
    </div>
    
    <!-- Date Filter -->
    <div class="date-filter">
      <select id="dateFilter" onchange="window.location.href='sales-report.php?filter='+this.value" style="padding:.6rem 1rem; border:1px solid rgba(0,0,0,.2); border-radius:.5rem; font:inherit; background:#fff; cursor:pointer;">
        <option value="yesterday" <?php echo $dateFilter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
        <option value="7days" <?php echo $dateFilter === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
        <option value="30days" <?php echo $dateFilter === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
        <option value="90days" <?php echo $dateFilter === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
        <option value="year" <?php echo $dateFilter === 'year' ? 'selected' : ''; ?>>Last Year</option>
        <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
      </select>
    </div>
  </div>

  <!-- ==================== KEY METRICS ==================== -->
  <div class="metrics-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom:2rem;">
    
    <!-- Gross Revenue -->
    <div class="metric-card card" style="padding:1.5rem;">
      <div style="font-size:2rem; font-weight:700; color:var(--brown-1); margin-bottom:.25rem; word-break:break-word;">
        $<?php echo number_format($salesMetrics['total_revenue'] ?? 0, 2); ?>
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Gross Revenue</div>
    </div>

    <!-- Net Revenue (after refunds) -->
    <div class="metric-card card" style="padding:1.5rem; border: 2px solid var(--orange-bright);">
      <div style="font-size:2rem; font-weight:700; color:var(--orange-bright); margin-bottom:.25rem; word-break:break-word;">
        $<?php echo number_format($salesMetrics['net_revenue'] ?? 0, 2); ?>
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Net Revenue</div>
      <div style="font-size:.75rem; color:var(--muted); margin-top:.25rem;">After refunds</div>
    </div>

    <div class="metric-card card" style="padding:1.5rem;">
      <div style="font-size:2rem; font-weight:700; color:var(--brown-1); margin-bottom:.25rem;">
        <?php echo formatNumber($salesMetrics['total_orders'] ?? 0); ?>
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Total Orders</div>
    </div>

    <div class="metric-card card" style="padding:1.5rem;">
      <div style="font-size:2rem; font-weight:700; color:var(--brown-1); margin-bottom:.25rem; word-break:break-word;">
        $<?php echo number_format($salesMetrics['avg_order_value'] ?? 0, 2); ?>
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Avg Order Value</div>
    </div>

    <div class="metric-card card" style="padding:1.5rem;">
      <div style="font-size:2rem; font-weight:700; color:#dc3545; margin-bottom:.25rem;">
        <?php echo formatNumber($returnMetrics['total_returns'] ?? 0); ?>
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Total Returns</div>
    </div>

    <div class="metric-card card" style="padding:1.5rem;">
      <div style="font-size:2rem; font-weight:700; color:#dc3545; margin-bottom:.25rem;">
        <?php echo number_format($returnRate, 1); ?>%
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Return Rate</div>
    </div>

    <div class="metric-card card" style="padding:1.5rem;">
      <div style="font-size:2rem; font-weight:700; color:#dc3545; margin-bottom:.25rem; word-break:break-word;">
        $<?php echo number_format($returnMetrics['total_refunded'] ?? 0, 2); ?>
      </div>
      <div style="color:var(--muted); font-size:.9rem; font-weight:600;">Total Refunded</div>
    </div>

  </div>

  <!-- ==================== REVENUE BREAKDOWN ==================== -->
  <div class="card" style="padding:1.5rem; margin-bottom:2rem; background:rgba(250,96,0,.05); border-left:4px solid var(--orange-bright);">
    <h3 style="margin:0 0 1rem; font-size:1.1rem; color:var(--brown-3);">Revenue Breakdown</h3>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
      <div style="padding:1rem; background:#fff; border-radius:.5rem;">
        <div style="font-size:.85rem; color:var(--muted); margin-bottom:.5rem;">Gross Revenue</div>
        <div style="font-size:1.5rem; font-weight:700; color:var(--brown-1);">
          $<?php echo number_format($salesMetrics['total_revenue'] ?? 0, 2); ?>
        </div>
      </div>
      <div style="padding:1rem; background:#fff; border-radius:.5rem;">
        <div style="font-size:.85rem; color:var(--muted); margin-bottom:.5rem;">Refunded</div>
        <div style="font-size:1.5rem; font-weight:700; color:#dc3545;">
          - $<?php echo number_format($returnMetrics['total_refunded'] ?? 0, 2); ?>
        </div>
      </div>
      <div style="padding:1rem; background:var(--orange-bright); color:#fff; border-radius:.5rem;">
        <div style="font-size:.85rem; opacity:.9; margin-bottom:.5rem;">Net Revenue</div>
        <div style="font-size:1.5rem; font-weight:700;">
          $<?php echo number_format($salesMetrics['net_revenue'] ?? 0, 2); ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== CHARTS ROW ==================== -->
  <?php if (!empty($salesByStatus) || !empty($topProducts) || !empty($topCustomers)): ?>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    
    <!-- Sales by Status -->
    <?php if (!empty($salesByStatus)): ?>
    <div class="card" style="padding:1.5rem;">
      <h2 style="margin:0 0 1rem; font-size:1.25rem;">Sales by Status</h2>
      <div class="chart-container">
        <?php foreach ($salesByStatus as $status): ?>
          <?php 
            $percentage = $salesMetrics['total_orders'] > 0 
              ? ($status['count'] / $salesMetrics['total_orders']) * 100 
              : 0;
          ?>
          <div style="margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:.5rem; font-size:.9rem;">
              <span style="font-weight:600; text-transform:capitalize;"><?php echo str_replace('_', ' ', $status['order_status']); ?></span>
              <span style="color:var(--muted);"><?php echo $status['count']; ?> orders</span>
            </div>
            <div class="progress-bar" style="height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
              <div class="progress-fill progress-<?php echo $status['order_status']; ?>" style="width:<?php echo $percentage; ?>%; height:100%; background:var(--brown-1); transition:width .3s;"></div>
            </div>
            <div style="text-align:right; margin-top:.25rem; font-size:.85rem; color:var(--muted);">
              $<?php echo number_format($status['total'], 2); ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Top Products -->
    <?php if (!empty($topProducts)): ?>
    <div class="card" style="padding:1.5rem;">
      <h2 style="margin:0 0 1rem; font-size:1.25rem;">Top Products</h2>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
              <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Product</th>
              <th style="text-align:center; padding:.5rem; font-size:.85rem; color:var(--muted);">Sold</th>
              <th style="text-align:right; padding:.5rem; font-size:.85rem; color:var(--muted);">Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topProducts)): ?>
              <tr>
                <td colspan="3" style="text-align:center; padding:2rem; color:var(--muted);">No products sold yet</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topProducts as $product): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                  <td style="padding:.75rem .5rem;">
                    <div style="font-weight:600; margin-bottom:.125rem; font-size:.9rem; word-break:break-word;"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div style="font-size:.75rem; color:var(--muted);"><?php echo $product['order_count']; ?> orders</div>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:center; font-weight:600; font-size:.9rem;">
                    <?php echo $product['total_quantity']; ?>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:right; font-weight:700; color:var(--brown-1); font-size:.9rem;">
                    $<?php echo number_format($product['total_revenue'], 2); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Top Customers -->
    <?php if (!empty($topCustomers)): ?>
    <div class="card" style="padding:1.5rem;">
      <h2 style="margin:0 0 1rem; font-size:1.25rem;">Top Customers</h2>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
              <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Customer</th>
              <th style="text-align:center; padding:.5rem; font-size:.85rem; color:var(--muted);">Orders</th>
              <th style="text-align:right; padding:.5rem; font-size:.85rem; color:var(--muted);">Total Spent</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topCustomers)): ?>
              <tr>
                <td colspan="3" style="text-align:center; padding:2rem; color:var(--muted);">No customers yet</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topCustomers as $customer): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                  <td style="padding:.75rem .5rem;">
                    <div style="font-weight:600; margin-bottom:.125rem; font-size:.9rem; word-break:break-word;"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                    <div style="font-size:.75rem; color:var(--muted); word-break:break-all;"><?php echo htmlspecialchars($customer['customer_email']); ?></div>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:center; font-weight:600; font-size:.9rem;">
                    <?php echo $customer['order_count']; ?>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:right; font-weight:700; color:var(--brown-1); font-size:.9rem;">
                    $<?php echo number_format($customer['total_spent'], 2); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <!-- ==================== SALES TREND CHART ==================== -->
  <div class="card" style="padding:1.5rem; margin-bottom:2rem;">
    <h2 style="margin:0 0 1rem; font-size:1.25rem;">Sales Trend (Last 30 Days)</h2>
    <div style="height:250px; display:flex; align-items:flex-end; gap:4px; padding:1rem 0;">
      <?php if (!empty($dailySales)): ?>
        <?php 
          $maxRevenue = max(array_column($dailySales, 'revenue'));
          $maxRevenue = $maxRevenue > 0 ? $maxRevenue : 1;
        ?>
        <?php foreach ($dailySales as $day): ?>
          <?php 
            $height = $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue) * 100 : 0;
          ?>
          <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end;">
            <div class="bar-tooltip" style="position:relative;">
              <div style="height:<?php echo max($height, 2); ?>%; background:var(--brown-1); border-radius:4px 4px 0 0; min-height:2px; transition:all .2s; cursor:pointer; width:100%;" 
                   title="<?php echo date('M d', strtotime($day['date'])); ?>: $<?php echo number_format($day['revenue'], 2); ?> (<?php echo $day['orders']; ?> orders)">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="width:100%; text-align:center; color:var(--muted); padding:2rem;">No sales data available for this period</div>
      <?php endif; ?>
    </div>
    <?php if (!empty($dailySales)): ?>
      <div style="text-align:center; color:var(--muted); font-size:.85rem; margin-top:1rem;">
        <?php echo date('M d', strtotime($dailySales[0]['date'])); ?> - <?php echo date('M d', strtotime($dailySales[count($dailySales)-1]['date'])); ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ==================== TOP PRODUCTS & CUSTOMERS ==================== -->
  <?php if (!empty($topProducts) || !empty($topCustomers)): ?>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(400px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
    
    <!-- Top Products -->
    <div class="card" style="padding:1.5rem;">
      <h2 style="margin:0 0 1rem; font-size:1.25rem;">Top Products</h2>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
              <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Product</th>
              <th style="text-align:center; padding:.5rem; font-size:.85rem; color:var(--muted);">Sold</th>
              <th style="text-align:right; padding:.5rem; font-size:.85rem; color:var(--muted);">Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topProducts)): ?>
              <tr>
                <td colspan="3" style="text-align:center; padding:2rem; color:var(--muted);">No products sold yet</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topProducts as $product): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                  <td style="padding:.75rem .5rem;">
                    <div style="font-weight:600; margin-bottom:.125rem; font-size:.9rem; word-break:break-word;"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div style="font-size:.75rem; color:var(--muted);"><?php echo $product['order_count']; ?> orders</div>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:center; font-weight:600; font-size:.9rem;">
                    <?php echo $product['total_quantity']; ?>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:right; font-weight:700; color:var(--brown-1); font-size:.9rem;">
                    $<?php echo number_format($product['total_revenue'], 2); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top Customers -->
    <div class="card" style="padding:1.5rem;">
      <h2 style="margin:0 0 1rem; font-size:1.25rem;">Top Customers</h2>
      <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
              <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Customer</th>
              <th style="text-align:center; padding:.5rem; font-size:.85rem; color:var(--muted);">Orders</th>
              <th style="text-align:right; padding:.5rem; font-size:.85rem; color:var(--muted);">Total Spent</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topCustomers)): ?>
              <tr>
                <td colspan="3" style="text-align:center; padding:2rem; color:var(--muted);">No customers yet</td>
              </tr>
            <?php else: ?>
              <?php foreach ($topCustomers as $customer): ?>
                <tr style="border-bottom:1px solid #f0f0f0;">
                  <td style="padding:.75rem .5rem;">
                    <div style="font-weight:600; margin-bottom:.125rem; font-size:.9rem; word-break:break-word;"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                    <div style="font-size:.75rem; color:var(--muted); word-break:break-all;"><?php echo htmlspecialchars($customer['customer_email']); ?></div>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:center; font-weight:600; font-size:.9rem;">
                    <?php echo $customer['order_count']; ?>
                  </td>
                  <td style="padding:.75rem .5rem; text-align:right; font-weight:700; color:var(--brown-1); font-size:.9rem;">
                    $<?php echo number_format($customer['total_spent'], 2); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
  <?php endif; ?>

  <!-- ==================== RETURNS SECTION ==================== -->
  <?php if (($returnMetrics['total_returns'] ?? 0) > 0): ?>
  <div class="card" style="padding:1.5rem; margin-bottom:2rem; background:rgba(220,53,69,.05); border-left:4px solid #dc3545;">
    <h2 style="margin:0 0 .5rem; font-size:1.5rem; color:#721c24;">Returns Overview</h2>
    <p style="margin:0 0 1.5rem; color:var(--muted);">Analysis of product returns and refunds</p>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:1rem; margin-bottom:2rem;">
      <div style="text-align:center; padding:1rem; background:#fff; border-radius:.5rem;">
        <div style="font-size:1.8rem; font-weight:700; color:#ffc107;"><?php echo $returnMetrics['pending_returns'] ?? 0; ?></div>
        <div style="font-size:.85rem; color:var(--muted); margin-top:.25rem;">Pending</div>
      </div>
      <div style="text-align:center; padding:1rem; background:#fff; border-radius:.5rem;">
        <div style="font-size:1.8rem; font-weight:700; color:#007bff;"><?php echo $returnMetrics['approved_returns'] ?? 0; ?></div>
        <div style="font-size:.85rem; color:var(--muted); margin-top:.25rem;">Approved</div>
      </div>
      <div style="text-align:center; padding:1rem; background:#fff; border-radius:.5rem;">
        <div style="font-size:1.8rem; font-weight:700; color:#dc3545;"><?php echo $returnMetrics['rejected_returns'] ?? 0; ?></div>
        <div style="font-size:.85rem; color:var(--muted); margin-top:.25rem;">Rejected</div>
      </div>
      <div style="text-align:center; padding:1rem; background:#fff; border-radius:.5rem;">
        <div style="font-size:1.8rem; font-weight:700; color:#28a745;"><?php echo $returnMetrics['refunded_returns'] ?? 0; ?></div>
        <div style="font-size:.85rem; color:var(--muted); margin-top:.25rem;">Refunded</div>
      </div>
    </div>

    <!-- Returns by Reason -->
    <?php if (!empty($returnsByReason)): ?>
      <h3 style="margin:0 0 1rem; font-size:1.1rem;">Returns by Reason</h3>
      <div style="background:#fff; padding:1rem; border-radius:.5rem;">
        <?php foreach ($returnsByReason as $reason): ?>
          <?php 
            $percentage = $returnMetrics['total_returns'] > 0 
              ? ($reason['count'] / $returnMetrics['total_returns']) * 100 
              : 0;
          ?>
          <div style="margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:.5rem; font-size:.9rem;">
              <span style="font-weight:600; text-transform:capitalize;"><?php echo str_replace('_', ' ', $reason['return_reason']); ?></span>
              <span style="color:var(--muted);"><?php echo $reason['count']; ?> returns ($<?php echo number_format($reason['total_amount'], 2); ?>)</span>
            </div>
            <div class="progress-bar" style="height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
              <div style="width:<?php echo $percentage; ?>%; height:100%; background:#dc3545; transition:width .3s;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ==================== RECENT RETURNS ==================== -->
  <?php if (!empty($recentReturns)): ?>
  <div class="card" style="padding:1.5rem;">
    <h2 style="margin:0 0 1rem; font-size:1.25rem;">Recent Returns</h2>
    <div style="overflow-x:auto;">
      <table style="width:100%; border-collapse:collapse; font-size:.9rem;">
        <thead>
          <tr style="border-bottom:2px solid #e5e7eb;">
            <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Return #</th>
            <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Order #</th>
            <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Customer</th>
            <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Reason</th>
            <th style="text-align:center; padding:.5rem; font-size:.85rem; color:var(--muted);">Status</th>
            <th style="text-align:right; padding:.5rem; font-size:.85rem; color:var(--muted);">Amount</th>
            <th style="text-align:left; padding:.5rem; font-size:.85rem; color:var(--muted);">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentReturns as $return): ?>
            <tr style="border-bottom:1px solid #f0f0f0;">
              <td style="padding:.75rem .5rem; font-weight:600;">
                <?php echo htmlspecialchars($return['return_number']); ?>
              </td>
              <td style="padding:.75rem .5rem;">
                <?php echo htmlspecialchars($return['order_number']); ?>
              </td>
              <td style="padding:.75rem .5rem;">
                <?php echo htmlspecialchars($return['customer_name']); ?>
              </td>
              <td style="padding:.75rem .5rem; text-transform:capitalize;">
                <?php echo str_replace('_', ' ', $return['return_reason']); ?>
              </td>
              <td style="padding:.75rem .5rem; text-align:center;">
                <span class="return-status-badge return-status-<?php echo $return['return_status']; ?>">
                  <?php echo ucfirst(str_replace('_', ' ', $return['return_status'])); ?>
                </span>
              </td>
              <td style="padding:.75rem .5rem; text-align:right; font-weight:700; color:#dc3545;">
                $<?php echo number_format($return['return_total'], 2); ?>
              </td>
              <td style="padding:.75rem .5rem; font-size:.85rem;">
                <?php echo date('M d, Y', strtotime($return['requested_at'])); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>