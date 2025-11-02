<?php
require_once 'config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['return_request_success']) || !$_SESSION['return_request_success']) {
    header('Location: profile.php');
    exit();
}

$return_number = $_SESSION['return_number'] ?? '';
$order_number = $_SESSION['return_order_number'] ?? '';

unset($_SESSION['return_request_success']);
unset($_SESSION['return_number']);
unset($_SESSION['return_order_number']);

require __DIR__ . '/partials/header.php';
?>

<main id="main" class="container" style="padding:2rem 0;">
  <div style="text-align:center; padding:2rem 0;">
    <div style="width:80px; height:80px; margin:0 auto 1rem; background:#28a745; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:3rem; font-weight:bold;">
      ✓
    </div>
    <h1 style="margin:0 0 .5rem; color:#28a745;">Return Request Submitted!</h1>
    <p class="subtitle" style="margin:0;">We've received your return request</p>
  </div>

  <div class="return-confirmation-layout">
    <div style="flex:1;">
      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Return Request Details</h2>
        <table style="width:100%;">
          <tr>
            <td style="padding:.5rem 0; color:var(--muted); width:40%;">Return Number:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo htmlspecialchars($return_number); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Order Number:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo htmlspecialchars($order_number); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Request Date:</td>
            <td style="padding:.5rem 0; font-weight:600;"><?php echo date('M d, Y'); ?></td>
          </tr>
          <tr>
            <td style="padding:.5rem 0; color:var(--muted);">Request Status:</td>
            <td style="padding:.5rem 0;">
              <span class="return-status-badge return-status-pending">Pending Review</span>
            </td>
          </tr>
        </table>
      </div>

      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">What Happens Next?</h2>
        <div class="next-steps-list">
          <div class="step-item">
            <div class="step-number">1</div>
            <div>
              <h3 style="margin:0 0 .5rem; font-size:1rem;">Review Process</h3>
              <p style="margin:0; color:var(--muted); font-size:.9rem;">Our team will review your return request within 1-2 business days.</p>
            </div>
          </div>
          
          <div class="step-item">
            <div class="step-number">2</div>
            <div>
              <h3 style="margin:0 0 .5rem; font-size:1rem;">Email Confirmation</h3>
              <p style="margin:0; color:var(--muted); font-size:.9rem;">You'll receive an email with approval status and next steps.</p>
            </div>
          </div>
          
          <div class="step-item">
            <div class="step-number">3</div>
            <div>
              <h3 style="margin:0 0 .5rem; font-size:1rem;">Return or Cancellation</h3>
              <p style="margin:0; color:var(--muted); font-size:.9rem;">Depending on order status, we'll either cancel or process your return.</p>
            </div>
          </div>
          
          <div class="step-item">
            <div class="step-number">4</div>
            <div>
              <h3 style="margin:0 0 .5rem; font-size:1rem;">Receive Refund</h3>
              <p style="margin:0; color:var(--muted); font-size:.9rem;">Refund will be processed within 5-7 business days after completion.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Track Your Return</h2>
        <p style="margin:0 0 1rem; color:var(--muted);">You can check the status of your return anytime from your profile page.</p>
        <a href="return-status.php?return=<?php echo urlencode($return_number); ?>" class="btn btn--primary">
          View Return Status
        </a>
      </div>
    </div>

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
        
        <div>
          <strong style="display:block; font-size:.85rem; color:var(--muted); margin-bottom:.25rem;">Hours</strong>
          <span style="font-size:.9rem;">Mon-Fri: 9AM-6PM</span>
        </div>
        
        <p style="margin-top:1rem; padding:.75rem; background:rgba(194,176,153,.1); border-radius:.5rem; font-size:.85rem;">
          Reference Return #<?php echo htmlspecialchars($return_number); ?>
        </p>
      </div>

      <div class="card" style="padding:1.5rem; margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem 0; font-size:1.25rem;">Return Policy</h2>
        <ul style="margin:0; padding-left:1.5rem; line-height:1.8; font-size:.9rem;">
          <li>Returns accepted at any order status</li>
          <li>Items must be unused and in original condition</li>
          <li>All tags must be attached</li>
          <li>Original packaging preferred</li>
        </ul>
      </div>

      <div style="display:flex; flex-direction:column; gap:1rem; text-align:center;">
        <a href="profile.php" class="btn btn--primary" style="width:100%;">Back to Profile</a>
        <a href="products.php" style="color:var(--brown-2); text-decoration:none;">Continue Shopping</a>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
