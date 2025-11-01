<?php
require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <!-- Hero -->
  <section class="hero" aria-label="Hero">
    <div class="hero__media" aria-hidden="true"></div>
    <div class="hero__overlay">
      <h1 class="hero__title">Stirling's</h1>
      <p class="hero__subtitle">Elegant silhouettes. Everyday comfort.</p>
      <a href="products.php" class="btn btn--primary">Shop the Collection</a>
    </div>
  </section>

  <section class="reading-section">
  <div class="reading-spacer"></div> <!-- preserves scroll space -->
  <div class="reading-wrap">
    <reading-text reading-speed="1.5" text-start-opacity="0.25" class="reading-text-el">
      <div class="reading-text__inner">
        <div class="prose max-w-full">
          <!-- <span class="subheading subheading-badge subheading-badge--with-icon subheading-badge--icon-square">
            Stirling's Collection
          </span> -->
          <p class="h4">
            <split-lines preserve-letters class="is-split">
              The finest menswear for the modern gentleman, at the tip of your fingers.
            </split-lines>
          </p>
        </div>
      </div>
    </reading-text>
  </div>
</section>


  <!-- Teasers -->
  <section class="teasers container" aria-label="Highlights">
    <article class="teaser"><h2 class="teaser__title">New Arrivals</h2><p class="teaser__text">Fresh drops in classic neutrals.</p><a class="link" href="products.php?cat=new">Explore</a></article>
    <article class="teaser"><h2 class="teaser__title">Best Sellers</h2><p class="teaser__text">Our community’s most‑loved staples.</p><a class="link" href="products.php?sort=popular">See More</a></article>
    <article class="teaser"><h2 class="teaser__title">Under $49</h2><p class="teaser__text">Quality basics that don’t break the bank.</p><a class="link" href="products.php?price=max49">Shop Deals</a></article>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>