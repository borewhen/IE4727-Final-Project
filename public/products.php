<?php
require __DIR__ . '/partials/header.php';

// Categories for filter navigation
$categories = ['All', 'Shoes', 'Shirts', 'Pants', 'Outerwear', 'Accessories'];
$currentCategory = isset($_GET['cat']) ? ucfirst(strtolower($_GET['cat'])) : 'All';
if (!in_array($currentCategory, $categories, true)) {
  $currentCategory = 'All';
}

// Placeholder catalog (to be replaced with DB-driven data later)
$catalog = [
  ['title' => 'Oxford Leather', 'price' => 129, 'category' => 'Shoes', 'slug' => 'oxford-leather'],
  ['title' => 'Minimal Sneaker', 'price' => 89, 'category' => 'Shoes', 'slug' => 'minimal-sneaker'],
  ['title' => 'Poplin Shirt', 'price' => 59, 'category' => 'Shirts', 'slug' => 'poplin-shirt'],
  ['title' => 'Linen Camp Shirt', 'price' => 69, 'category' => 'Shirts', 'slug' => 'linen-camp-shirt'],
  ['title' => 'Tapered Chino', 'price' => 79, 'category' => 'Pants', 'slug' => 'tapered-chino'],
  ['title' => 'Selvedge Denim', 'price' => 99, 'category' => 'Pants', 'slug' => 'selvedge-denim'],
  ['title' => 'Wool Overcoat', 'price' => 199, 'category' => 'Outerwear', 'slug' => 'wool-overcoat'],
  ['title' => 'Packable Anorak', 'price' => 119, 'category' => 'Outerwear', 'slug' => 'packable-anorak'],
  ['title' => 'Leather Belt', 'price' => 39, 'category' => 'Accessories', 'slug' => 'leather-belt'],
  ['title' => 'Cashmere Scarf', 'price' => 69, 'category' => 'Accessories', 'slug' => 'cashmere-scarf'],
];
?>
<main id="main">
  <section class="shop-hero container" aria-label="Shop">
    <h1 class="shop-title">Shop</h1>
    <p class="shop-subtitle">Refined essentials for everyday wear.</p>
  </section>

  <nav class="category-filter container" aria-label="Browse by category">
    <ul class="category-filter__list" role="tablist">
<?php foreach ($categories as $cat): ?>
      <li role="presentation">
        <button
          class="category-filter__button<?php echo $cat === $currentCategory ? ' is-active' : ''; ?>"
          role="tab"
          aria-selected="<?php echo $cat === $currentCategory ? 'true' : 'false'; ?>"
          data-cat="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>"
        ><?php echo htmlspecialchars($cat); ?></button>
      </li>
<?php endforeach; ?>
    </ul>
  </nav>

  <section class="products container" data-initial-cat="<?php echo htmlspecialchars($currentCategory, ENT_QUOTES); ?>">
    <div class="products-grid">
<?php foreach ($catalog as $item): ?>
      <article class="product-card" data-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>">
        <a href="product.php?slug=<?php echo urlencode($item['slug']); ?>" class="product-card__link" aria-label="View <?php echo htmlspecialchars($item['title']); ?>">
          <div class="product-card__image" aria-hidden="true"></div>
          <div class="product-card__body">
            <h3 class="product-card__title"><?php echo htmlspecialchars($item['title']); ?></h3>
            <div class="product-card__meta">
              <span class="product-card__category"><?php echo htmlspecialchars($item['category']); ?></span>
              <span class="product-card__price">$<?php echo number_format($item['price']); ?></span>
            </div>
          </div>
        </a>
      </article>
<?php endforeach; ?>
    </div>
  </section>
</main>

<script>
  // Basic client-side category filtering with deep-link support (?cat=...)
  (function() {
    var container = document.querySelector('.products');
    if (!container) return;

    var initial = container.getAttribute('data-initial-cat') || 'All';
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.category-filter__button'));
    var cards = Array.prototype.slice.call(document.querySelectorAll('.product-card'));

    function setActive(cat) {
      buttons.forEach(function(btn) {
        var isActive = btn.getAttribute('data-cat') === cat;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
    }

    function filterCards(cat) {
      cards.forEach(function(card) {
        var c = card.getAttribute('data-category');
        var show = (cat === 'All') || (c === cat);
        card.style.display = show ? '' : 'none';
      });
    }

    function updateUrl(cat) {
      var url = new URL(window.location.href);
      if (cat && cat !== 'All') {
        url.searchParams.set('cat', cat.toLowerCase());
      } else {
        url.searchParams.delete('cat');
      }
      window.history.replaceState({}, '', url);
    }

    function apply(cat) {
      setActive(cat);
      filterCards(cat);
      updateUrl(cat);
    }

    buttons.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var cat = btn.getAttribute('data-cat');
        apply(cat);
      });
    });

    apply(initial);
  })();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>


