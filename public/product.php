<?php
require_once 'config.php';

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
        <p class="product__selected">Colour:</p>
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
        <p class="product__selected">Size:</p>
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
        <button class="btn btn--primary">Add to Cart</button>
      </div>
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
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>


