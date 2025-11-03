<?php
require_once 'config.php';
require_once 'check_session.php';

$slug = isset($_GET['slug']) ? strtolower(preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'])) : '';
if ($slug === '') {
  header('Location: products.php');
  exit();
}

$conn = getDBConnection();

// Load product
$ps = $conn->prepare('SELECT p.id, p.name, p.slug, p.description, p.price, p.image_filename, c.name AS category_name FROM products p INNER JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.is_active = 1 LIMIT 1');
$ps->bind_param('s', $slug);
$ps->execute();
$productRow = $ps->get_result()->fetch_assoc();
$ps->close();

if (!$productRow) {
  require __DIR__ . '/partials/header.php';
  echo '<main id="main" class="container" style="padding:2rem 0;"><h1>Product not found</h1><p><a href="products.php">Back to products</a></p></main>';
  require __DIR__ . '/partials/footer.php';
  exit();
}

$productId = (int)$productRow['id'];

// Admin edit handler (runs before we close the DB connection below)
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'edit_product'
    && isAdmin()) {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!verifyCsrfToken($csrf)) {
    $_SESSION['product_edit_error'] = 'Invalid security token.';
  } else {
    $newName = trim($_POST['name'] ?? '');
    $newDesc = trim($_POST['description'] ?? '');
    $newPrice = isset($_POST['price']) ? (float)$_POST['price'] : (float)$productRow['price'];
    $newImage = trim($_POST['image_filename'] ?? '');
    if ($newName === '' || $newPrice < 0) {
      $_SESSION['product_edit_error'] = 'Please provide a valid name and price.';
    } else {
      $up = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, image_filename = ? WHERE id = ? LIMIT 1');
      $up->bind_param('ssdsi', $newName, $newDesc, $newPrice, $newImage, $productId);
      if ($up->execute()) {
        $_SESSION['product_edit_success'] = 'Product updated successfully.';
        $up->close();
        // Reload product
        $ps = $conn->prepare('SELECT p.id, p.name, p.slug, p.description, p.price, p.image_filename, c.name AS category_name FROM products p INNER JOIN categories c ON p.category_id = c.id WHERE p.id = ? LIMIT 1');
        $ps->bind_param('i', $productId);
        $ps->execute();
        $productRow = $ps->get_result()->fetch_assoc() ?: $productRow;
        $ps->close();
      } else {
        $_SESSION['product_edit_error'] = 'Failed to update product.';
        $up->close();
      }
    }
  }
}

// Admin: save variation sizes/images directly on product page
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'save_variations'
    && isAdmin()) {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!verifyCsrfToken($csrf)) {
    $_SESSION['product_edit_error'] = 'Invalid security token.';
  } else if (!empty($_POST['variation']) && is_array($_POST['variation'])) {
    // helpers
    $ensureDir = function($path){ if(is_dir($path)) return true; if(@mkdir($path,0775,true)) return true; if(@mkdir($path,0777,true)) return true; return is_dir($path); };
    foreach ($_POST['variation'] as $vid => $vdata) {
      $variationId = (int)$vid;
      // Update sizes
      if (!empty($vdata['sizes']) && is_array($vdata['sizes'])) {
        foreach ($vdata['sizes'] as $srow) {
          $sid = isset($srow['id']) ? (int)$srow['id'] : 0;
          $del = !empty($srow['delete']);
          $size = isset($srow['size']) ? trim($srow['size']) : '';
          if ($size !== '' && strlen($size) > 32) { $size = substr($size,0,32); }
          $stock = isset($srow['stock']) ? (int)$srow['stock'] : 0;
          if ($sid > 0) {
            if ($del) {
              $ds = $conn->prepare('DELETE FROM variation_sizes WHERE id = ? AND variation_id = ?');
              $ds->bind_param('ii', $sid, $variationId); $ds->execute(); $ds->close();
            } else if ($size !== '') {
              $us = $conn->prepare('UPDATE variation_sizes SET size = ?, stock_quantity = ? WHERE id = ? AND variation_id = ?');
              $us->bind_param('siii', $size, $stock, $sid, $variationId); $us->execute(); $us->close();
            }
          } else if (!$del && $size !== '') {
            $is = $conn->prepare('INSERT INTO variation_sizes (variation_id, size, stock_quantity) VALUES (?, ?, ?)');
            $is->bind_param('isi', $variationId, $size, $stock); $is->execute(); $is->close();
          }
        }
      }
      // Existing images: sort/delete
      if (!empty($vdata['images']) && is_array($vdata['images'])) {
        foreach ($vdata['images'] as $im) {
          $iid = isset($im['id']) ? (int)$im['id'] : 0;
          if ($iid <= 0) continue;
          if (!empty($im['delete'])) {
            $di = $conn->prepare('DELETE FROM variation_images WHERE id = ? AND variation_id = ?');
            $di->bind_param('ii', $iid, $variationId); $di->execute(); $di->close();
          } else {
            $sort = isset($im['sort_order']) ? (int)$im['sort_order'] : 0;
            $ui = $conn->prepare('UPDATE variation_images SET sort_order = ? WHERE id = ? AND variation_id = ?');
            $ui->bind_param('iii', $sort, $iid, $variationId); $ui->execute(); $ui->close();
          }
        }
      }
      // Append new uploads
      $baseDir = __DIR__ . '/assets/images/products/' . $productRow['slug'] . '/' . $variationId;
      $ensureDir($baseDir);
      $field = 'var_images_' . $variationId;
      if (isset($_FILES[$field]) && is_array($_FILES[$field]['tmp_name'])) {
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $count = count($_FILES[$field]['tmp_name']);
        for ($i=0; $i<$count; $i++) {
          if (!is_uploaded_file($_FILES[$field]['tmp_name'][$i])) continue;
          $mime = function_exists('mime_content_type') ? @mime_content_type($_FILES[$field]['tmp_name'][$i]) : 'image/jpeg';
          if (!isset($allowed[$mime])) continue;
          $ext = $allowed[$mime];
          $name = uniqid('img_',true) . '.' . $ext;
          $dest = $baseDir . '/' . $name;
          if (@move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dest)) {
            $rel = 'assets/images/products/' . $productRow['slug'] . '/' . $variationId . '/' . $name;
            $ins = $conn->prepare('INSERT INTO variation_images (variation_id, image_filename, sort_order) VALUES (?, ?, 0)');
            $ins->bind_param('is', $variationId, $rel); $ins->execute(); $ins->close();
          }
        }
      }
    }

    // Add new variations (colour, sizes, images)
    if (!empty($_POST['new_variations']) && is_array($_POST['new_variations'])) {
      foreach ($_POST['new_variations'] as $idx => $nv) {
        $colour = isset($nv['colour']) ? trim($nv['colour']) : '';
        if ($colour === '') { continue; }
        if (strlen($colour) > 50) { $colour = substr($colour, 0, 50); }
        $active = isset($nv['is_active']) ? 1 : 0;
        $iv = $conn->prepare('INSERT INTO product_variations (product_id, colour, is_active) VALUES (?, ?, ?)');
        $iv->bind_param('isi', $productId, $colour, $active);
        if ($iv->execute()) {
          $newVarId = $iv->insert_id;
          $iv->close();
          // Sizes
          if (!empty($nv['sizes']) && is_array($nv['sizes'])) {
            foreach ($nv['sizes'] as $srow) {
              $size = isset($srow['size']) ? trim($srow['size']) : '';
              if ($size === '') { continue; }
              if (strlen($size) > 32) { $size = substr($size, 0, 32); }
              $stock = isset($srow['stock']) ? (int)$srow['stock'] : 0;
              $is = $conn->prepare('INSERT INTO variation_sizes (variation_id, size, stock_quantity) VALUES (?, ?, ?)');
              $is->bind_param('isi', $newVarId, $size, $stock);
              $is->execute();
              $is->close();
            }
          }
          // Images upload new_var_images_<idx>
          $baseDir = __DIR__ . '/assets/images/products/' . $productRow['slug'] . '/' . $newVarId;
          if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true) || @mkdir($baseDir, 0777, true); }
          $field = 'new_var_images_' . $idx;
          if (isset($_FILES[$field]) && is_array($_FILES[$field]['tmp_name'])) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $count = count($_FILES[$field]['tmp_name']);
            for ($i = 0; $i < $count; $i++) {
              if (!is_uploaded_file($_FILES[$field]['tmp_name'][$i])) { continue; }
              $mime = function_exists('mime_content_type') ? @mime_content_type($_FILES[$field]['tmp_name'][$i]) : 'image/jpeg';
              if (!isset($allowed[$mime])) { continue; }
              $ext = $allowed[$mime];
              $name = uniqid('img_', true) . '.' . $ext;
              $dest = $baseDir . '/' . $name;
              if (@move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dest)) {
                $rel = 'assets/images/products/' . $productRow['slug'] . '/' . $newVarId . '/' . $name;
                $ins = $conn->prepare('INSERT INTO variation_images (variation_id, image_filename, sort_order) VALUES (?, ?, 0)');
                $ins->bind_param('is', $newVarId, $rel);
                $ins->execute();
                $ins->close();
              }
            }
          }
        } else {
          $iv->close();
        }
      }
    }

    $_SESSION['product_edit_success'] = 'Variation changes saved.';
  }
}

// Load variations (colour-level)
$variations = [];
$vs = $conn->prepare('SELECT id, colour, is_active FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY id');
$vs->bind_param('i', $productId);
$vs->execute();
$vres = $vs->get_result();
while ($v = $vres->fetch_assoc()) {
  $variations[] = [
    'id' => (int)$v['id'],
    'colour' => $v['colour'] ?: 'Default',
    'sizes' => [],
    'images' => [],
  ];
}
$vs->close();

// Load sizes per variation
if (!empty($variations)) {
  $vids = array_map(function($v){return (int)$v['id'];}, $variations);
  $in = implode(',', array_fill(0, count($vids), '?'));
  $types = str_repeat('i', count($vids));
  $stmt = $conn->prepare("SELECT variation_id, size, stock_quantity FROM variation_sizes WHERE variation_id IN ($in) ORDER BY id");
  $stmt->bind_param($types, ...$vids);
  $stmt->execute();
  $rs = $stmt->get_result();
  $sizesMap = [];
  while ($row = $rs->fetch_assoc()) {
    $sizesMap[(int)$row['variation_id']][] = $row;
  }
  $stmt->close();
  foreach ($variations as &$vr) { $vr['sizes'] = isset($sizesMap[$vr['id']]) ? $sizesMap[$vr['id']] : []; }
  unset($vr);

  // Load first image per variation (and all images for thumbs if desired)
  $stmt2 = $conn->prepare("SELECT variation_id, image_filename FROM variation_images WHERE variation_id IN ($in) ORDER BY sort_order, id");
  $stmt2->bind_param($types, ...$vids);
  $stmt2->execute();
  $ri = $stmt2->get_result();
  $imgMap = [];
  while ($row = $ri->fetch_assoc()) { $imgMap[(int)$row['variation_id']][] = $row['image_filename']; }
  $stmt2->close();
  foreach ($variations as &$vr) { $vr['images'] = isset($imgMap[$vr['id']]) ? $imgMap[$vr['id']] : []; }
  unset($vr);
}

$conn->close();

// Determine initial variation index from ?color=
$requestedColor = isset($_GET['color']) ? strtolower(trim($_GET['color'])) : '';
$initialIndex = 0;
if ($requestedColor !== '') {
  foreach ($variations as $i => $vr) {
    if (strtolower($vr['colour']) === $requestedColor) { $initialIndex = $i; break; }
  }
}

// Initial sizes and image
$initialSizes = !empty($variations[$initialIndex]['sizes']) ? array_map(function($r){ return $r['size']; }, $variations[$initialIndex]['sizes']) : [];
if (empty($initialSizes)) { $initialSizes = []; }
$requestedSize = isset($_GET['size']) ? (string)$_GET['size'] : '';
$initialSizeIndex = 0;
if ($requestedSize !== '' && !empty($initialSizes)) {
  $sIdx = array_search($requestedSize, $initialSizes, true);
  if ($sIdx !== false) { $initialSizeIndex = (int)$sIdx; }
}

$initialImage = $variations[$initialIndex]['images'][0] ?? ($productRow['image_filename'] ?? '');

require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <section class="product container" aria-label="Product details">
    <div class="product__gallery">
      <div class="product-media" data-variant-index="<?php echo (int)$initialIndex; ?>">
        <div class="product-media__image" style="<?php echo $initialImage ? 'background-image:url(' . htmlspecialchars($initialImage) . '); background-size:cover; background-position:center;' : ''; ?>" aria-label="Product image" role="img"></div>
      </div>
      <div class="product-thumbs" aria-label="Gallery thumbnails" id="thumbs">
<?php 
$initialImages = $variations[$initialIndex]['images'];
if (empty($initialImages) && !empty($productRow['image_filename'])) { $initialImages = [$productRow['image_filename']]; }
foreach (($initialImages ?: []) as $ti => $img): ?>
        <button class="thumb<?php echo $ti === 0 ? ' is-active' : ''; ?>" data-img="<?php echo htmlspecialchars($img); ?>" aria-label="Thumbnail <?php echo (int)$ti + 1; ?>">
          <img src="<?php echo htmlspecialchars($img); ?>" alt="Thumbnail <?php echo (int)$ti + 1; ?>" style="width:64px; height:64px; object-fit:cover; display:block; border-radius:.25rem;">
        </button>
<?php endforeach; ?>
      </div>
    </div>

    <div class="product__info">
      <h1 class="product__title"><?php echo htmlspecialchars($productRow['name']); ?></h1>
      <div class="product__meta">
        <span class="product__price">$<?php echo number_format((float)$productRow['price']); ?></span>
        <span class="product__category"><?php echo htmlspecialchars($productRow['category_name']); ?></span>
      </div>
      <p class="product__desc"><?php echo htmlspecialchars($productRow['description']); ?></p>

      <div class="product__variants">
        <p class="product__selected">Colour: <span class="product__selected-name"></span></p>
        <div class="swatches" role="radiogroup" aria-label="Choose color">
<?php foreach ($variations as $i => $v): 
  $sizesList = array_map(function($r){ return $r['size']; }, $v['sizes']);
  $firstImg = $v['images'][0] ?? ($productRow['image_filename'] ?? '');
?>
          <button
            class="swatch<?php echo $i === $initialIndex ? ' is-selected' : ''; ?>"
            role="radio"
            aria-checked="<?php echo $i === $initialIndex ? 'true' : 'false'; ?>"
            data-variant-index="<?php echo (int)$i; ?>"
            data-variant-name="<?php echo htmlspecialchars($v['colour'], ENT_QUOTES); ?>"
            data-variant-image="<?php echo htmlspecialchars($firstImg); ?>"
            data-variant-sizes='<?php echo json_encode($sizesList, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG); ?>'
            data-variant-images='<?php echo json_encode($v['images'], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG); ?>'
          >
            <?php echo htmlspecialchars($v['colour'] ?: 'Variant'); ?>
          </button>
<?php endforeach; ?>
        </div>
      </div>
<br>
      <div class="product__sizes" data-size-index="<?php echo (int)$initialSizeIndex; ?>">
        <p class="product__selected">Size: <span class="product__selected-size"></span></p>
        <div class="sizes" role="radiogroup" aria-label="Choose size">
<?php foreach ($initialSizes as $i => $size): ?>
          <button
            class="size<?php echo $i === $initialSizeIndex ? ' is-selected' : ''; ?>"
            role="radio"
            aria-checked="<?php echo $i === $initialSizeIndex ? 'true' : 'false'; ?>"
            data-size-index="<?php echo (int)$i; ?>"
            data-size-value="<?php echo htmlspecialchars($size, ENT_QUOTES); ?>"
          ><?php echo htmlspecialchars($size); ?></button>
<?php endforeach; ?>
        </div>
      </div>

      <div class="product__actions">
        <form id="addToCartForm" method="POST" action="add_to_cart.php">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="product_id" value="<?php echo (int)$productId; ?>">
          <input type="hidden" name="product_slug" value="<?php echo htmlspecialchars($productRow['slug']); ?>">
          <input type="hidden" name="color" id="selectedColor" value="">
          <input type="hidden" name="size" id="selectedSize" value="">
          <input type="hidden" name="quantity" id="selectedQty" value="1">
          <button type="submit" class="btn btn--primary" id="addToCartBtn">Add to Cart</button>
        </form>
      </div>
      <?php if (isAdmin()): ?>
      <hr style="margin:1.25rem 0; border:0; border-top:1px solid rgba(0,0,0,.1);">
      <section class="card" style="padding:1rem;">
        <h2 class="card-title" style="margin:0 0 .5rem; font-size:1.1rem;">Admin • Edit Product</h2>
        <?php if (!empty($_SESSION['product_edit_success'])): ?>
          <div class="message message--success"><?php echo htmlspecialchars($_SESSION['product_edit_success']); unset($_SESSION['product_edit_success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['product_edit_error'])): ?>
          <div class="message message--error"><?php echo htmlspecialchars($_SESSION['product_edit_error']); unset($_SESSION['product_edit_error']); ?></div>
        <?php endif; ?>
        <form method="POST" action="product.php?slug=<?php echo urlencode($productRow['slug']); ?>" style="display:grid; gap:.5rem;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="edit_product">
          <div class="form-group">
            <label for="p_name">Name</label>
            <input id="p_name" name="name" type="text" value="<?php echo htmlspecialchars($productRow['name']); ?>" />
          </div>
          <div class="form-group">
            <label for="p_desc">Description</label>
            <textarea id="p_desc" name="description" rows="3"><?php echo htmlspecialchars($productRow['description']); ?></textarea>
          </div>
          <div class="form-group">
            <label for="p_price">Price</label>
            <input id="p_price" name="price" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars((string)$productRow['price']); ?>" />
          </div>
          <div class="form-group">
            <label for="p_image">Image Filename (URL)</label>
            <input id="p_image" name="image_filename" type="text" value="<?php echo htmlspecialchars($productRow['image_filename']); ?>" />
          </div>
          <div>
            <button type="submit" class="btn btn--primary">Save Changes</button>
          </div>
        </form>
      </section>
      <section class="card" style="padding:1rem; margin-top:1rem;">
        <h2 class="card-title" style="margin:0 0 .5rem; font-size:1.1rem;">Admin • Variations & Sizes</h2>
        <form method="POST" action="product.php?slug=<?php echo urlencode($productRow['slug']); ?>" enctype="multipart/form-data" style="display:grid; gap:1rem;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="action" value="save_variations">
          <?php foreach ($variations as $v): $vid=(int)$v['id']; $sizesFor = array_filter($variations[$vid-1]['sizes'] ?? [], function(){ return true; }); ?>
          <section style="border:1px solid #eee; border-radius:.5rem; padding:1rem;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem;">
              <div><strong><?php echo htmlspecialchars($v['colour']); ?></strong></div>
              <label style="display:flex; align-items:center; gap:.25rem;"><input type="checkbox" name="variation[<?php echo $vid; ?>][is_active]" <?php echo ((int)$v['is_active']===1?'checked':''); ?>> Active</label>
            </div>
            <div>
              <h3 style="margin:.25rem 0; font-size:1rem;"><em>Size / Stock</em></h3>
              <?php foreach (($variations[array_search($v, $variations)]['sizes'] ?? []) as $s): ?>
              <div class="form-row admin-size-row">
                <input type="hidden" name="variation[<?php echo $vid; ?>][sizes][][id]" value="<?php echo (int)$s['id']; ?>">
                <input type="text" name="variation[<?php echo $vid; ?>][sizes][][size]" value="<?php echo htmlspecialchars($s['size']); ?>" placeholder="Size" style="width:40px;">
                <input type="number" name="variation[<?php echo $vid; ?>][sizes][][stock]" value="<?php echo (int)$s['stock_quantity']; ?>" min="0" style="width:40px;">
                <label style="margin-left:.5rem; color:#b3261e;"><input type="checkbox" name="variation[<?php echo $vid; ?>][sizes][][delete]"></label>
              </div>
              <?php endforeach; ?>
              <div class="form-row admin-size-row">
                <input type="text" name="variation[<?php echo $vid; ?>][sizes][][size]" placeholder="New size" style="width:40px;">
                <input type="number" name="variation[<?php echo $vid; ?>][sizes][][stock]" placeholder="Stock" min="0" style="width:40px;">
              </div>
            </div>
            <div>
              <h3 style="margin:.25rem 0; font-size:1rem;">Images</h3>
              <?php $imgs = $variations[array_search($v, $variations)]['images'] ?? []; if (!empty($imgs)): ?>
              <div style="display:flex; flex-wrap:wrap; gap:.5rem;">
                <?php foreach ($imgs as $idxImg => $img): ?>
                  <div style="border:1px solid #ddd; border-radius:.5rem; padding:.5rem; display:flex; flex-direction:column; align-items:center; width:120px;">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="Image" style="width:100px; height:100px; object-fit:cover; border-radius:.25rem;">
                    <input type="hidden" name="variation[<?php echo $vid; ?>][images][][id]" value="<?php echo $idxImg+1; ?>">
                    <label style="margin-top:.25rem; font-size:.85rem;">Order <input type="number" name="variation[<?php echo $vid; ?>][images][][sort_order]" value="<?php echo $idxImg; ?>" style="width:60px;"></label>
                    <label style="color:#b3261e; margin-top:.25rem; font-size:.85rem;"><input type="checkbox" name="variation[<?php echo $vid; ?>][images][][delete]"></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
                <div class="notice">No images yet.</div>
              <?php endif; ?>
              <div class="form-group" style="margin-top:.5rem;">
                <label>Append Images</label>
                <input type="file" name="var_images_<?php echo $vid; ?>[]" accept="image/*" multiple>
              </div>
            </div>
          </section>
          <?php endforeach; ?>
          <section style="border:1px solid #eee; border-radius:.5rem; padding:1rem;">
            <h3 style="margin:0 0 .5rem; font-size:1rem;">Add New Variation</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Colour</label>
                <input type="text" name="new_variations[0][colour]" placeholder="e.g. Navy">
              </div>
              <div class="form-group" style="display:flex; align-items:flex-end;">
                <label style="display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="new_variations[0][is_active]" checked> Active</label>
              </div>
            </div>
            <div>
              <h4 style="margin:.25rem 0; font-size:1rem;"><em>Sizes</em></h4>
              <div class="form-row">
                <input type="text" name="new_variations[0][sizes][0][size]" placeholder="Size" style="width:140px;">
                <input type="number" name="new_variations[0][sizes][0][stock]" placeholder="Stock" min="0" style="width:120px;">
              </div>
            </div>
            <div class="form-group" style="margin-top:.5rem;">
              <label>Images</label>
              <input type="file" name="new_var_images_0[]" accept="image/*" multiple>
            </div>
          </section>
          <div>
            <button type="submit" class="btn btn--primary">Save Variation Changes</button>
          </div>
        </form>
      </section>
      <?php endif; ?>
    </div>
  </section>
</main>

<script>
  // Variant switching: update main image, name, sizes; deep-link (?color=)
  (function() {
    var media = document.querySelector('.product-media__image');
    var selectedNameEl = document.querySelector('.product__selected-name');
    var swatches = Array.prototype.slice.call(document.querySelectorAll('.swatch'));
    var selectedSizeEl = document.querySelector('.product__selected-size');
    var sizesContainer = document.querySelector('.sizes');
    var thumbsContainer = document.getElementById('thumbs');
    var hiddenColor = document.getElementById('selectedColor');
    var hiddenSize = document.getElementById('selectedSize');
    var addForm = document.getElementById('addToCartForm');
    function collectSizeButtons() { return Array.prototype.slice.call(document.querySelectorAll('.size')); }
    var sizeButtons = collectSizeButtons();
    if (!media || !selectedNameEl || !swatches.length || !sizesContainer) return;

    function selectVariantByIndex(idx) {
      swatches.forEach(function(s, i) {
        var isSel = (i === idx);
        s.classList.toggle('is-selected', isSel);
        s.setAttribute('aria-checked', isSel ? 'true' : 'false');
      });
      var sw = swatches[idx];
      if (!sw) return;
      var name = sw.getAttribute('data-variant-name') || '';
      var img = sw.getAttribute('data-variant-image') || '';
      var sizesJson = sw.getAttribute('data-variant-sizes') || '[]';
      var imagesJson = sw.getAttribute('data-variant-images') || '[]';
      selectedNameEl.textContent = name;
      if (hiddenColor) hiddenColor.value = name;
      if (img) {
        media.style.backgroundImage = 'url(' + img + ')';
        media.style.backgroundSize = 'cover';
        media.style.backgroundPosition = 'center';
      }
      // Rebuild sizes
      var sizes;
      try { sizes = JSON.parse(sizesJson); } catch (e) { sizes = []; }
      sizesContainer.innerHTML = '';
      if (!sizes || !sizes.length) {
        selectedSizeEl.textContent = 'N/A';
      } else {
        sizes.forEach(function(sz, i) {
          var btn = document.createElement('button');
          btn.className = 'size' + (i === 0 ? ' is-selected' : '');
          btn.setAttribute('role', 'radio');
          btn.setAttribute('aria-checked', i === 0 ? 'true' : 'false');
          btn.setAttribute('data-size-index', String(i));
          btn.setAttribute('data-size-value', sz);
          btn.textContent = sz;
          btn.addEventListener('click', function() {
            selectSizeByIndex(i);
          });
          sizesContainer.appendChild(btn);
        });
        sizeButtons = collectSizeButtons();
        selectSizeByIndex(0);
      }

      // Rebuild thumbnails
      if (thumbsContainer) {
        thumbsContainer.innerHTML = '';
        var images;
        try { images = JSON.parse(imagesJson); } catch (e) { images = []; }
        if ((!images || !images.length) && img) { images = [img]; }
        images.forEach(function(src, i) {
          var b = document.createElement('button');
          b.className = 'thumb' + (i === 0 ? ' is-active' : '');
          b.setAttribute('data-img', src);
          b.setAttribute('aria-label', 'Thumbnail ' + (i + 1));
          var im = document.createElement('img');
          im.src = src; im.alt = 'Thumbnail ' + (i + 1);
          im.style.width = '64px'; im.style.height = '64px'; im.style.objectFit = 'cover'; im.style.display = 'block'; im.style.borderRadius = '.25rem';
          b.appendChild(im);
          b.addEventListener('click', function() {
            Array.prototype.slice.call(thumbsContainer.querySelectorAll('.thumb')).forEach(function(t){ t.classList.remove('is-active'); });
            b.classList.add('is-active');
            media.style.backgroundImage = 'url(' + src + ')';
          });
          thumbsContainer.appendChild(b);
        });
      }
      // Update URL with ?color=
      var url = new URL(window.location.href);
      url.searchParams.set('color', String(name || '').toLowerCase());
      window.history.replaceState({}, '', url);
    }

    swatches.forEach(function(sw) {
      sw.addEventListener('click', function() {
        var idx = parseInt(sw.getAttribute('data-variant-index') || '0', 10);
        selectVariantByIndex(idx);
      });
    });

    // Init using default index provided by server
    var initialIndex = parseInt((document.querySelector('.product-media').getAttribute('data-variant-index') || '0'), 10);
    selectVariantByIndex(initialIndex);

    // Size selection with deep-link (?size=)
    function selectSizeByIndex(idx) {
      if (!sizeButtons.length || !selectedSizeEl) return;
      sizeButtons.forEach(function(btn, i) {
        var isSel = (i === idx);
        btn.classList.toggle('is-selected', isSel);
        btn.setAttribute('aria-checked', isSel ? 'true' : 'false');
      });
      var btn = sizeButtons[idx];
      if (!btn) return;
      var value = btn.getAttribute('data-size-value');
      selectedSizeEl.textContent = value;
      if (hiddenSize) hiddenSize.value = value;
      var url = new URL(window.location.href);
      url.searchParams.set('size', String(value || ''));
      window.history.replaceState({}, '', url);
    }

    sizeButtons.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var idx = parseInt(btn.getAttribute('data-size-index') || '0', 10);
        selectSizeByIndex(idx);
      });
    });

    var initialSizeIndex = parseInt((document.querySelector('.product__sizes').getAttribute('data-size-index') || '0'), 10);
    selectSizeByIndex(initialSizeIndex);

    // Wire up initial thumbnails click
    if (thumbsContainer) {
      Array.prototype.slice.call(thumbsContainer.querySelectorAll('.thumb')).forEach(function(b){
        b.addEventListener('click', function(){
          Array.prototype.slice.call(thumbsContainer.querySelectorAll('.thumb')).forEach(function(t){ t.classList.remove('is-active'); });
          b.classList.add('is-active');
          var src = b.getAttribute('data-img') || '';
          if (src) media.style.backgroundImage = 'url(' + src + ')';
        });
      });
    }
    // Validate on submit
    if (addForm) {
      addForm.addEventListener('submit', function(e){
        var color = hiddenColor ? (hiddenColor.value || '') : '';
        var size = hiddenSize ? (hiddenSize.value || '') : '';
        if (!color) {
          e.preventDefault();
          alert('Please select a color variant.');
          return false;
        }
        if (!size) {
          e.preventDefault();
          alert('Please select a size.');
          return false;
        }
        return true;
      });
    }
  })();
</script>

<script>
  // Admin UX helpers: add-size buttons and friendlier remove toggles
  (function(){
    var adminForms = document.querySelectorAll('form[action^="product.php"][enctype] input[name="action"][value="save_variations"]');
    if (!adminForms.length) return;
    // For each variation block
    var variationSections = document.querySelectorAll('section.card form[action^="product.php"][enctype] > section');
    variationSections.forEach(function(sec){
      // Add an "+ Add size" button if not present
      var sizesHeader = Array.prototype.slice.call(sec.querySelectorAll('h3')).filter(function(h){ return /size/i.test(h.textContent); })[0];
      if (sizesHeader) {
        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'btn';
        addBtn.textContent = '+ Add size';
        addBtn.style.margin = '.35rem 0';
        sizesHeader.parentNode.appendChild(addBtn);

        addBtn.addEventListener('click', function(){
          // determine variation id from any input name under this section
          var anyInput = sec.querySelector('input[name^="variation["]');
          if (!anyInput) return;
          var match = anyInput.name.match(/^variation\[(\d+)\]/);
          var vid = match ? match[1] : null;
          if (!vid) return;
          var row = document.createElement('div');
          row.className = 'form-row';
          row.style.margin = '.3rem 0';
          row.innerHTML = '<input type="text" name="variation['+vid+'][sizes][][size]" placeholder="New size" style="width:140px;">\n'
                        + '<input type="number" name="variation['+vid+'][sizes][][stock]" placeholder="Stock" min="0" style="width:120px;">\n'
                        + '<button type="button" class="btn-secondary" data-remove-inline style="color:#b3261e; margin-left:.5rem;">Remove</button>';
          sizesHeader.parentNode.appendChild(row);
          var removeBtn = row.querySelector('[data-remove-inline]');
          removeBtn.addEventListener('click', function(){ row.parentNode.removeChild(row); });
        });
      }
      // Friendlier remove for existing rows: toggle hidden delete checkboxes
      Array.prototype.slice.call(sec.querySelectorAll('label > input[type="checkbox"][name*="[delete]"]')).forEach(function(chk){
        var label = chk.parentNode;
        // hide the checkbox itself
        chk.style.display = 'none';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-secondary';
        btn.textContent = 'Remove';
        btn.style.color = '#b3261e';
        btn.style.marginLeft = '.5rem';
        label.appendChild(btn);
        btn.addEventListener('click', function(){
          chk.checked = !chk.checked;
          var row = label.closest('.form-row');
          if (row) { row.style.opacity = chk.checked ? '.5' : '1'; }
          btn.textContent = chk.checked ? 'Undo remove' : 'Remove';
        });
      });
    });
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>


