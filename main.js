/* =============================================
   ALL IN ONE ABROAD — main.js
   ============================================= */

// ─── IMAGE FALLBACK ──────────────────────────
const FALLBACK_IMG = 'data:image/svg+xml,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="100%" height="100%" fill="#f3f4f6"/><text x="50%" y="50%" font-family="Arial, sans-serif" font-size="16" fill="#9ca3af" text-anchor="middle" dominant-baseline="middle">Image unavailable</text></svg>'
);
function handleImgError(img) {
  img.onerror = null;
  img.src = FALLBACK_IMG;
}

// ─── PRODUCT DATA (loaded from the database) ─
let PRODUCTS = [];
let productsLoadError = false;

async function fetchProducts() {
  try {
    const res = await fetch('api.php?action=products');
    const data = await res.json();
    if (data.success) {
      PRODUCTS = data.products;
    } else {
      productsLoadError = true;
    }
  } catch (err) {
    productsLoadError = true;
  }
}

// ─── CART STATE ──────────────────────────────
let cart = JSON.parse(localStorage.getItem('aiaCart') || '[]');

function saveCart() { localStorage.setItem('aiaCart', JSON.stringify(cart)); }

function addToCart(product) {
  const existing = cart.find(i => i.id === product.id);
  if (existing) { existing.qty++; }
  else { cart.push({ ...product, qty: 1 }); }
  saveCart();
  updateCartUI();
  showToast(`✓ Added to cart: ${product.name}`);
}

function removeFromCart(id) {
  cart = cart.filter(i => i.id !== id);
  saveCart();
  updateCartUI();
  renderCartItems();
}

function changeQty(id, delta) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) removeFromCart(id);
  else { saveCart(); updateCartUI(); renderCartItems(); }
}

function updateCartUI() {
  const total = cart.reduce((s, i) => s + i.qty, 0);
  const sub = cart.reduce((s, i) => s + i.price * i.qty, 0);
  document.querySelectorAll('#cartCount').forEach(el => el.textContent = total);
  const lbl = document.getElementById('cartLabel');
  if (lbl) lbl.textContent = total;
  const subEl = document.getElementById('cartSubtotal');
  if (subEl) subEl.textContent = 'Rs. ' + sub.toLocaleString('en-IN');
}

function renderCartItems() {
  const list = document.getElementById('cartItemsList');
  const empty = document.getElementById('cartEmpty');
  const footer = document.getElementById('cartFooter');
  if (!list) return;
  if (cart.length === 0) {
    list.innerHTML = '';
    if (empty) empty.style.display = 'block';
    if (footer) footer.style.display = 'none';
    return;
  }
  if (empty) empty.style.display = 'none';
  if (footer) footer.style.display = 'block';
  list.innerHTML = cart.map(item => `
    <div class="cart-item">
      <img src="${item.img}" alt="${item.name}" onerror="handleImgError(this)"/>
      <div class="ci-info">
        <div class="ci-name">${item.name}</div>
        <div class="ci-price">Rs. ${item.price.toLocaleString('en-IN')}</div>
        <div class="ci-qty">
          <button onclick="changeQty(${item.id}, -1)">−</button>
          <span>${item.qty}</span>
          <button onclick="changeQty(${item.id}, 1)">+</button>
        </div>
      </div>
      <button class="ci-remove" onclick="removeFromCart(${item.id})" title="Remove">×</button>
    </div>
  `).join('');
}

// ─── CART DRAWER ────────────────────────────
function toggleCart() {
  const drawer = document.getElementById('cartDrawer');
  const overlay = document.getElementById('drawerOverlay');
  if (!drawer) return;
  drawer.classList.toggle('open');
  overlay.classList.toggle('open');
  if (drawer.classList.contains('open')) { renderCartItems(); document.body.style.overflow = 'hidden'; }
  else { document.body.style.overflow = ''; }
}

function closeAllDrawers() {
  document.getElementById('cartDrawer')?.classList.remove('open');
  document.getElementById('drawerOverlay')?.classList.remove('open');
  document.body.style.overflow = '';
}

// ─── MOBILE MENU ────────────────────────────
function toggleMobileMenu() {
  document.getElementById('mobileNav')?.classList.toggle('open');
}

// ─── TOAST ──────────────────────────────────
function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2800);
}

// ─── WISHLIST (local) ────────────────────────
let wishlist = JSON.parse(localStorage.getItem('aiaWish') || '[]');
function toggleWish(id) {
  const idx = wishlist.indexOf(id);
  if (idx > -1) wishlist.splice(idx, 1); else wishlist.push(id);
  localStorage.setItem('aiaWish', JSON.stringify(wishlist));
  document.querySelectorAll(`[data-wish="${id}"]`).forEach(btn => {
    btn.textContent = wishlist.includes(id) ? '♥' : '♡';
    btn.style.color = wishlist.includes(id) ? '#ef4444' : '';
  });
}

// ─── RENDER PRODUCT CARD ─────────────────────
function renderProductCard(p) {
  const inWish = wishlist.includes(p.id);
  return `
  <div class="prod-card">
    <div class="prod-img-wrap">
      <img src="${p.img}" alt="${p.name}" loading="lazy" onerror="handleImgError(this)"/>
      <div class="prod-badges">
        <span class="badge badge-off">${p.off}% OFF</span>
        <span class="badge ${p.badgeClass}">${p.badge}</span>
        ${p.extra ? `<span class="badge ${p.extra}">${p.extraLabel}</span>` : ''}
      </div>
      <button class="wish-btn ${inWish ? 'active' : ''}" data-wish="${p.id}" onclick="toggleWish(${p.id})" title="Wishlist">${inWish ? '♥' : '♡'}</button>
    </div>
    <div class="prod-body">
      <div class="prod-cat">${p.cat.toUpperCase()}</div>
      <div class="prod-name">${p.name}</div>
      <div class="prod-sub">${p.sub}</div>
      <div class="prod-rating">
        <span class="stars">${'★'.repeat(p.stars)}${'☆'.repeat(5 - p.stars)}</span>
        <span class="review-ct">(${p.reviews.toLocaleString('en-IN')})</span>
      </div>
      <div class="prod-price-row">
        <span class="prod-price">Rs. ${p.price.toLocaleString('en-IN')}</span>
        <span class="prod-orig">Rs. ${p.orig.toLocaleString('en-IN')}</span>
      </div>
      <button class="add-cart-btn" onclick="addToCart({id:${p.id}, name:'${p.name.replace(/'/g,"\\'")}', price:${p.price}, img:'${p.img}', cat:'${p.cat}'})">ADD TO CART</button>
    </div>
  </div>`;
}

// ─── HOME PAGE: BEST SELLERS ─────────────────
function renderBestSellers() {
  const grid = document.getElementById('bestSellerGrid');
  if (!grid) return;
  const top4 = PRODUCTS.filter(p => [1,2,3,4].includes(p.id));
  grid.innerHTML = top4.map(renderProductCard).join('');
}

// ─── SHOP PAGE ───────────────────────────────
function renderShop() {
  const grid = document.getElementById('shopGrid');
  if (!grid) return;
  const params = new URLSearchParams(window.location.search);
  const cat = params.get('cat') || 'all';
  const sort = document.getElementById('sortSelect')?.value || 'featured';
  let prods = cat === 'all' ? [...PRODUCTS] : PRODUCTS.filter(p => p.cat === cat);
  if (sort === 'low') prods.sort((a, b) => a.price - b.price);
  else if (sort === 'high') prods.sort((a, b) => b.price - a.price);
  else if (sort === 'rated') prods.sort((a, b) => b.reviews - a.reviews);
  const titleEl = document.getElementById('shopTitle');
  const descEl = document.getElementById('shopDesc');
  const countEl = document.getElementById('prodCount');
  const catMap = {
    luggage: { t: 'Luggage & Bags', d: 'Trolleys, backpacks & sets built for international travel.' },
    kitchen: { t: 'Kitchen Essentials', d: 'Pressure cookers, induction cooktops, tiffins & masala dabbas.' },
    bedding: { t: 'Bedding & Comfort', d: 'Warm blankets, memory foam pillows and quick-dry towel sets.' },
    all:     { t: 'All Products', d: 'Everything you need — luggage, kitchen, and bedding.' },
  };
  if (titleEl) titleEl.textContent = catMap[cat]?.t || 'All Products';
  if (descEl) descEl.textContent = catMap[cat]?.d || '';
  if (countEl) countEl.textContent = `${prods.length} products`;
  grid.innerHTML = prods.map(renderProductCard).join('');

  // Active filter highlight
  document.querySelectorAll('[data-cat]').forEach(btn => {
    btn.style.fontWeight = btn.dataset.cat === cat ? '800' : '500';
    btn.style.color = btn.dataset.cat === cat ? 'var(--orange)' : '';
  });
}

// ─── SCROLL BEHAVIOURS ───────────────────────
window.addEventListener('scroll', () => {
  const bt = document.getElementById('backTop');
  if (bt) { bt.classList.toggle('show', window.scrollY > 400); }
});

// ─── HEADER STICKY SHADOW ────────────────────
window.addEventListener('scroll', () => {
  const h = document.getElementById('siteHeader');
  if (h) h.style.boxShadow = window.scrollY > 10 ? '0 2px 16px rgba(0,0,0,0.1)' : '';
});

// ─── SEARCH (basic) ──────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const si = document.getElementById('searchInput');
  if (si) {
    si.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        const q = si.value.trim();
        if (q) window.location.href = `shop.html?q=${encodeURIComponent(q)}`;
      }
    });
  }
  // Init
  updateCartUI();
  renderBestSellers();
  renderShop();
  checkAuthState();
});

// ─── AUTH STATE (header sign-in / account) ───
async function checkAuthState() {
  const signInLinks = document.querySelectorAll('.btn-signin');
  if (!signInLinks.length) return;
  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'me' })
    });
    const data = await res.json();
    if (data.success && data.user) {
      signInLinks.forEach(el => {
        el.textContent = `Hi, ${data.user.name.split(' ')[0]}`;
        el.href = '#';
        el.title = 'Click to log out';
        el.onclick = (e) => { e.preventDefault(); handleLogout(); };
      });
    }
  } catch (err) {
    // Backend unreachable — leave header as "Sign In"
  }
}

async function handleLogout() {
  try {
    await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'logout' })
    });
  } catch (err) {}
  window.location.href = 'index.html';
}
