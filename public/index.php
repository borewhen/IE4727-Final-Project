<?php
require __DIR__ . '/partials/header.php';
?>
<main id="main">
  <!-- Hero -->
  <section class="hero hero--home" aria-label="Hero">
      <div class="hero__overlay">
          <img src="assets/images/logo.svg" alt="Stirling's Logo" class="hero__logo">
          <p class="hero__subtitle">Elegant silhouettes. Everyday comfort.</p>
          <a href="products.php" class="btn btn--primary">Shop the Collection</a>
        </div>
  </section>

  <section class="reading-section reading-section--home">
  <div class="reading-spacer"></div> <!-- this preserves scroll space! -->
  <div class="reading-wrap">
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
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>