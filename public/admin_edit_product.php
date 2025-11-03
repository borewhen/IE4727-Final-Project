<?php
require_once 'config.php';
require_once 'check_session.php';

if (!isAdmin()) { header('Location: login.php'); exit(); }

$slug = isset($_GET['slug']) ? strtolower(preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'])) : '';
if ($slug === '') { header('Location: products.php'); exit(); }

$conn = getDBConnection();

// Load product
$ps = $conn->prepare('SELECT id, name, slug, description, price, image_filename FROM products WHERE slug = ? LIMIT 1');
$ps->bind_param('s', $slug);
$ps->execute();
$product = $ps->get_result()->fetch_assoc();
$ps->close();
if (!$product) { $conn->close(); header('Location: products.php'); exit(); }
$productId = (int)$product['id'];

function ensure_dir($p){ if(is_dir($p)) return true; if(@mkdir($p,0775,true)) return true; if(@mkdir($p,0777,true)) return true; return is_dir($p);} 

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='save' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
  // Update existing variations
  if (!empty($_POST['variation']) && is_array($_POST['variation'])) {
    foreach ($_POST['variation'] as $varId => $vdata) {
      $vid = (int)$varId;
      if (!empty($vdata['delete'])) {
        $del = $conn->prepare('DELETE FROM product_variations WHERE id = ? AND product_id = ?');
        $del->bind_param('ii', $vid, $productId);
        $del->execute();
        $del->close();
        continue;
      }
      $colour = trim($vdata['colour'] ?? '');
      if (strlen($colour) > 50) { $colour = substr($colour,0,50); }
      $active = isset($vdata['is_active']) ? 1 : 0;
      $up = $conn->prepare('UPDATE product_variations SET colour = ?, is_active = ? WHERE id = ? AND product_id = ?');
      $up->bind_param('siii', $colour, $active, $vid, $productId);
      $up->execute();
      $up->close();

      // Sizes: insert/update/delete
      if (!empty($vdata['sizes']) && is_array($vdata['sizes'])) {
        foreach ($vdata['sizes'] as $srow) {
          $sid = isset($srow['id']) ? (int)$srow['id'] : 0;
          $size = trim($srow['size'] ?? '');
          if ($size === '') continue;
          if (strlen($size) > 32) { $size = substr($size,0,32); }
          $stock = isset($srow['stock']) ? (int)$srow['stock'] : 0;
          $markDel = !empty($srow['delete']);
          if ($sid > 0) {
            if ($markDel) {
              $ds = $conn->prepare('DELETE FROM variation_sizes WHERE id = ? AND variation_id = ?');
              $ds->bind_param('ii', $sid, $vid);
              $ds->execute();
              $ds->close();
            } else {
              $us = $conn->prepare('UPDATE variation_sizes SET size = ?, stock_quantity = ? WHERE id = ? AND variation_id = ?');
              $us->bind_param('siii', $size, $stock, $sid, $vid);
              $us->execute();
              $us->close();
            }
          } else if (!$markDel) {
            $is = $conn->prepare('INSERT INTO variation_sizes (variation_id, size, stock_quantity) VALUES (?, ?, ?)');
            $is->bind_param('isi', $vid, $size, $stock);
            $is->execute();
            $is->close();
          }
        }
      }

      // Existing images: update sort or delete
      if (!empty($vdata['images']) && is_array($vdata['images'])) {
        foreach ($vdata['images'] as $imRow) {
          $iid = isset($imRow['id']) ? (int)$imRow['id'] : 0;
          if ($iid <= 0) { continue; }
          $del = !empty($imRow['delete']);
          $sort = isset($imRow['sort_order']) ? (int)$imRow['sort_order'] : 0;
          if ($del) {
            $di = $conn->prepare('DELETE FROM variation_images WHERE id = ? AND variation_id = ?');
            $di->bind_param('ii', $iid, $vid);
            $di->execute();
            $di->close();
          } else {
            $ui = $conn->prepare('UPDATE variation_images SET sort_order = ? WHERE id = ? AND variation_id = ?');
            $ui->bind_param('iii', $sort, $iid, $vid);
            $ui->execute();
            $ui->close();
          }
        }
      }

      // Append images
      $baseDir = __DIR__ . '/assets/images/products/' . $product['slug'] . '/' . $vid;
      ensure_dir($baseDir);
      $field = 'var_images_' . $vid;
      if (isset($_FILES[$field]) && is_array($_FILES[$field]['tmp_name'])) {
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $count = count($_FILES[$field]['tmp_name']);
        for ($i=0;$i<$count;$i++) {
          if (!is_uploaded_file($_FILES[$field]['tmp_name'][$i])) continue;
          $mime = function_exists('mime_content_type') ? @mime_content_type($_FILES[$field]['tmp_name'][$i]) : 'image/jpeg';
          if (!isset($allowed[$mime])) continue;
          $ext = $allowed[$mime];
          $name = uniqid('img_',true).'.'.$ext;
          $dest = $baseDir.'/'.$name;
          if (@move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dest)) {
            $rel = 'assets/images/products/'.$product['slug'].'/'.$vid.'/'.$name;
            $ins = $conn->prepare('INSERT INTO variation_images (variation_id, image_filename, sort_order) VALUES (?, ?, 0)');
            $ins->bind_param('is', $vid, $rel);
            $ins->execute();
            $ins->close();
          }
        }
      }
    }
  }

  // Add new variations
  if (!empty($_POST['new_variations']) && is_array($_POST['new_variations'])) {
    foreach ($_POST['new_variations'] as $idx => $nv) {
      $colour = trim($nv['colour'] ?? '');
      if ($colour === '') continue;
      if (strlen($colour) > 50) { $colour = substr($colour,0,50); }
      $active = isset($nv['is_active']) ? 1 : 0;
      $iv = $conn->prepare('INSERT INTO product_variations (product_id, colour, is_active) VALUES (?, ?, ?)');
      $iv->bind_param('isi', $productId, $colour, $active);
      if ($iv->execute()) {
        $newVarId = $iv->insert_id; $iv->close();
        // sizes
        if (!empty($nv['sizes'])) {
          foreach ($nv['sizes'] as $srow) {
            $size = trim($srow['size'] ?? '');
            if ($size === '') continue;
            if (strlen($size) > 32) $size = substr($size,0,32);
            $stock = isset($srow['stock']) ? (int)$srow['stock'] : 0;
            $is = $conn->prepare('INSERT INTO variation_sizes (variation_id, size, stock_quantity) VALUES (?, ?, ?)');
            $is->bind_param('isi', $newVarId, $size, $stock);
            $is->execute(); $is->close();
          }
        }
        // images upload new_var_images_<idx>
        $baseDir = __DIR__ . '/assets/images/products/' . $product['slug'] . '/' . $newVarId; ensure_dir($baseDir);
        $field = 'new_var_images_' . $idx;
        if (isset($_FILES[$field]) && is_array($_FILES[$field]['tmp_name'])) {
          $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
          $count = count($_FILES[$field]['tmp_name']);
          for ($i=0;$i<$count;$i++) {
            if (!is_uploaded_file($_FILES[$field]['tmp_name'][$i])) continue;
            $mime = function_exists('mime_content_type') ? @mime_content_type($_FILES[$field]['tmp_name'][$i]) : 'image/jpeg';
            if (!isset($allowed[$mime])) continue;
            $ext = $allowed[$mime];
            $name = uniqid('img_',true).'.'.$ext;
            $dest = $baseDir.'/'.$name;
            if (@move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dest)) {
              $rel = 'assets/images/products/'.$product['slug'].'/'.$newVarId.'/'.$name;
              $ins = $conn->prepare('INSERT INTO variation_images (variation_id, image_filename, sort_order) VALUES (?, ?, 0)');
              $ins->bind_param('is', $newVarId, $rel);
              $ins->execute(); $ins->close();
            }
          }
        }
      } else { $iv->close(); }
    }
  }

  $_SESSION['product_edit_success'] = 'Changes saved.';
  header('Location: admin_edit_product.php?slug=' . urlencode($slug));
  exit();
}

// Load variations + sizes + images for view
$variations = [];
$vs = $conn->prepare('SELECT id, colour, is_active FROM product_variations WHERE product_id = ? ORDER BY id');
$vs->bind_param('i', $productId);
$vs->execute();
$vres = $vs->get_result();
while ($v = $vres->fetch_assoc()) { $variations[] = $v; }
$vs->close();

$sizesMap = [];
$imagesMap = [];
if (!empty($variations)) {
  $vids = array_map(function($v){return (int)$v['id'];}, $variations);
  $in = implode(',', array_fill(0, count($vids), '?'));
  $types = str_repeat('i', count($vids));
  $s = $conn->prepare("SELECT id, variation_id, size, stock_quantity FROM variation_sizes WHERE variation_id IN ($in) ORDER BY id");
  $s->bind_param($types, ...$vids);
  $s->execute();
  $rs = $s->get_result();
  while ($row = $rs->fetch_assoc()) { $sizesMap[(int)$row['variation_id']][] = $row; }
  $s->close();

  $q = $conn->prepare("SELECT id, variation_id, image_filename, sort_order FROM variation_images WHERE variation_id IN ($in) ORDER BY sort_order, id");
  $q->bind_param($types, ...$vids);
  $q->execute();
  $ri = $q->get_result();
  while ($row = $ri->fetch_assoc()) { $imagesMap[(int)$row['variation_id']][] = $row; }
  $q->close();
}

require __DIR__ . '/partials/header.php';
?>
<main id="main" class="container" style="padding:2rem 0;">
  <h1>Edit Product – <?php echo htmlspecialchars($product['name']); ?></h1>
  <?php if (!empty($_SESSION['product_edit_success'])): ?><div class="success-message"><?php echo htmlspecialchars($_SESSION['product_edit_success']); unset($_SESSION['product_edit_success']); ?></div><?php endif; ?>
  <form method="POST" action="admin_edit_product.php?slug=<?php echo urlencode($slug); ?>" enctype="multipart/form-data" style="display:grid; gap:1rem;">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="action" value="save">

    <?php foreach ($variations as $v): $vid=(int)$v['id']; $sizes = $sizesMap[$vid] ?? []; $imgs = $imagesMap[$vid] ?? []; ?>
    <section class="card" style="padding:1rem;">
      <h2 class="card-title" style="margin:0 0 .5rem;">Variation #<?php echo $vid; ?></h2>
      <div class="form-row">
        <div class="form-group">
          <label>Colour</label>
          <input type="text" name="variation[<?php echo $vid; ?>][colour]" value="<?php echo htmlspecialchars($v['colour']); ?>">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end; gap:.5rem;">
          <label style="display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="variation[<?php echo $vid; ?>][is_active]" <?php echo ((int)$v['is_active']===1?'checked':''); ?>> Active</label>
          <label style="display:flex; align-items:center; gap:.5rem; color:#b3261e;"><input type="checkbox" name="variation[<?php echo $vid; ?>][delete]"> Delete</label>
        </div>
      </div>
      <div>
        <h3>Sizes</h3>
        <div data-sizes>
          <?php foreach ($sizes as $s): ?>
            <div class="form-row" style="margin:.4rem 0;">
              <input type="hidden" name="variation[<?php echo $vid; ?>][sizes][][id]" value="<?php echo (int)$s['id']; ?>">
              <input type="text" name="variation[<?php echo $vid; ?>][sizes][][size]" value="<?php echo htmlspecialchars($s['size']); ?>" placeholder="Size" style="width:140px;">
              <input type="number" name="variation[<?php echo $vid; ?>][sizes][][stock]" value="<?php echo (int)$s['stock_quantity']; ?>" min="0" style="width:120px;">
              <label style="display:flex; align-items:center; gap:.25rem; margin-left:.5rem;"><input type="checkbox" name="variation[<?php echo $vid; ?>][sizes][][delete]"> Delete</label>
            </div>
          <?php endforeach; ?>
          <div class="form-row" style="margin:.4rem 0;">
            <input type="text" name="variation[<?php echo $vid; ?>][sizes][][size]" placeholder="New size" style="width:140px;">
            <input type="number" name="variation[<?php echo $vid; ?>][sizes][][stock]" placeholder="Stock" min="0" style="width:120px;">
          </div>
        </div>
      </div>
      <div class="form-group">
        <h3>Images</h3>
        <?php if (!empty($imgs)): ?>
        <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
          <?php foreach ($imgs as $im): ?>
            <div style="border:1px solid #ddd; border-radius:.5rem; padding:.5rem; display:flex; flex-direction:column; align-items:center; width:120px;">
              <img src="<?php echo htmlspecialchars($im['image_filename']); ?>" alt="Image" style="width:100px; height:100px; object-fit:cover; border-radius:.25rem;">
              <input type="hidden" name="variation[<?php echo $vid; ?>][images][][id]" value="<?php echo (int)$im['id']; ?>">
              <label style="margin-top:.25rem; font-size:.85rem;">Order <input type="number" name="variation[<?php echo $vid; ?>][images][][sort_order]" value="<?php echo (int)$im['sort_order']; ?>" style="width:60px;"></label>
              <label style="color:#b3261e; margin-top:.25rem; font-size:.85rem;"><input type="checkbox" name="variation[<?php echo $vid; ?>][images][][delete]"> Delete</label>
            </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="notice">No images yet.</div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>Append Images</label>
        <input type="file" name="var_images_<?php echo $vid; ?>[]" accept="image/*" multiple>
      </div>
    </section>
    <?php endforeach; ?>

    <section class="card" style="padding:1rem;">
      <h2 class="card-title" style="margin:0 0 .5rem;">Add New Variation</h2>
      <div class="form-row">
        <div class="form-group">
          <label>Colour</label>
          <input type="text" name="new_variations[0][colour]" placeholder="e.g. Navy">
        </div>
        <div class="form-group" style="display:flex; align-items:flex-end;">
          <label style="display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="new_variations[0][is_active]" checked> Active</label>
        </div>
      </div>
      <h3>Sizes</h3>
      <div class="form-row" style="margin:.4rem 0;">
        <input type="text" name="new_variations[0][sizes][0][size]" placeholder="Size" style="width:140px;">
        <input type="number" name="new_variations[0][sizes][0][stock]" placeholder="Stock" min="0" style="width:120px;">
      </div>
      <div class="form-group">
        <label>Images</label>
        <input type="file" name="new_var_images_0[]" accept="image/*" multiple>
      </div>
    </section>

    <div>
      <button type="submit" class="btn btn--primary">Save All Changes</button>
      <a href="product.php?slug=<?php echo urlencode($slug); ?>" class="link" style="margin-left:.5rem;">Back to Product</a>
    </div>
  </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>


