<?php
// Ensure session/config available for nav state
require_once __DIR__ . '/../config.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stirling's</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script defer src="assets/js/reading-text.js"></script>
  </head>
  <body>
    <header class="site-header" role="banner">
      <div class="container nav">
        <a class="logo" href="index.php"><img src="assets/images/logo.svg" alt="Stirling's Logo" style="width: 80px; height: 80px;"></a>
        <nav id="primary-nav" class="nav__menu" role="navigation">
          <ul class="nav__list">
            <li><a href="products.php">Shop</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="about.php#contact">Contact</a></li>
            <li><a href="cart.php" class="nav__cart" aria-label="View cart">Cart</a></li>
            <br>
            <?php if (isset($_SESSION['customer_id'])): ?>
            <li><a href="profile.php">Profile</a></li>
            <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
    </header>
