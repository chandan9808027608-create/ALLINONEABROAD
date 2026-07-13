/* =============================================
   ALL IN ONE ABROAD — main.js
   ============================================= */

// ─── PRODUCT DATA ───────────────────────────
const PRODUCTS = [
  { id:1, name:'Hardside Spinner Trolley 28"', cat:'luggage', price:2799, orig:4999, off:44, img:'https://images.unsplash.com/photo-1565026057447-bc90a3dceb87?w=400&q=80', sub:'TSA Lock • 360° Wheels', badge:'CHECK-IN BAG', badgeClass:'badge-tag', extra:'badge-green', extraLabel:'Best Seller', reviews:3, stars:4 },
  { id:2, name:'Hardside Cabin Trolley 20"', cat:'luggage', price:1999, orig:3499, off:43, img:'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=400&q=80', sub:'TSA Lock, Flight Ready', badge:'CABIN BAG', badgeClass:'badge-tag', extra:'badge-blue', extraLabel:'Airline Approved', reviews:987, stars:5 },
  { id:3, name:'Pressure Cooker 3L', cat:'kitchen', price:999, orig:1799, off:44, img:'https://images.unsplash.com/photo-1585837146751-a4b0f2df9e44?w=400&q=80', sub:'ISI Certified, 5 Year Warranty', badge:'STAINLESS STEEL', badgeClass:'badge-tag', extra:'badge-red', extraLabel:'Must Have', reviews:3421, stars:5 },
  { id:4, name:'Anti-Theft Laptop Backpack 35L', cat:'luggage', price:1499, orig:2499, off:40, img:'https://images.unsplash.com/photo-1491553895911-0055eca6402d?w=400&q=80', sub:'Water Resistant, USB Charging Port', badge:'LAPTOP + TRAVEL', badgeClass:'badge-tag', extra:'badge-purple', extraLabel:'Top Pick', reviews:2108, stars:5 },
  { id:5, name:'Hardside Luggage Set — 3 Pcs', cat:'luggage', price:5499, orig:10999, off:50, img:'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400&q=80', sub:'20" + 24" + 28" Set', badge:'SET DEAL', badgeClass:'badge-tag', extra:'badge-off', extraLabel:'50% OFF', reviews:654, stars:5 },
  { id:6, name:'Steel Masala Dabba 7-Jar Set', cat:'kitchen', price:549, orig:899, off:39, img:'https://images.unsplash.com/photo-1606851091851-e8c8c0fca5ba?w=400&q=80', sub:'Airtight • Rustproof', badge:'SPICE BOX', badgeClass:'badge-tag', extra:'badge-green', extraLabel:'Best Seller', reviews:5672, stars:5 },
  { id:7, name:'Kitchen Utensils Starter Set', cat:'kitchen', price:799, orig:1499, off:47, img:'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=400&q=80', sub:'Spatula, Ladle, Tongs & more', badge:'12-PIECE SET', badgeClass:'badge-tag', extra:'badge-off', extraLabel:'47% OFF', reviews:1893, stars:4 },
  { id:8, name:'Stainless Steel Lunch Box 3-Tier', cat:'kitchen', price:449, orig:799, off:44, img:'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=400&q=80', sub:'Leakproof • Microwave Safe', badge:'TIFFIN BOX', badgeClass:'badge-tag', extra:'badge-teal', extraLabel:'Popular', reviews:4102, stars:5 },
  { id:9, name:'Warm Fleece Blanket XL', cat:'bedding', price:699, orig:1299, off:46, img:'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=400&q=80', sub:'Anti-Pilling, Machine Washable', badge:'BLANKET', badgeClass:'badge-tag', extra:'badge-blue', extraLabel:'Cozy Pick', reviews:892, stars:4 },
  { id:10, name:'Memory Foam Pillow', cat:'bedding', price:599, orig:999, off:40, img:'https://images.unsplash.com/photo-1631049421450-348ccd7f8949?w=400&q=80', sub:'Cervical Support, Hypoallergenic', badge:'PILLOW', badgeClass:'badge-tag', extra:'badge-green', extraLabel:'Best Seller', reviews:2341, stars:5 },
  { id:11, name:'Quick-Dry Towel Set 3-Pc', cat:'bedding', price:399, orig:699, off:43, img:'https://images.unsplash.com/photo-1583845112203-29329902332e?w=400&q=80', sub:'Microfiber, 600 GSM', badge:'TOWEL SET', badgeClass:'badge-tag', extra:'badge-off', extraLabel:'43% OFF', reviews:1123, stars:4 },
  { id:12, name:'Anti-Theft Crossbody Bag', cat:'luggage', price:899, orig:1499, off:40, img:'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400&q=80', sub:'RFID Blocking, Water Resistant', badge:'CROSSBODY', badgeClass:'badge-tag', extra:'badge-purple', extraLabel:'New Arrival', reviews:445, stars:4 },
];

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
      <img src="${item.img}" alt="${item.name}" onerror="this.src='https://via.placeholder.com/64'"/>
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
      <img src="${p.img}" alt="${p.name}" loading="lazy"/>
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
});
