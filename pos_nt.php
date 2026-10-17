<?php
/**
 * pos.php
 * POS Kasir — UC-03 Memproses Transaksi Penjualan
 * Core Module Modul 7
 * 
 * Alur: Katalog Menu → Pilih Item → Keranjang → Metode Bayar → Konfirmasi → Struk
 */
$pageTitle = 'POS Kasir';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Kasir']);

$pdo = getConnection();

// Ambil kategori
$kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

// Ambil menu beserta ketersediaan stok (cek semua bahan di resep)
$menuItems = $pdo->query("
    SELECT m.*, k.nama_kategori,
    (
        SELECT CASE
            WHEN COUNT(r.id_resep) = 0 THEN 1
            WHEN MIN(FLOOR(b.stok_tersedia / r.takaran)) <= 0 THEN 0
            ELSE MIN(FLOOR(b.stok_tersedia / r.takaran))
        END
        FROM resep r
        JOIN bahan_baku b ON r.id_bahan_baku = b.id_bahan_baku
        WHERE r.id_menu = m.id_menu
    ) AS max_porsi
    FROM menu m
    JOIN kategori k ON m.id_kategori = k.id_kategori
    ORDER BY k.nama_kategori, m.nama_menu
")->fetchAll();

// Map ikon kategori
$categoryIcons = [
    'Espresso Based' => '☕',
    'Non-Coffee'     => '🍵',
    'Manual Brew'    => '🫖',
    'Pastry'         => '🥐',
];
?>

<div class="pos-layout">
  <!-- Katalog Menu (kiri) -->
  <div class="pos-catalog">
    <!-- Filter Kategori (Tab) — diperbesar sesuai perbaikan Laprak 6 -->
    <div class="category-tabs">
      <button class="category-tab active" onclick="filterCategory('all', this)">Semua</button>
      <?php foreach ($kategoriList as $kat): ?>
        <button class="category-tab" onclick="filterCategory(<?= $kat['id_kategori'] ?>, this)">
          <?= $categoryIcons[$kat['nama_kategori']] ?? '📋' ?>
          <?= htmlspecialchars($kat['nama_kategori']) ?>
        </button>
      <?php endforeach; ?>
    </div>
    
    <!-- Grid Menu -->
    <div class="menu-grid" id="menuGrid">
      <?php foreach ($menuItems as $item): ?>
        <div class="menu-card <?= $item['max_porsi'] <= 0 ? 'unavailable' : '' ?>"
             data-category="<?= $item['id_kategori'] ?>"
             data-id="<?= $item['id_menu'] ?>"
             data-name="<?= htmlspecialchars($item['nama_menu']) ?>"
             data-price="<?= $item['harga'] ?>"
             data-max="<?= $item['max_porsi'] ?>"
             <?= $item['max_porsi'] > 0 ? 'onclick="addToCart(this)"' : '' ?>>
          
          <div class="menu-icon"><?= $categoryIcons[$item['nama_kategori']] ?? '📋' ?></div>
          <div class="menu-name"><?= htmlspecialchars($item['nama_menu']) ?></div>
          <div style="font-size:0.7rem; color:var(--color-text-secondary); margin-bottom:0.25rem;">
            <?= htmlspecialchars($item['nama_kategori']) ?>
          </div>
          <div class="menu-price">Rp <?= number_format($item['harga'], 0, ',', '.') ?></div>
          
          <?php if ($item['max_porsi'] <= 0): ?>
            <span class="badge badge-danger badge-habis">Habis</span>
          <?php elseif ($item['max_porsi'] <= 3): ?>
            <span class="badge badge-warning badge-low">Sisa <?= $item['max_porsi'] ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <!-- Keranjang Belanja (kanan) -->
  <div class="pos-cart">
    <div class="cart-header">
      <span><i data-lucide="shopping-cart" style="width:18px;height:18px;"></i> Keranjang Pesanan</span>
      <button class="btn btn-sm btn-outline" onclick="clearCart()" id="btnClearCart" style="display:none;">
        <i data-lucide="trash" style="width:14px;height:14px;"></i> Kosongkan
      </button>
    </div>
    
    <!-- Item di keranjang -->
    <div class="cart-items" id="cartItems">
      <div class="cart-empty" id="cartEmpty">
        <i data-lucide="shopping-bag"></i>
        <p>Keranjang masih kosong</p>
        <small class="text-muted">Pilih menu untuk memulai transaksi</small>
      </div>
    </div>
    
    <!-- Footer: Total + Payment -->
    <div class="cart-footer" id="cartFooter" style="display:none;">
      <div class="cart-total">
        <span class="total-label">Total</span>
        <span class="total-value" id="totalPrice">Rp 0</span>
      </div>
      
      <!-- Metode Pembayaran -->
      <div style="font-size:0.8rem; font-weight:600; margin-bottom:0.5rem;">Metode Pembayaran</div>
      <div class="payment-methods">
        <div class="payment-method">
          <input type="radio" name="metode" id="metodeTunai" value="Tunai" checked>
          <label for="metodeTunai"><i data-lucide="banknote" style="width:16px;height:16px;"></i> Tunai</label>
        </div>
        <div class="payment-method">
          <input type="radio" name="metode" id="metodeQRIS" value="QRIS">
          <label for="metodeQRIS"><i data-lucide="qr-code" style="width:16px;height:16px;"></i> QRIS</label>
        </div>
      </div>
      
      <!-- Input Nominal (untuk Tunai) -->
      <div id="tunaiSection">
        <div class="form-group" style="margin-bottom:0.5rem;">
          <label for="nominalBayar" style="font-size:0.8rem;">Nominal Bayar (Rp)</label>
          <input type="number" id="nominalBayar" class="form-control" placeholder="0" min="0"
                 oninput="hitungKembalian()">
        </div>
        
        <!-- Quick Cash Buttons (sesuai perbaikan Laprak 6) -->
        <div class="quick-cash">
          <button type="button" onclick="setNominal('pas')">💰 Uang Pas</button>
          <button type="button" onclick="setNominal(20000)">Rp 20.000</button>
          <button type="button" onclick="setNominal(50000)">Rp 50.000</button>
          <button type="button" onclick="setNominal(100000)">Rp 100.000</button>
        </div>
        
        <div class="cart-change">
          <span>Kembalian</span>
          <span class="change-value" id="kembalianValue">Rp 0</span>
        </div>
      </div>
      
      <!-- Tombol Konfirmasi (label disesuaikan per perbaikan Laprak 6) -->
      <button class="btn btn-primary btn-lg btn-block" onclick="prosesTransaksi()" id="btnBayar">
        <i data-lucide="check-circle" style="width:20px;height:20px;"></i>
        Konfirmasi & Bayar
      </button>
    </div>
  </div>
</div>

<!-- Form tersembunyi untuk submit transaksi -->
<form id="formTransaksi" method="POST" action="pos_process.php" style="display:none;">
  <input type="hidden" name="cart_data" id="inputCartData">
  <input type="hidden" name="metode_pembayaran" id="inputMetode">
  <input type="hidden" name="nominal_bayar" id="inputNominal">
  <input type="hidden" name="total_harga" id="inputTotal">
</form>

<script>
/**
 * POS Kasir — JavaScript
 * Mengelola keranjang belanja, kalkulasi, dan validasi di client-side
 */

// State keranjang
let cart = [];

// Format Rupiah
function formatRp(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

// Filter kategori menu
function filterCategory(catId, btn) {
    document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.menu-card').forEach(card => {
        if (catId === 'all' || card.dataset.category == catId) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Tambah item ke keranjang
function addToCart(el) {
    const id    = parseInt(el.dataset.id);
    const name  = el.dataset.name;
    const price = parseFloat(el.dataset.price);
    const max   = parseInt(el.dataset.max);
    
    // Cek apakah sudah ada di keranjang
    const existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty >= max) {
            alert('Stok bahan baku tidak mencukupi untuk menambah porsi ' + name + '.\nMaksimal: ' + max + ' porsi.');
            return;
        }
        existing.qty++;
    } else {
        cart.push({ id, name, price, qty: 1, max });
    }
    
    renderCart();
}

// Render keranjang
function renderCart() {
    const container = document.getElementById('cartItems');
    const empty     = document.getElementById('cartEmpty');
    const footer    = document.getElementById('cartFooter');
    const clearBtn  = document.getElementById('btnClearCart');
    
    if (cart.length === 0) {
        container.innerHTML = '';
        container.appendChild(empty);
        empty.style.display = '';
        footer.style.display = 'none';
        clearBtn.style.display = 'none';
        return;
    }
    
    empty.style.display = 'none';
    footer.style.display = '';
    clearBtn.style.display = '';
    
    let html = '';
    let total = 0;
    
    cart.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        
        html += `
        <div class="cart-item">
          <div class="item-info">
            <div class="item-name">${item.name}</div>
            <div class="item-price">${formatRp(item.price)} × ${item.qty}</div>
          </div>
          <div class="item-qty">
            <button onclick="changeQty(${index}, -1)" title="Kurangi">−</button>
            <span>${item.qty}</span>
            <button onclick="changeQty(${index}, 1)" title="Tambah">+</button>
          </div>
          <div class="item-subtotal">${formatRp(subtotal)}</div>
          <button class="item-remove" onclick="removeItem(${index})" title="Hapus">
            <i data-lucide="x" style="width:16px;height:16px;"></i>
          </button>
        </div>`;
    });
    
    container.innerHTML = html;
    document.getElementById('totalPrice').textContent = formatRp(total);
    
    // Re-render icons
    if (typeof lucide !== 'undefined') lucide.createIcons();
    
    hitungKembalian();
}

// Ubah jumlah porsi
function changeQty(index, delta) {
    const item = cart[index];
    const newQty = item.qty + delta;
    
    if (newQty <= 0) {
        removeItem(index);
        return;
    }
    
    if (newQty > item.max) {
        alert('Stok bahan baku tidak mencukupi.\nMaksimal ' + item.name + ': ' + item.max + ' porsi.');
        return;
    }
    
    item.qty = newQty;
    renderCart();
}

// Hapus item dari keranjang
function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

// Kosongkan keranjang
function clearCart() {
    if (confirm('Yakin ingin mengosongkan keranjang?')) {
        cart = [];
        renderCart();
    }
}

// Hitung total keranjang
function getTotal() {
    return cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
}

// Toggle metode pembayaran
document.querySelectorAll('input[name="metode"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const tunaiSection = document.getElementById('tunaiSection');
        if (this.value === 'Tunai') {
            tunaiSection.style.display = '';
        } else {
            tunaiSection.style.display = 'none';
        }
        hitungKembalian();
    });
});

// Set nominal bayar (Quick Cash)
function setNominal(val) {
    const input = document.getElementById('nominalBayar');
    if (val === 'pas') {
        input.value = getTotal();
    } else {
        input.value = val;
    }
    hitungKembalian();
}

// Hitung kembalian
function hitungKembalian() {
    const total = getTotal();
    const nominal = parseFloat(document.getElementById('nominalBayar').value) || 0;
    const kembalian = nominal - total;
    const el = document.getElementById('kembalianValue');
    
    el.textContent = formatRp(Math.max(0, kembalian));
    el.style.color = kembalian >= 0 ? 'var(--color-success)' : 'var(--color-danger)';
}

// Proses transaksi (validasi + submit)
function prosesTransaksi() {
    // Validasi keranjang tidak kosong
    if (cart.length === 0) {
        alert('Keranjang masih kosong. Silakan pilih menu terlebih dahulu.');
        return;
    }
    
    const total  = getTotal();
    const metode = document.querySelector('input[name="metode"]:checked').value;
    let nominal  = total; // default untuk QRIS
    
    // Validasi pembayaran tunai
    if (metode === 'Tunai') {
        nominal = parseFloat(document.getElementById('nominalBayar').value) || 0;
        
        if (nominal <= 0) {
            alert('Nominal bayar harus lebih besar dari 0.');
            return;
        }
        
        if (nominal < total) {
            alert('Nominal bayar (Rp ' + nominal.toLocaleString('id-ID') + ') kurang dari total (Rp ' + total.toLocaleString('id-ID') + ').\nPembayaran tidak mencukupi.');
            return;
        }
    }
    
    // Validasi porsi > 0
    for (const item of cart) {
        if (item.qty <= 0) {
            alert('Jumlah porsi untuk "' + item.name + '" harus lebih besar dari 0.');
            return;
        }
    }
    
    // Konfirmasi
    if (!confirm('Konfirmasi transaksi?\n\nTotal: ' + formatRp(total) + '\nMetode: ' + metode + '\nBayar: ' + formatRp(nominal))) {
        return;
    }
    
    // Submit form
    document.getElementById('inputCartData').value = JSON.stringify(cart);
    document.getElementById('inputMetode').value   = metode;
    document.getElementById('inputNominal').value   = nominal;
    document.getElementById('inputTotal').value     = total;
    document.getElementById('formTransaksi').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
