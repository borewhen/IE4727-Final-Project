<?php
require_once 'config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

$return_number = isset($_GET['return']) ? trim($_GET['return']) : '';

if (empty($return_number)) {
    header('Location: profile.php');
    exit();
}

$conn = getDBConnection();

// Get return details with order info
$stmt = $conn->prepare("
    SELECT 
        r.*,
        o.order_number,
        o.customer_name,
        o.customer_email
    FROM returns r
    JOIN orders o ON r.order_id = o.id
    WHERE r.return_number = ? AND r.customer_id = ?
");
$stmt->bind_param("si", $return_number, $_SESSION['customer_id']);
$stmt->execute();
$result = $stmt->get_result();
$return = $result->fetch_assoc();
$stmt->close();

if (!$return) {
    $conn->close();
    header('Location: profile.php');
    exit();
}

// Get return items
$stmt = $conn->prepare("
    SELECT 
        ri.*,
        oi.product_name,
        oi.product_image_filename,
        oi.size,
        oi.color,
        oi.unit_price,
        oi.line_total
    FROM return_items ri
    JOIN order_items oi ON ri.order_item_id = oi.id
    WHERE ri.return_id = ?
");
$stmt->bind_param("i", $return['id']);
$stmt->execute();
$result = $stmt->get_result();
$return_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Status timeline
$timeline = [
    'pending' => [
        'label' => 'Pending Review',
        'icon' => '📝',
        'description' => 'Your return request is being reviewed by our team',
        'active' => true
    ],
    'approved' => [
        'label' => 'Approved',
        'icon' => '✓',
        'description' => 'Your return has been approved. Return shipping instructions sent to your email.',
        'active' => !empty($return['approved_at'])
    ],
    'items_received' => [
        'label' => 'Items Received',
        'icon' => '📦',
        'description' => 'We have received your returned items and are inspecting them',
        'active' => !empty($return['items_received_at'])
    ],
    'completed' => [
        'label' => 'Completed',
        'icon' => '✓',
        'description' => 'Return completed. Items inspected and accepted.',
        'active' => !empty($return['completed_at'])
    ],
    'refunded' => [
        'label' => 'Refunded',
        'icon' => '💰',
        'description' => 'Refund has been processed to your account',
        'active' => !empty($return['refunded_at'])
    ]
];

// Handle rejected status
$is_rejected = ($return['return_status'] === 'rejected');

require __DIR__ . '/partials/header.php';
?>

<main id="main" class="container" style="padding:2rem 0;">
  <div style="margin-bottom:1.5rem;">
    <a href="profile.php" style="color:var(--brown-2); text-decoration:none; font-size:.9rem;">← Back to Profile</a>
  </div>

  <h1>Return Status</h1>
  <p class="subtitle">Return #<?php echo htmlspecialchars($return['return_number']); ?></p>

  <div class="return-status-layout">
    <div style="flex:1;">
      
      <?php if ($is_rejected): ?>
        <!-- Rejected Notice -->
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem; background:rgba(220,53,69,.1); border-left:4px solid #dc3545;">
          <h2 style="margin:0 0 .75rem 0; font-size:1.25rem; color:#721c24;">Return Request Rejected</h2>
          <p style="margin:0 0 .75rem; color:var(--muted);">Your return request was reviewed and unfortunately could not be approved.</p>
          <?php if (!empty($return['rejection_reason'])): ?>
            <div style="padding:1rem; background:#fff; border-radius:.5rem; margin-top:1rem;">
              <strong style="display:block; margin-bottom:.5rem;">Reason:</strong>
              <p style="margin:0;"><?php echo nl2br(htmlspecialchars($return['rejection_reason'])); ?></p>
            </div>
          <?php endif; ?>
          <p style="margin-top:1rem; font-size:.9rem; color:var(--muted);">
            If you have questions, please contact customer support at returns@stirlings.com
          </p>
        </div>
      <?php else: ?>
        <!-- Status Timeline -->
        <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
          <h2 style="margin:0 0 1.5rem 0; font-size:1.25rem;">Return Progress</h2>
          <div class="return-timeline">
            <?php foreach ($timeline as $status => $info): ?>
              <?php 
                $is_current = ($return['return_status'] === $status);
                $is_complete = $info['active'];
              ?>
              <div class="timeline-item <?php echo $is_complete ? 'timeline-complete' : ''; ?> <?php echo $is_current ? 'timeline-current' : ''; ?>">
                <div class="timeline-icon">
                  <?php if ($is_complete): ?>
                    <span style="color:#28a745;">✓</span>
                  <?php else: ?>
                    <span style="color:#ccc;">○</span>
                  <?php endif; ?>
                </div>
                <div class="timeline-content">
                  <h3 style="margin:0 0 .25rem; font-size:1rem; font-weight:700;"><?php echo $info['label']; ?></h3>
                  <p style="margin:0; font-size:.9rem; color:var(--muted);"><?php echo $info['description']; ?></p>
                  <?php if ($is_current): ?>
                    <span class="return-status-badge return-status-<?php echo $status; ?>" style="margin-top:.5rem; display:inline-block;">
                      Current Status
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Return Details -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Return Details</h2>
        <table style="width:100%;">
          <tr>
            <td style="padding:.5rem 0; color:var(--muted); width:40%;">Return Number:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo htmlspecialchars($return['return_number']); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Order Number:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo htmlspecialchars($return['order_number']); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Return Status:</td>
            <td style="padding:.5rem 0;">
              <span class="return-status-badge return-status-<?php echo $return['return_status']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $return['return_status'])); ?>
              </span>
            </td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Requested Date:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo date('M d, Y', strtotime($return['requested_at'])); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Return Reason:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo ucfirst(str_replace('_', ' ', $return['return_reason'])); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Refund Method:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo ucfirst(str_replace('_', ' ', $return['refund_method'])); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Return Total:</td>
            <td style="padding:.5rem 0; font-weight:700; color:var(--brown-1);">$<?php echo number_format($return['return_total'], 2); ?></td>
          </tr>
          <?php if (!empty($return['return_shipping_tracking'])): ?>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Tracking Number:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo htmlspecialchars($return['return_shipping_tracking']); ?></td>
          </tr>
          <?php endif; ?>
        </table>
      </div>

      <!-- Return Details Description -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 .75rem 0; font-size:1.25rem;">Your Message</h2>
        <p style="margin:0; line-height:1.7; color:var(--text);">
          <?php echo nl2br(htmlspecialchars($return['return_details'])); ?>
        </p>
      </div>

      <?php if (!empty($return['admin_notes'])): ?>
      <!-- Admin Notes -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem; background:rgba(0,123,255,.05); border-left:4px solid #007bff;">
        <h2 style="margin:0 0 .75rem 0; font-size:1.25rem;">Note from Team</h2>
        <p style="margin:0; line-height:1.7;">
          <?php echo nl2br(htmlspecialchars($return['admin_notes'])); ?>
        </p>
      </div>
      <?php endif; ?>

      <!-- Returned Items -->
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Returned Items</h2>
        <div class="returned-items-list">
          <?php foreach ($return_items as $item): ?>
            <div style="display:flex; gap:1rem; padding:1rem; border:1px solid #e5e7eb; border-radius:.5rem; margin-bottom:.75rem;">
              <?php if (!empty($item['product_image_filename'])): ?>
                <img src="<?php echo htmlspecialchars($item['product_image_filename']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width:60px; height:60px; object-fit:cover; border-radius:.5rem; flex-shrink:0;">
              <?php endif; ?>
              <div style="flex:1;">
                <div style="font-weight:600; margin-bottom:.25rem;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                <div style="font-size:.85rem; color:var(--muted);">
                  <?php if (!empty($item['size'])): ?>Size: <?php echo htmlspecialchars($item['size']); ?> • <?php endif; ?>
                  <?php if (!empty($item['color'])): ?>Color: <?php echo htmlspecialchars($item['color']); ?> • <?php endif; ?>
                  Qty: <?php echo $item['quantity']; ?>
                </div>
                <div style="font-weight:700; margin-top:.25rem; color:var(--brown-1);">$<?php echo number_format($item['line_total'], 2); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- Sidebar -->
    <div style="width:100%; max-width:350px;">
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Need Help?</h2>
        <p style="margin-bottom:1rem; font-size:.9rem;">Questions about your return?</p>
        
        <div style="margin-bottom:1rem;">
          <strong style="display:block; font-size:.85rem; color:var(--muted); margin-bottom:.25rem;">Email</strong>
          <a href="mailto:returns@stirlings.com" style="color:var(--brown-2); text-decoration:none;">returns@stirlings.com</a>
        </div>
        
        <div style="margin-bottom:1rem;">
          <strong style="display:block; font-size:.85rem; color:var(--muted); margin-bottom:.25rem;">Phone</strong>
          <a href="tel:1-800-RETURNS" style="color:var(--brown-2); text-decoration:none;">1-800-RETURNS</a>
        </div>
        
        <p style="margin-top:1rem; padding:.75rem; background:rgba(194,176,153,.1); border-radius:.5rem; font-size:.85rem;">
          Reference Return #<?php echo htmlspecialchars($return['return_number']); ?>
        </p>
      </div>

      <?php if ($return['return_status'] === 'approved' && empty($return['items_received_at'])): ?>
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem; background:rgba(40,167,69,.1);">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Next Steps</h2>
        <p style="margin:0; font-size:.9rem; line-height:1.7;">
          Your return has been approved! Check your email for return shipping instructions and prepaid label.
        </p>
      </div>
      <?php endif; ?>

      <div style="text-align:center;">
        <a href="profile.php" class="btn btn--primary" style="width:100%;">Back to Profile</a>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
