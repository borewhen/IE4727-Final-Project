<?php
require_once 'config.php';

// Temporary: surface errors during development
ini_set('display_errors', '1');
error_reporting(E_ALL);
// Avoid mysqli throwing fatal exceptions; we will handle errors manually
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

// Guard: only admins
// if (!isset($_SESSION['customer_id']) || !isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
//     header('Location: login.php');
//     exit();
// }

$conn = getDBConnection();

$message = '';
$errors = [];

// Helpers
function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: uniqid('product-');
}

function ensureDir($path) {
    if (is_dir($path)) { return true; }
    // Try 0775 first; if it fails, try 0777
    if (@mkdir($path, 0775, true)) { return true; }
    if (@mkdir($path, 0777, true)) { return true; }
    return is_dir($path);
}

function detectMime($filePath) {
    if (function_exists('mime_content_type')) {
        return @mime_content_type($filePath) ?: null;
    }
    if (function_exists('finfo_open')) {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = @finfo_file($f, $filePath);
            @finfo_close($f);
            return $m ?: null;
        }
    }
    return null;
}

// Unified submit: create product and multiple variations at once
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $slug = isset($_POST['slug']) && trim($_POST['slug']) !== '' ? trim($_POST['slug']) : slugify($name);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $stock_quantity = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
    $material = isset($_POST['material']) ? trim($_POST['material']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    $variations = isset($_POST['variations']) && is_array($_POST['variations']) ? $_POST['variations'] : [];

    if ($category_id <= 0) { $errors[] = 'Category is required.'; }
    if ($name === '') { $errors[] = 'Name is required.'; }
    if ($price <= 0) { $errors[] = 'Price must be greater than 0.'; }

    // Validate at least one variation
    if (empty($variations)) {
        $errors[] = 'Please add at least one variation.';
    } else {
        $hasValid = false;
        foreach ($variations as $v) {
            $vcolour = isset($v['colour']) ? trim($v['colour']) : '';
            $sizes = isset($v['sizes']) && is_array($v['sizes']) ? $v['sizes'] : [];
            $hasSize = false;
            foreach ($sizes as $s) {
                $sz = isset($s['size']) ? trim($s['size']) : '';
                if ($sz !== '') { $hasSize = true; break; }
            }
            // fallback to legacy single size field
            if (!$hasSize) {
                $legacy = isset($v['size']) ? trim($v['size']) : '';
                if ($legacy !== '') { $hasSize = true; }
            }
            if ($hasSize || $vcolour !== '') { $hasValid = true; break; }
        }
        if (!$hasValid) { $errors[] = 'Each variation must include at least one size.'; }
    }

    $image_filename = null;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mainMime = null;
    if (isset($_FILES['main_image']) && is_uploaded_file($_FILES['main_image']['tmp_name'])) {
        $mainMime = detectMime($_FILES['main_image']['tmp_name']);
        if (!isset($allowed[$mainMime])) { $errors[] = 'Main image must be JPG/PNG/WEBP.'; }
    }

    if (empty($errors)) {
        // Insert product
        $stmt = $conn->prepare("INSERT INTO products (category_id, name, slug, description, price, stock_quantity, brand, material, image_filename, is_active, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssdiissii', $category_id, $name, $slug, $description, $price, $stock_quantity, $brand, $material, $image_filename, $is_active, $is_featured);
        if ($stmt->execute()) {
            $productId = $stmt->insert_id;
            $stmt->close();

            // Prepare product image directory
            $baseUploadDir = __DIR__ . '/assets/images/products';
            $baseDir = $baseUploadDir . '/' . $slug;
            if (!ensureDir($baseUploadDir) || !ensureDir($baseDir)) {
                $errors[] = 'Failed to create image directory. Please check folder permissions for assets/images/products.';
            }

            // Save main image
            if (empty($errors) && isset($_FILES['main_image']) && is_uploaded_file($_FILES['main_image']['tmp_name']) && $mainMime) {
                $ext = $allowed[$mainMime];
                $safeName = 'main.' . $ext;
                $dest = $baseDir . '/' . $safeName;
                if (!@move_uploaded_file($_FILES['main_image']['tmp_name'], $dest)) {
                    $errors[] = 'Failed to save main image. Please verify folder permissions.';
                }
                $image_filename = 'assets/images/products/' . $slug . '/' . $safeName;
                $upd = $conn->prepare('UPDATE products SET image_filename=? WHERE id=?');
                $upd->bind_param('si', $image_filename, $productId);
                $upd->execute();
                $upd->close();
            }

            // Insert variations (colour-level) and their sizes/images
            $insertedCount = 0;
            foreach ($variations as $idx => $v) {
                $vcolour = isset($v['colour']) ? trim($v['colour']) : '';
                $vactive = isset($v['var_active']) ? 1 : 0;

                $sizes = isset($v['sizes']) && is_array($v['sizes']) ? $v['sizes'] : [];
                if (empty($sizes) && isset($v['size'])) {
                    // legacy single size/stock
                    $sizes = [[
                        'size' => $v['size'] ?? '',
                        'stock' => $v['var_stock'] ?? 0,
                    ]];
                }
                // Skip variation entirely if no sizes provided
                if (empty($sizes)) { continue; }

                // Enforce database length constraints
                if (strlen($vcolour) > 50) { $vcolour = substr($vcolour, 0, 50); }

                // Insert colour-level variation
                $vstmt = $conn->prepare('INSERT INTO product_variations (product_id, colour, is_active) VALUES (?, ?, ?)');
                $vstmt->bind_param('isi', $productId, $vcolour, $vactive);
                if ($vstmt->execute()) {
                    $variationId = $vstmt->insert_id;
                    $vstmt->close();
                    
                    // Insert size rows (per-size stock)
                    foreach ($sizes as $srow) {
                        $szName = isset($srow['size']) ? trim($srow['size']) : '';
                        if ($szName === '') { continue; }
                        if (strlen($szName) > 32) { $szName = substr($szName, 0, 32); }
                        $szStock = isset($srow['stock']) ? (int)$srow['stock'] : 0;
                        $sz = $conn->prepare('INSERT INTO variation_sizes (variation_id, size, stock_quantity) VALUES (?, ?, ?)');
                        $sz->bind_param('isi', $variationId, $szName, $szStock);
                        if ($sz->execute()) {
                            $insertedCount++;
                        } else {
                            $errors[] = 'Failed to add size for variation #' . ($idx + 1) . ': ' . $sz->error;
                        }
                        $sz->close();
                    }

                    $varDir = $baseDir . '/' . $variationId;
                    if (!ensureDir($varDir)) {
                        $errors[] = 'Failed to create variation directory for #' . $variationId . '. Check permissions.';
                        continue;
                    }

                    // Handle variation images: field names are variation_images_{index}[]
                    $field = 'variation_images_' . $idx;
                    if (isset($_FILES[$field]) && is_array($_FILES[$field]['tmp_name'])) {
                        $count = count($_FILES[$field]['tmp_name']);
                        for ($i = 0; $i < $count; $i++) {
                            if (!is_uploaded_file($_FILES[$field]['tmp_name'][$i])) { continue; }
                            $mime = detectMime($_FILES[$field]['tmp_name'][$i]);
                            if (!isset($allowed[$mime])) { continue; }
                            $ext = $allowed[$mime];
                            $safeName = uniqid('img_', true) . '.' . $ext;
                            $dest = $varDir . '/' . $safeName;
                            if (@move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dest)) {
                                $rel = 'assets/images/products/' . $slug . '/' . $variationId . '/' . $safeName;
                                $imgStmt = $conn->prepare('INSERT INTO variation_images (variation_id, image_filename, sort_order) VALUES (?, ?, 0)');
                                $imgStmt->bind_param('is', $variationId, $rel);
                                $imgStmt->execute();
                                $imgStmt->close();
                            }
                        }
                    }
                } else {
                    $errors[] = 'Failed to add variation #' . ($idx + 1) . ': ' . $vstmt->error;
                    $vstmt->close();
                }
            }

            if ($insertedCount === 0) {
                $errors[] = 'No sizes were saved. Please add at least one size.';
            } else {
                $message = 'Product created with ' . $insertedCount . ' size row(s).';
            }
        } else {
            $errors[] = 'Failed to create product: ' . $stmt->error;
            $stmt->close();
        }
    }
}

// Load categories for Step 1
$categories = [];
$res = $conn->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name');
if ($res) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
    $res->close();
}

// Rely on setup.sql for category seeding

// No per-product preview on this page; admin can navigate elsewhere to view

require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <div class="container" style="padding:2rem 0;">
    <h1>Add Product</h1>
    <?php if (!empty($message)): ?>
      <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="error-message">
        <ul style="margin:0; padding-left:1.1rem;">
          <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="admin_add_product.php" enctype="multipart/form-data" novalidate>
      <section style="margin-bottom:2rem;">
        <h2 style="margin-bottom:.5rem;">Product Details</h2>
        <div class="form-row">
          <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
              <option value="">Select category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" placeholder="auto-generated from name">
          </div>
          <div class="form-group">
            <label for="price">Price</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="stock_quantity">Stock (initial)</label>
            <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="0">
          </div>
          <div class="form-group">
            <label for="brand">Brand</label>
            <input type="text" id="brand" name="brand">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="material">Material</label>
            <input type="text" id="material" name="material">
          </div>
          <div class="form-group">
            <label for="main_image">Main Image</label>
            <input type="file" id="main_image" name="main_image" accept="image/*">
          </div>
        </div>
        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="4"></textarea>
        </div>
        <div class="form-row">
          <label style="display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="is_active" checked> Active</label>
          <label style="display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="is_featured"> Featured</label>
        </div>
      </section>

      <section>
        <h2 style="margin-bottom:.5rem;">Variations</h2>
        <p class="subtitle">For each variation (e.g., colour), add one or more sizes with stock. Use “Add Size” to add additional sizes for the same variation.</p>
        <div id="variationsContainer"></div>
        <button type="button" id="addVariationBtn" class="btn-secondary" style="margin-top:1rem;">Add Variation</button>
      </section>

      <div style="margin-top:2rem;">
        <button type="submit" class="btn-primary">Create Product with Variations</button>
      </div>
    </form>
  </div>
</main>

<script>
// Auto-generate slug from name; dynamic variation blocks
document.addEventListener('DOMContentLoaded', function() {
  const nameInput = document.getElementById('name');
  const slugInput = document.getElementById('slug');
  if (nameInput && slugInput) {
    nameInput.addEventListener('input', function() {
      if (slugInput.value.trim() === '') {
        const s = this.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        slugInput.value = s;
      }
    });
  }

  const variationsContainer = document.getElementById('variationsContainer');
  const addBtn = document.getElementById('addVariationBtn');
  let varIndex = 0;

  function createVariationBlock(index) {
    const wrapper = document.createElement('div');
    wrapper.className = 'card';
    wrapper.style.padding = '1rem';
    wrapper.style.marginTop = '.75rem';
    wrapper.dataset.index = index;

    wrapper.innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.5rem;">
        <strong>Variation #${index + 1}</strong>
        <button type="button" class="btn-secondary" data-remove>Remove</button>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Size</label>
          <div data-sizes></div>
          <button type="button" class="btn-secondary" data-add-size>Add Size</button>
        </div>
        <div class="form-group">
          <label>Colour</label>
          <input type="text" name="variations[${index}][colour]" placeholder="e.g. Navy">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group" style="display:flex; align-items:flex-end;">
          <label style="display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="variations[${index}][var_active]" checked> Active</label>
        </div>
      </div>
      <div class="form-group">
        <label>Variation Images</label>
        <input type="file" name="variation_images_${index}[]" accept="image/*" multiple>
        <small>Upload images for this variation.</small>
      </div>
    `;

    wrapper.querySelector('[data-remove]').addEventListener('click', function() {
      variationsContainer.removeChild(wrapper);
    });

    const sizesContainer = wrapper.querySelector('[data-sizes]');
    const addSizeBtn = wrapper.querySelector('[data-add-size]');
    let sizeIndex = 0;

    function addSizeRow() {
      const row = document.createElement('div');
      row.className = 'form-row';
      row.style.margin = '.5rem 0';
      row.innerHTML = `
        <div class="form-group">
          <input type="text" name="variations[${index}][sizes][${sizeIndex}][size]" placeholder="Size (e.g. S, M, 41)">
        </div>
        <div class="form-group">
          <input type="number" name="variations[${index}][sizes][${sizeIndex}][stock]" min="0" value="0" placeholder="Stock">
        </div>
        <div class="form-group" style="display:flex; align-items:center;">
          <button type="button" class="btn-secondary" data-remove-size>Remove</button>
        </div>
      `;
      row.querySelector('[data-remove-size]').addEventListener('click', function() {
        sizesContainer.removeChild(row);
      });
      sizesContainer.appendChild(row);
      sizeIndex++;
    }

    addSizeBtn.addEventListener('click', addSizeRow);
    // default first size row
    addSizeRow();

    return wrapper;
  }

  function addVariation() {
    const block = createVariationBlock(varIndex++);
    variationsContainer.appendChild(block);
  }

  addBtn.addEventListener('click', addVariation);
  // Start with one variation by default
  addVariation();
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>

