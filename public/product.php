<?php
require __DIR__ . '/partials/header.php';

// Placeholder catalog data keyed by slug until DB integration
$products = [
  'oxford-leather' => [
    'title' => "Oxford Leather",
    'price' => 129,
    'category' => 'Shoes',
    'description' => 'A refined oxford crafted from full-grain leather. Cushioned insole for day-long comfort and a resilient outsole for traction.',
    'variants' => [
      ['name' => 'Chestnut', 'color' => '#8b5a2b'],
      ['name' => 'Black', 'color' => '#111111'],
      ['name' => 'Oxblood', 'color' => '#5b2731'],
    ],
    'sizes' => ['40', '41', '42', '43', '44'],
  ],
  'minimal-sneaker' => [
    'title' => 'Minimal Sneaker',
    'price' => 89,
    'category' => 'Shoes',
    'description' => 'Streamlined silhouette in supple faux leather with breathable lining. Versatile for casual and smart-casual fits.',
    'variants' => [
      ['name' => 'White', 'color' => '#eaeaea'],
      ['name' => 'Cream', 'color' => '#ddd2c2'],
      ['name' => 'Slate', 'color' => '#5e6a73'],
    ],
    'sizes' => ['40', '41', '42', '43', '44'],
  ],
  'poplin-shirt' => [
    'title' => 'Poplin Shirt',
    'price' => 59,
    'category' => 'Shirts',
    'description' => 'Crisp cotton-poplin with a silky hand-feel. Tailored fit with a clean placket for effortless layering.',
    'variants' => [
      ['name' => 'Sky', 'color' => '#b3c7e6'],
      ['name' => 'White', 'color' => '#f5f5f5'],
      ['name' => 'Navy', 'color' => '#1f2a44'],
    ],
    'sizes' => ['S', 'M', 'L', 'XL'],
  ],
];

$slug = isset($_GET['slug']) ? strtolower(preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'])) : 'oxford-leather';
$product = $products[$slug] ?? reset($products);

// Default to first variant if no color query
$variantNames = array_map(function ($v) { return strtolower($v['name']); }, $product['variants']);
$requestedColor = isset($_GET['color']) ? strtolower($_GET['color']) : '';
$initialIndex = 0;
if ($requestedColor) {
  $idx = array_search($requestedColor, $variantNames, true);
  if ($idx !== false) { $initialIndex = (int)$idx; }
}

// Sizes handling with deep-link support (?size=)
$sizes = $product['sizes'] ?? ['S','M','L','XL'];
$requestedSize = isset($_GET['size']) ? strtolower($_GET['size']) : '';
$sizeNames = array_map('strtolower', $sizes);
$initialSizeIndex = 0;
if ($requestedSize) {
  $sIdx = array_search($requestedSize, $sizeNames, true);
  if ($sIdx !== false) { $initialSizeIndex = (int)$sIdx; }
}
?>
<main id="main">
  <section class="product container" aria-label="Product details">
    <div class="product__gallery">
      <div class="product-media" data-variant-index="<?php echo (int)$initialIndex; ?>">
        <div class="product-media__image" style="--media-color: <?php echo htmlspecialchars($product['variants'][$initialIndex]['color'], ENT_QUOTES); ?>;" aria-label="Product image" role="img"></div>
      </div>
      <div class="product-thumbs" aria-label="Gallery thumbnails">
        <!-- Decorative placeholders; can become real images later -->
        <button class="thumb is-active" data-index="0" aria-label="Primary view"><span class="visually-hidden">Primary view</span></button>
        <button class="thumb" data-index="1" aria-label="Detail view"><span class="visually-hidden">Detail view</span></button>
        <button class="thumb" data-index="2" aria-label="On-foot view"><span class="visually-hidden">On-foot view</span></button>
      </div>
    </div>

    <div class="product__info">
      <h1 class="product__title"><?php echo htmlspecialchars($product['title']); ?></h1>
      <div class="product__meta">
        <span class="product__price">$<?php echo number_format($product['price']); ?></span>
        <span class="product__category"><?php echo htmlspecialchars($product['category']); ?></span>
      </div>
      <p class="product__desc"><?php echo htmlspecialchars($product['description']); ?></p>

      <div class="product__variants">
        <p class="product__selected">Color:
          <strong class="product__selected-name"><?php echo htmlspecialchars($product['variants'][$initialIndex]['name']); ?></strong>
        </p>
        <div class="swatches" role="radiogroup" aria-label="Choose color">
<?php foreach ($product['variants'] as $i => $v): ?>
          <button
            class="swatch<?php echo $i === $initialIndex ? ' is-selected' : ''; ?>"
            role="radio"
            aria-checked="<?php echo $i === $initialIndex ? 'true' : 'false'; ?>"
            data-variant-index="<?php echo (int)$i; ?>"
            data-variant-name="<?php echo htmlspecialchars($v['name'], ENT_QUOTES); ?>"
            data-variant-color="<?php echo htmlspecialchars($v['color'], ENT_QUOTES); ?>"
            style="--swatch: <?php echo htmlspecialchars($v['color'], ENT_QUOTES); ?>"
          >
            <span class="visually-hidden"><?php echo htmlspecialchars($v['name']); ?></span>
          </button>
<?php endforeach; ?>
        </div>
      </div>

      <div class="product__sizes" data-size-index="<?php echo (int)$initialSizeIndex; ?>">
        <p class="product__selected">Size:
          <strong class="product__selected-size"><?php echo htmlspecialchars($sizes[$initialSizeIndex]); ?></strong>
        </p>
        <div class="sizes" role="radiogroup" aria-label="Choose size">
<?php foreach ($sizes as $i => $size): ?>
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
  // Variant switching and color deep-link (?color=)
  (function() {
    var media = document.querySelector('.product-media__image');
    var selectedNameEl = document.querySelector('.product__selected-name');
    var swatches = Array.prototype.slice.call(document.querySelectorAll('.swatch'));
    var selectedSizeEl = document.querySelector('.product__selected-size');
    var sizeButtons = Array.prototype.slice.call(document.querySelectorAll('.size'));
    if (!media || !selectedNameEl || !swatches.length) return;

    function selectVariantByIndex(idx) {
      swatches.forEach(function(s, i) {
        var isSel = (i === idx);
        s.classList.toggle('is-selected', isSel);
        s.setAttribute('aria-checked', isSel ? 'true' : 'false');
      });
      var sw = swatches[idx];
      if (!sw) return;
      var name = sw.getAttribute('data-variant-name');
      var color = sw.getAttribute('data-variant-color');
      selectedNameEl.textContent = name;
      media.style.setProperty('--media-color', color);
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
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>


