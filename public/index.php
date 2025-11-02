<?php
require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <!-- Hero -->
  <section class="hero" aria-label="Hero" style="background: linear-gradient(rgba(0,0,0,.5), rgba(0,0,0,.5)), url('assets/images/hero.jpg') center/cover no-repeat;">
      <div class="hero__overlay">
          <img src="assets/images/logo.svg" alt="Stirling's Logo" style="width: 30vw; height: auto; filter: invert(1);">
          <p class="hero__subtitle">Elegant silhouettes. Everyday comfort.</p>
          <a href="products.php" class="btn btn--primary">Shop the Collection</a>
        </div>
  </section>

  <section class="reading-section" style="background: linear-gradient(rgba(80,63,53,0.8), rgba(80,63,53,0.8)), url('assets/images/hero2.jpg') center/cover no-repeat;">
  <div class="reading-spacer"></div> <!-- this preserves scroll space! -->
  <div class="reading-wrap" style="color:#ffffff;">
    <reading-text reading-speed="1.5" text-start-opacity="0.25" class="reading-text-el">
      <div class="reading-text__inner">
        <div class="prose max-w-full">
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