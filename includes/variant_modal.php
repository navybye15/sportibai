<!-- ═══════════════════════════════════════════════════════════════════════════
     SPORTIBAI VARIANT / SIZE SELECTOR MODAL (Quick-Add)
═══════════════════════════════════════════════════════════════════════════ -->
<div id="variant-modal" class="variant-modal-backdrop" onclick="if(event.target === this) closeVariantModal();">
    <div class="variant-modal-card">
        <!-- Close button -->
        <button type="button" class="variant-modal-close" onclick="closeVariantModal()" aria-label="Close modal">✕</button>

        <!-- Product Summary Header -->
        <div class="variant-modal-header">
            <div class="variant-modal-thumb-wrap">
                <img id="vm-image" src="" alt="Product" style="display:none;">
                <div id="vm-image-fallback" class="variant-modal-fallback" style="display:none;">GEAR</div>
            </div>
            <div class="variant-modal-meta">
                <span id="vm-brand" class="brand-tag"></span>
                <h3 id="vm-name" class="variant-modal-title"></h3>
                <div id="vm-price" class="variant-modal-price"></div>
            </div>
        </div>

        <!-- Add to Cart Form -->
        <form method="post" action="actions/cart_update.php" class="variant-modal-body">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="item_id" id="vm-item-id" value="">
            <input type="hidden" name="size" id="vm-selected-size" value="">
            <input type="hidden" name="return_url" id="vm-return-url" value="">

            <div style="margin-bottom:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label class="form-label" style="font-size:11px; margin:0; text-transform:uppercase; letter-spacing:0.8px;">Choose Size / Variation</label>
                    <span id="vm-stock-hint" class="vm-stock-hint">Select a size</span>
                </div>
                <div id="vm-sizes-container" class="variant-pills-grid">
                    <!-- Dynamic Size Pills injected by JS -->
                </div>
            </div>

            <!-- Quantity Selector -->
            <div class="variant-qty-row">
                <span style="font-size:13px; font-weight:600; color:var(--color-fg);">Quantity</span>
                <div class="variant-qty-ctrl">
                    <button type="button" onclick="vmChangeQty(-1)" class="vm-qty-btn" aria-label="Decrease">−</button>
                    <input type="number" id="vm-qty" name="qty" value="1" min="1" max="99" readonly class="vm-qty-input">
                    <button type="button" onclick="vmChangeQty(1)" class="vm-qty-btn" aria-label="Increase">+</button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="vm-submit-btn" class="btn" style="width:100%; padding:0.9rem; font-size:13px; font-weight:700; border-radius:10px; display:flex; align-items:center; justify-content:center; gap:8px;">
                <span>Add to Cart</span>
                <span>→</span>
            </button>

            <div style="text-align:center; margin-top:12px;">
                <a id="vm-view-details" href="#" class="muted" style="font-size:11px; text-decoration:underline;">View full product specs &amp; buyer reviews →</a>
            </div>
        </form>
    </div>
</div>

<style>
.variant-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.78);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    opacity: 0;
    transition: opacity 0.22s ease;
}
.variant-modal-backdrop.is-open {
    display: flex;
    opacity: 1;
}
.variant-modal-card {
    background: #14171f;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 18px;
    max-width: 440px;
    width: 100%;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.85), 0 0 0 1px rgba(255, 255, 255, 0.05);
    overflow: hidden;
    transform: scale(0.92);
    transition: transform 0.24s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}
.variant-modal-backdrop.is-open .variant-modal-card {
    transform: scale(1);
}
.variant-modal-close {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    z-index: 10;
    transition: background 0.15s, transform 0.15s;
}
.variant-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.05);
}
.variant-modal-header {
    padding: 1.5rem 1.5rem 1.1rem;
    display: flex;
    gap: 14px;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
}
.variant-modal-thumb-wrap {
    width: 74px;
    height: 74px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: #0a0b0e;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.variant-modal-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.variant-modal-fallback {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-muted);
    letter-spacing: 1px;
}
.variant-modal-meta {
    flex: 1;
    min-width: 0;
}
.variant-modal-title {
    font-size: 16px;
    margin: 4px 0 0;
    line-height: 1.3;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.variant-modal-price {
    font-size: 18px;
    margin-top: 6px;
    color: #30d158;
    font-weight: 800;
}
.variant-modal-body {
    padding: 1.25rem 1.5rem 1.5rem;
}
.variant-pills-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.vm-stock-hint {
    font-size: 11px;
    font-weight: 600;
    color: var(--color-muted);
}
.vm-stock-hint.in-stock {
    color: #30d158;
}
.vm-stock-hint.out-of-stock {
    color: #ff6b6b;
}
.variant-pill {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.04);
    color: #e5e7eb;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.16s ease;
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.variant-pill:hover:not(.is-disabled) {
    border-color: rgba(10, 132, 255, 0.6);
    background: rgba(10, 132, 255, 0.12);
    color: #fff;
    transform: translateY(-1px);
}
.variant-pill.is-selected {
    border-color: #0a84ff !important;
    background: rgba(10, 132, 255, 0.22) !important;
    color: #fff !important;
    box-shadow: 0 0 12px rgba(10, 132, 255, 0.45);
}
.variant-pill.is-disabled {
    opacity: 0.35;
    text-decoration: line-through;
    cursor: not-allowed;
    background: rgba(255, 255, 255, 0.02);
    border-color: rgba(255, 255, 255, 0.06);
}
.variant-qty-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
}
.variant-qty-ctrl {
    display: flex;
    align-items: center;
    gap: 8px;
}
.vm-qty-btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #fff;
    cursor: pointer;
    transition: background 0.15s;
}
.vm-qty-btn:hover {
    background: rgba(255, 255, 255, 0.18);
}
.vm-qty-input {
    width: 44px;
    text-align: center;
    background: transparent;
    border: none;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    outline: none;
}
</style>

<script>
let vmCurrentMaxStock = 1;

function openVariantModal(item) {
    if (!item) return;

    document.getElementById('vm-item-id').value = item.id;
    document.getElementById('vm-return-url').value = item.return_url || '';
    document.getElementById('vm-name').innerText = item.name;
    document.getElementById('vm-brand').innerText = item.brand || item.category || 'SPORTIBAI';
    document.getElementById('vm-price').innerText = item.price;
    document.getElementById('vm-view-details').href = 'item/detail.php?id=' + item.id;

    // Image handling
    const imgEl = document.getElementById('vm-image');
    const fallbackEl = document.getElementById('vm-image-fallback');
    if (item.image && item.image.trim() !== '') {
        imgEl.src = item.image;
        imgEl.style.display = 'block';
        fallbackEl.style.display = 'none';
    } else {
        imgEl.style.display = 'none';
        fallbackEl.innerText = (item.category || 'GEAR').toUpperCase();
        fallbackEl.style.display = 'block';
    }

    // Reset Qty
    document.getElementById('vm-qty').value = 1;

    // Render sizes
    const container = document.getElementById('vm-sizes-container');
    container.innerHTML = '';
    const sizes = item.sizes || [];
    let firstAvailable = null;

    if (sizes.length === 0) {
        // Fallback for non-sized items
        document.getElementById('vm-selected-size').value = '';
        document.getElementById('vm-stock-hint').innerText = 'Standard size (in stock)';
        document.getElementById('vm-stock-hint').className = 'vm-stock-hint in-stock';
        vmCurrentMaxStock = 99;
    } else {
        sizes.forEach(sz => {
            const stock = parseInt(sz.stock, 10) || 0;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'variant-pill' + (stock <= 0 ? ' is-disabled' : '');
            btn.innerHTML = '<span>' + sz.size_label + '</span>' + (stock > 0 && stock <= 5 ? '<small style="font-size:10px; opacity:0.8;">(' + stock + ')</small>' : '');
            
            if (stock > 0) {
                btn.onclick = () => vmSelectSize(btn, sz.size_label, stock);
                if (!firstAvailable) {
                    firstAvailable = { btn, label: sz.size_label, stock };
                }
            } else {
                btn.title = 'Size ' + sz.size_label + ' is out of stock';
            }

            container.appendChild(btn);
        });

        if (firstAvailable) {
            vmSelectSize(firstAvailable.btn, firstAvailable.label, firstAvailable.stock);
        } else {
            document.getElementById('vm-selected-size').value = '';
            document.getElementById('vm-stock-hint').innerText = 'All sizes currently out of stock';
            document.getElementById('vm-stock-hint').className = 'vm-stock-hint out-of-stock';
            document.getElementById('vm-submit-btn').disabled = true;
            document.getElementById('vm-submit-btn').style.opacity = '0.5';
        }
    }

    const modal = document.getElementById('variant-modal');
    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

function vmSelectSize(btn, sizeLabel, stock) {
    document.querySelectorAll('.variant-pill').forEach(p => p.classList.remove('is-selected'));
    btn.classList.add('is-selected');

    document.getElementById('vm-selected-size').value = sizeLabel;
    vmCurrentMaxStock = Math.max(1, stock);

    const hint = document.getElementById('vm-stock-hint');
    hint.innerText = stock <= 5 ? 'Only ' + stock + ' left in size ' + sizeLabel : '✓ Size ' + sizeLabel + ' in stock (' + stock + ' available)';
    hint.className = 'vm-stock-hint in-stock';

    // Validate current qty against newly selected size stock
    const qtyInput = document.getElementById('vm-qty');
    if (parseInt(qtyInput.value, 10) > vmCurrentMaxStock) {
        qtyInput.value = vmCurrentMaxStock;
    }

    const submitBtn = document.getElementById('vm-submit-btn');
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
}

function vmChangeQty(delta) {
    const qtyInput = document.getElementById('vm-qty');
    let val = parseInt(qtyInput.value, 10) || 1;
    val = val + delta;
    if (val < 1) val = 1;
    if (val > vmCurrentMaxStock) val = vmCurrentMaxStock;
    qtyInput.value = val;
}

function closeVariantModal() {
    const modal = document.getElementById('variant-modal');
    if (modal) {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeVariantModal();
});
</script>
