<?php
require_once 'config.php';

// Inputs
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';

// Load categories from DB
$categories = [];
$conn = getDBConnection();
$cres = $conn->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY name");
if ($cres) {
  while ($row = $cres->fetch_assoc()) { $categories[] = $row; }
  $cres->close();
}

// Build products query
$params = [];
$types = '';
$where = ['p.is_active = 1'];
if ($cat !== '') {
  $where[] = 'c.slug = ?';
  $params[] = $cat;
  $types .= 's';
}
if ($q !== '') {
  $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like;
  $types .= 'ss';
}

$sql = 'SELECT p.id, p.name, p.slug, p.price, p.image_filename, c.name AS category_name, c.slug AS category_slug
        FROM products p INNER JOIN categories c ON p.category_id = c.id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC';

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$products = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$conn->close();

require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <section class="shop-hero container" aria-label="Shop">
    <h1 class="shop-title">Shop</h1>
    <p class="shop-subtitle">Refined essentials for everyday wear.</p>
    <form method="GET" action="products.php" class="form-row" style="margin-top:1rem; gap:.5rem; align-items:center;">
      <input type="search" class="search" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search products..." style="flex:1; max-width:420px;">
      <?php if ($cat !== ''): ?><input type="hidden" name="cat" value="<?php echo htmlspecialchars($cat); ?>"><?php endif; ?>
      <button type="submit" class="btn-secondary">Search</button>
    </form>
  </section>

  <nav class="category-filter container" aria-label="Browse by category">
    <ul class="category-filter__list" role="tablist">
      <li role="presentation">
        <a class="category-filter__button<?php echo $cat === '' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $cat === '' ? 'true' : 'false'; ?>" href="products.php<?php echo $q !== '' ? ('?q=' . urlencode($q)) : ''; ?>">All</a>
      </li>
<?php foreach ($categories as $c): ?>
      <li role="presentation">
        <a
          class="category-filter__button<?php echo $cat === $c['slug'] ? ' is-active' : ''; ?>"
          role="tab"
          aria-selected="<?php echo $cat === $c['slug'] ? 'true' : 'false'; ?>"
          href="products.php?cat=<?php echo urlencode($c['slug']); ?><?php echo $q !== '' ? ('&q=' . urlencode($q)) : ''; ?>"
        ><?php echo htmlspecialchars($c['name']); ?></a>
      </li>
<?php endforeach; ?>
    </ul>
  </nav>

  <section class="products container">
    <?php if (empty($products)): ?>
      <div class="notice" style="margin-top:.5rem;">No products found.</div>
    <?php else: ?>
    <div class="products-grid">
<?php foreach ($products as $p): ?>
      <article class="product-card">
        <a href="product.php?slug=<?php echo urlencode($p['slug']); ?>" class="product-card__link" aria-label="View <?php echo htmlspecialchars($p['name']); ?>">
          <div class="product-card__image" aria-hidden="true" style="<?php echo $p['image_filename'] ? 'background-image:url(' . htmlspecialchars($p['image_filename']) . '); background-size:cover; background-position:center;' : ''; ?>"></div>
          <div class="product-card__body">
            <h3 class="product-card__title"><?php echo htmlspecialchars($p['name']); ?></h3>
            <div class="product-card__meta">
              <span class="product-card__category"><?php echo htmlspecialchars($p['category_name']); ?></span>
              <span class="product-card__price">$<?php echo number_format((float)$p['price']); ?></span>
            </div>
          </div>
        </a>
      </article>
<?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>


