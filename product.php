<?php
require_once __DIR__ . '/config.php';

$conn = connectDb();
$id = (int)($_GET['id'] ?? 0);
$product = null;

if ($id > 0) {
    $stmt = $conn->prepare('SELECT id, name, category, price, original_price, stock, image, description, badge, rating, reviews FROM products WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
}

if (!$product) {
    http_response_code(404);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $scheme . '://' . $host;
$canonicalUrl = $baseUrl . '/product.php?id=' . $id;

if ($product) {
    $ogTitle = $product['name'];
    $ogDescription = $product['description'] ?: ('Shop ' . $product['name'] . ' at ALL IN ONE ABROAD — quality luggage and kitchen essentials.');
    $ogImage = preg_match('#^https?://#i', $product['image']) ? $product['image'] : $baseUrl . '/' . ltrim($product['image'], '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $product ? htmlspecialchars($product['name']) . ' — ALL IN ONE ABROAD' : 'Product Not Found — ALL IN ONE ABROAD' ?></title>
  <?php if ($product): ?>
  <meta property="og:type" content="website"/>
  <meta property="og:site_name" content="ALL IN ONE ABROAD"/>
  <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>"/>
  <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>"/>
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>"/>
  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>"/>
  <?php endif; ?>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
</head>
<body>

<div class="announce-bar"><div class="announce-track"><span>🎉 Grand Opening — Up to 50% Off</span><span>🚚 Free Delivery on Orders Above Rs. 799</span><span>💳 eSewa • Khalti • FonePay • COD</span><span>🎉 Grand Opening — Up to 50% Off</span><span>🚚 Free Delivery on Orders Above Rs. 799</span><span>💳 eSewa • Khalti • FonePay • COD</span></div></div>

<header class="site-header" id="siteHeader">
  <div class="header-inner">
    <a href="index.html" class="logo"><span class="logo-top">ALL IN ONE</span><span class="logo-bottom">ABROAD</span></a>
    <form class="search-form" onsubmit="return false;">
      <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" placeholder="Search luggage, cookers, tiffins…" id="searchInput"/>
    </form>
    <nav class="main-nav">
      <a href="shop.html?cat=luggage" data-cat="luggage">Luggage</a>
      <a href="shop.html?cat=kitchen" data-cat="kitchen">Kitchen</a>
      <a href="shop.html" data-cat="all">All Products</a>
    </nav>
    <div class="header-actions">
      <button class="icon-btn" title="Wishlist"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>
      <button class="icon-btn cart-btn" onclick="toggleCart()" title="Cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span class="cart-count" id="cartCount">0</span></button>
      <a href="login.html" class="btn-signin">Sign In</a>
      <button class="mobile-menu-btn" onclick="toggleMobileMenu()"><span></span><span></span><span></span></button>
    </div>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="shop.html?cat=luggage">Luggage</a><a href="shop.html?cat=kitchen">Kitchen</a>
    <a href="shop.html">All Products</a><a href="login.html">Sign In</a>
  </div>
</header>

<?php if (!$product): ?>

  <div style="max-width:600px;margin:80px auto;padding:0 24px;text-align:center;">
    <div style="font-size:48px;margin-bottom:16px;">📦</div>
    <h1 style="font-size:22px;font-weight:800;margin-bottom:10px;">Product not found</h1>
    <p style="color:var(--gray);font-size:14px;margin-bottom:24px;">This product may have been removed, or the link is incorrect.</p>
    <a href="shop.html" class="btn-orange">Back to Shop</a>
  </div>

<?php else: ?>

  <?php
    $price = (float)$product['price'];
    $orig = $product['original_price'] !== null ? (float)$product['original_price'] : null;
    $off = ($orig && $orig > $price) ? round((($orig - $price) / $orig) * 100) : 0;
    $stock = (int)$product['stock'];
    $outOfStock = $stock <= 0;
    $stars = (int)$product['rating'];
    $reviews = (int)$product['reviews'];
  ?>

  <div class="pdp-layout">
    <div class="pdp-gallery">
      <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="handleImgError(this)"/>
    </div>
    <div class="pdp-info">
      <div class="prod-cat"><?= htmlspecialchars(strtoupper($product['category'])) ?></div>
      <h1 class="pdp-title"><?= htmlspecialchars($product['name']) ?></h1>
      <div class="prod-rating" style="margin-bottom:14px;">
        <span class="stars"><?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?></span>
        <span class="review-ct">(<?= number_format($reviews) ?> reviews)</span>
      </div>
      <div class="pdp-price">Rs. <?= number_format($price, 2) ?></div>
      <?php if ($off > 0): ?>
        <div class="pdp-orig">Rs. <?= number_format($orig, 2) ?> <span style="color:var(--orange);text-decoration:none;font-weight:700;">(<?= (int)$off ?>% OFF)</span></div>
      <?php endif; ?>
      <div style="margin:6px 0 16px;">
        <?php if ($outOfStock): ?>
          <span class="badge" style="background:#6b7280;color:#fff;">OUT OF STOCK</span>
        <?php else: ?>
          <span class="badge badge-green">In Stock<?= $stock <= 5 ? ' — only ' . $stock . ' left' : '' ?></span>
        <?php endif; ?>
      </div>
      <?php if ($product['description']): ?>
        <p style="color:var(--gray);font-size:14px;line-height:1.7;margin-bottom:8px;"><?= htmlspecialchars($product['description']) ?></p>
      <?php endif; ?>
      <div class="pdp-btns">
        <?php if ($outOfStock): ?>
          <button class="btn-orange btn-full" disabled style="opacity:0.5;cursor:not-allowed;">OUT OF STOCK</button>
        <?php else: ?>
          <button class="btn-orange btn-full" id="pdpAddBtn">ADD TO CART</button>
        <?php endif; ?>
        <a href="shop.html?cat=<?= urlencode($product['category']) ?>" class="btn-outline btn-full">← Back to <?= htmlspecialchars(ucfirst($product['category'])) ?></a>
      </div>

      <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
        <div style="font-size:12px;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Share this product</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button class="btn-outline" onclick="shareFacebook()">📘 Facebook</button>
          <button class="btn-outline" id="shareNativeBtn" style="display:none;" onclick="shareNative()">📤 Share to Instagram &amp; more</button>
          <button class="btn-outline" onclick="copyProductLink()">🔗 Copy Link</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const btn = document.getElementById('pdpAddBtn');
      if (btn) {
        btn.addEventListener('click', () => {
          addToCart(<?= json_encode([
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'price' => $price,
            'img' => $product['image'],
            'cat' => $product['category'],
          ], JSON_UNESCAPED_SLASHES) ?>);
        });
      }

      const nativeBtn = document.getElementById('shareNativeBtn');
      if (nativeBtn && navigator.share) {
        nativeBtn.style.display = 'inline-flex';
      }
    });

    function shareFacebook() {
      const url = encodeURIComponent(window.location.href);
      window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, 'fbshare', 'width=600,height=500');
    }

    function shareNative() {
      if (!navigator.share) return;
      navigator.share({
        title: document.title,
        text: <?= json_encode($product['name']) ?>,
        url: window.location.href
      }).catch(() => {});
    }

    function copyProductLink() {
      const url = window.location.href;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
          showToast('🔗 Link copied! Paste it into an Instagram Story, DM, or bio.');
        }).catch(() => {
          window.prompt('Copy this link:', url);
        });
      } else {
        window.prompt('Copy this link:', url);
      }
    }
  </script>

<?php endif; ?>

<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <div class="footer-logo"><span class="footer-logo-top">ALL IN ONE</span><span class="footer-logo-bottom">ABROAD</span></div>
        <p class="footer-desc">Your trusted companion for student travel essentials.</p>
        <div class="footer-social">
          <a href="https://www.facebook.com/people/Bags-And-Luggage/61582261618135/" title="Facebook" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg></a>
          <a href="https://www.instagram.com/bagandluggage2025/" title="Instagram" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg></a>
          <a href="https://www.tiktok.com/@bags_luggage_ktm" title="TikTok" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82c-.99-.86-1.62-2.1-1.7-3.5h-3.05v13.9c0 1.65-1.35 3-3 3s-3-1.35-3-3 1.35-3 3-3c.28 0 .55.04.8.11V9.94a6.14 6.14 0 0 0-.8-.05c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6V9.01a9.16 9.16 0 0 0 4.75 1.33V7.29a5.86 5.86 0 0 1-2.99-1.47z"/></svg></a>
        </div>
      </div>
      <div class="footer-col"><h4>SHOP</h4><ul><li><a href="shop.html?cat=luggage">Luggage</a></li><li><a href="shop.html?cat=kitchen">Kitchen</a></li></ul></div>
      <div class="footer-col"><h4>SUPPORT</h4><ul><li><a href="contact.html">Contact</a></li><li><a href="#">Shipping</a></li><li><a href="#">Returns</a></li><li><a href="#">FAQ</a></li></ul></div>
      <div class="footer-col"><h4>LEGAL</h4><ul><li><a href="#">Privacy</a></li><li><a href="#">Terms</a></li></ul></div>
      <div class="footer-col"><h4>CONTACT</h4><ul><li style="color:rgba(255,255,255,0.6);">+977 9809497026</li><li style="color:rgba(255,255,255,0.6);">luvkushgupta37@gmail.com</li><li style="color:rgba(255,255,255,0.6);">Jorpati Chamunda Gate (Opp. Prabhu Bank), Kathmandu</li></ul></div>
    </div>
    <div class="footer-bottom"><p class="footer-copy">© 2026 All In One Abroad. All rights reserved.</p><div class="payment-icons"><span class="pay-badge">eSewa</span><span class="pay-badge">Khalti</span><span class="pay-badge">FonePay</span><span class="pay-badge">COD</span></div></div>
  </div>
</footer>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeAllDrawers()"></div>
<aside class="cart-drawer" id="cartDrawer">
  <div class="drawer-head"><h3>Shopping Cart <span id="cartLabel" class="cart-label">0</span></h3><button class="drawer-close" onclick="toggleCart()">×</button></div>
  <div class="drawer-body" id="cartBody"><div class="empty-state" id="cartEmpty"><div class="empty-icon">🛒</div><p>Your cart is empty</p></div><div id="cartItemsList"></div></div>
  <div class="drawer-footer" id="cartFooter" style="display:none;"><div class="cart-total-row"><span>Subtotal</span><span id="cartSubtotal">Rs. 0</span></div><div class="cart-note">🎉 Free delivery on this order!</div><a href="checkout.html" class="btn-orange btn-full">Proceed to Checkout →</a><button class="btn-outline btn-full" onclick="toggleCart()" style="margin-top:10px;">Continue Shopping</button></div>
</aside>
<div class="toast" id="toast"></div>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>
<script src="main.js?v=6"></script>
</body>
</html>
