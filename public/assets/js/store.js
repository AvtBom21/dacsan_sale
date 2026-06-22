const apiBase = document.body.dataset.apiBase || 'api';
const productBase = document.body.dataset.productBase || '';
const state = {
    products: [],
    settings: {},
    customer: null,
    bestSellers: [],
    reviews: [],
    customerOrders: [],
    cart: [],
    card: {},
    currentProduct: null,
    modalUomId: '',
    modalQty: 1,
    checkoutItems: [],
    checkoutSource: 'cart',
    checkoutToken: ''
};

function apiUrl(action) {
    const separator = apiBase.includes('?') ? '&' : '?';
    return `${apiBase}${separator}action=${encodeURIComponent(action)}`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

async function apiGet(action) {
    const response = await fetch(apiUrl(action), { credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok || payload.status !== 'ok') {
        throw new Error(payload.message || 'Không thể tải dữ liệu.');
    }
    return payload.data;
}

async function apiPost(action, body) {
    const response = await fetch(apiUrl(action), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    const payload = await response.json();
    if (!response.ok || payload.status !== 'ok') {
        throw new Error(payload.message || 'Không thể gửi dữ liệu.');
    }
    return payload.data;
}

function normalizeImage(image, fallback = 'products_image/placeholder.svg') {
    const raw = typeof image === 'string' ? image : (image?.url || image?.path || fallback);
    let path = String(raw || fallback).replace(/\\/g, '/').replace(/^(\.\.\/)+/, '').replace(/^\/+/, '');
    if (!path.startsWith('products_image/')) {
        path = fallback;
    }
    const prefix = productBase.replace(/\/$/, '');
    return `${prefix}/${path}`.replace(/\/{2,}/g, '/');
}

function regionLabel(product) {
    return product.region === 'gia-lai' ? 'Gia Lai' : 'Bình Định';
}

function regionMicrocopy(product) {
    return product.region === 'gia-lai' ? 'Nắng cao nguyên' : 'Vị biển miền Trung';
}

function defaultUom(product) {
    return product.default_uom || product.sellable_uoms?.[0] || null;
}

function productById(productId) {
    return state.products.find(product => product.product_id === productId) || null;
}

function cardState(productId) {
    return state.card[productId];
}

function formatMoney(value) {
    return `${Number(value || 0).toLocaleString('vi-VN')}đ`;
}

function formatPhoneDisplay(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length === 10) {
        return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
    }
    return String(value || '').trim();
}

function renderStoreContact() {
    const root = document.querySelector('[data-store-contact]');
    if (!root) return;

    const phone = String(state.settings.store_phone || '').replace(/\D/g, '');
    const zaloLink = String(state.settings.zalo_link || '').trim();
    const phoneLink = root.querySelector('[data-contact-phone]');
    const zaloAnchor = root.querySelector('[data-contact-zalo]');
    const phoneText = root.querySelector('[data-contact-phone-text]');
    const hasPhone = phone.length >= 9;
    const hasZalo = /^https:\/\/zalo\.me\/[A-Za-z0-9._-]+(?:[/?#].*)?$/i.test(zaloLink);

    if (phoneText) {
        phoneText.hidden = !hasPhone;
        phoneText.textContent = hasPhone ? formatPhoneDisplay(phone) : '';
    }
    if (phoneLink) {
        phoneLink.hidden = !hasPhone;
        if (hasPhone) phoneLink.href = `tel:${phone}`;
    }
    if (zaloAnchor) {
        zaloAnchor.hidden = !hasZalo;
        if (hasZalo) zaloAnchor.href = zaloLink;
    }
    root.hidden = !hasPhone && !hasZalo;
}

function toast(message, type = 'info') {
    const stack = document.getElementById('toast-stack');
    const item = document.createElement('div');
    item.className = `toast ${type}`;
    item.textContent = message;
    stack.appendChild(item);
    setTimeout(() => item.remove(), 3600);
}

function setInlineMessage(node, message, type = '') {
    if (!node) return;
    node.textContent = message;
    node.className = `inline-message ${type}`.trim();
    node.hidden = message === '';
}

function updateCustomerNavigation() {
    const button = document.querySelector('[data-customer-auth-open]');
    if (button) button.textContent = state.customer ? 'Tài khoản' : 'Đăng nhập';
}

function renderBestSellers() {
    const list = document.querySelector('[data-best-seller-list]');
    if (!list) return;
    const products = state.bestSellers.length ? state.bestSellers : state.products.slice(0, 3);
    list.innerHTML = products.map((product, index) => `
        <button type="button" onclick="openModal('${escapeHtml(product.product_id)}')">
            <span>${String(index + 1).padStart(2, '0')}</span>
            <strong>${escapeHtml(product.product_name)}</strong>
            <small>${escapeHtml(product.price_display || product.default_uom?.unit_price_display || '')}</small>
        </button>
    `).join('');
}

function renderPublicReviews() {
    const list = document.querySelector('[data-review-list]');
    if (!list) return;
    if (!state.reviews.length) {
        list.innerHTML = '<p class="review-empty">Chưa có đánh giá được duyệt. Hãy chia sẻ sau khi hoàn tất đơn hàng.</p>';
        return;
    }
    const visibleCount = window.matchMedia('(max-width: 760px)').matches ? 1 : 3;
    list.innerHTML = state.reviews.slice(0, visibleCount).map(review => `
        <article class="review-card">
            <div class="review-stars" aria-label="${Number(review.rating)} trên 5 sao">${'★'.repeat(Number(review.rating))}</div>
            <p>${escapeHtml(review.review_text)}</p>
            <footer><strong>${escapeHtml(review.customer_name)}</strong><span>${escapeHtml(review.product_name)}</span></footer>
        </article>
    `).join('');
}

function renderEmptyRail(container) {
    container.innerHTML = `
        <div class="rail-empty">
            <div>
                <strong>Sản phẩm đang được cập nhật</strong>
                <p>Shop đang bổ sung món mới cho vùng này. Vui lòng quay lại sau.</p>
            </div>
        </div>
    `;
}

function buildCard(product) {
    const selected = defaultUom(product);
    if (!selected) return '';

    state.card[product.product_id] = {
        uomId: selected.uom_id,
        qty: 1
    };

    const chips = product.sellable_uoms.map(uom => `
        <button class="uom-chip ${uom.uom_id === selected.uom_id ? 'active' : ''}" type="button"
            onclick="selectCardUom('${escapeHtml(product.product_id)}', '${escapeHtml(uom.uom_id)}', this)">
            ${escapeHtml(uom.uom_label)}
        </button>
    `).join('');

    return `
        <article class="product-card cinematic-card" data-product-id="${escapeHtml(product.product_id)}">
            <img src="${escapeHtml(normalizeImage(product.base_image))}" alt="${escapeHtml(product.product_name)}" onclick="openModal('${escapeHtml(product.product_id)}')">
            <div class="card-kicker">
                <span>${escapeHtml(regionLabel(product))}</span>
                <span>${escapeHtml(regionMicrocopy(product))}</span>
            </div>
            <h3 onclick="openModal('${escapeHtml(product.product_id)}')">${escapeHtml(product.product_name)}</h3>
            <div class="card-price-row">
                <span class="card-price">${escapeHtml(selected.unit_price_display)}</span>
                <span class="card-unit">/ ${escapeHtml(selected.uom_label)}</span>
            </div>
            <div class="uom-chips">${chips}</div>
            <div class="card-controls">
                <div class="quantity-control">
                    <button type="button" onclick="changeCardQty('${escapeHtml(product.product_id)}', -1)">−</button>
                    <span id="qty-${escapeHtml(product.product_id)}">1</span>
                    <button type="button" onclick="changeCardQty('${escapeHtml(product.product_id)}', 1)">+</button>
                </div>
            </div>
            <div class="card-actions">
                <button class="btn-secondary" type="button" onclick="addToCartFromCard('${escapeHtml(product.product_id)}')">Thêm vào giỏ</button>
                <button class="btn-primary" type="button" onclick="buyNowFromCard('${escapeHtml(product.product_id)}')">Mua ngay</button>
            </div>
        </article>
    `;
}

function renderRail(railId, products) {
    const rail = document.getElementById(railId);
    if (!rail) return;
    if (!products.length) {
        renderEmptyRail(rail);
        return;
    }
    rail.innerHTML = products.map(buildCard).join('');
}

function renderCatalog() {
    renderRail('gialai-products', state.products.filter(product => product.region === 'gia-lai'));
    renderRail('binhdinh-products', state.products.filter(product => product.region === 'binh-dinh'));
}

function selectCardUom(productId, uomId, button) {
    const card = cardState(productId);
    const product = productById(productId);
    const uom = product?.sellable_uoms?.find(item => item.uom_id === uomId);
    if (!card || !product || !uom) return;
    card.uomId = uomId;
    const root = button.closest('.product-card');
    root.querySelector('.card-price').textContent = uom.unit_price_display;
    root.querySelector('.card-unit').textContent = `/ ${uom.uom_label}`;
    root.querySelectorAll('.uom-chip').forEach(chip => chip.classList.remove('active'));
    button.classList.add('active');
}

function changeCardQty(productId, delta) {
    const card = cardState(productId);
    if (!card) return;
    card.qty = Math.max(1, Math.min(99, card.qty + delta));
    document.getElementById(`qty-${productId}`).textContent = String(card.qty);
}

function itemFromCard(productId) {
    const product = productById(productId);
    const card = cardState(productId);
    if (!product || !card) return null;
    const uom = product.sellable_uoms.find(item => item.uom_id === card.uomId) || defaultUom(product);
    if (!uom) return null;
    return {
        product_id: product.product_id,
        product_name: product.product_name,
        image: normalizeImage(product.base_image),
        uom_id: uom.uom_id,
        uom_label: uom.uom_label,
        unit_price_vnd: uom.unit_price_vnd,
        unit_price_display: uom.unit_price_display,
        qty: card.qty
    };
}

function addToCartFromCard(productId) {
    const item = itemFromCard(productId);
    if (!item) return;
    const existing = state.cart.find(entry => entry.product_id === item.product_id && entry.uom_id === item.uom_id);
    if (existing) {
        existing.qty += item.qty;
    } else {
        state.cart.push({ ...item });
    }
    renderCart();
    toast('Đã thêm sản phẩm vào giỏ.');
}

function buyNowFromCard(productId) {
    const item = itemFromCard(productId);
    if (!item) return;
    openCheckout([item], 'instant');
}

function renderCart() {
    const badge = document.getElementById('cart-badge');
    const items = document.getElementById('cart-items');
    const total = state.cart.reduce((sum, item) => sum + item.unit_price_vnd * item.qty, 0);
    badge.textContent = String(state.cart.reduce((sum, item) => sum + item.qty, 0));
    document.getElementById('cart-total').textContent = formatMoney(total);
    document.getElementById('cart-checkout-btn').disabled = state.cart.length === 0;

    if (!state.cart.length) {
        items.innerHTML = '<div class="empty-state"><div><strong>Giỏ hàng đang trống</strong><p>Chọn một đặc sản để bắt đầu đơn hàng.</p></div></div>';
        return;
    }

    items.innerHTML = state.cart.map((item, index) => `
        <div class="cart-line">
            <img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.product_name)}">
            <div>
                <h3>${escapeHtml(item.product_name)}</h3>
                <p>${escapeHtml(item.uom_label)} · ${escapeHtml(item.unit_price_display)} x ${item.qty}</p>
            </div>
            <button class="icon-btn" type="button" onclick="removeCartItem(${index})" aria-label="Xóa sản phẩm">×</button>
        </div>
    `).join('');
}

function removeCartItem(index) {
    state.cart.splice(index, 1);
    renderCart();
}

function toggleCart(forceOpen) {
    const sidebar = document.getElementById('cart-sidebar');
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', shouldOpen);
    toggleMobileNav(false);
}

function openModal(productId) {
    const product = productById(productId);
    if (!product) return;
    state.currentProduct = product;
    const selected = defaultUom(product);
    state.modalUomId = selected?.uom_id || '';
    state.modalQty = 1;
    const images = product.gallery_images?.length ? product.gallery_images : [product.base_image];
    document.getElementById('modal-main-img').src = normalizeImage(images[0]);
    document.getElementById('modal-main-img').alt = product.product_name;
    document.getElementById('modal-region').textContent = regionLabel(product);
    document.getElementById('modal-name').textContent = product.product_name;
    document.getElementById('modal-desc').textContent = product.full_description || product.short_description || 'Đặc sản nhà làm, đóng gói sạch và giao tận nơi.';
    document.getElementById('modal-ingredients').textContent = product.ingredients || 'Thông tin đang được cập nhật.';
    document.getElementById('modal-storage').textContent = product.shelf_life_value
        ? `Dùng tốt trong ${product.shelf_life_value} ${product.shelf_life_unit}.`
        : 'Bảo quản nơi khô ráo, thoáng mát.';
    document.getElementById('modal-qty').textContent = '1';
    document.getElementById('modal-thumbnails').innerHTML = images.map((image, index) => `
        <button type="button" onclick="selectModalImage('${escapeHtml(normalizeImage(image))}')">
            <img src="${escapeHtml(normalizeImage(image))}" alt="${escapeHtml(product.product_name)} ${index + 1}">
        </button>
    `).join('');
    document.getElementById('modal-options').innerHTML = product.sellable_uoms.map(uom => `
        <button class="uom-chip ${uom.uom_id === state.modalUomId ? 'active' : ''}" type="button" onclick="selectModalUom('${escapeHtml(uom.uom_id)}', this)">
            ${escapeHtml(uom.uom_label)} · ${escapeHtml(uom.unit_price_display)}
        </button>
    `).join('');
    document.getElementById('product-modal').classList.add('active');
    document.getElementById('product-modal').setAttribute('aria-hidden', 'false');
}

function selectModalImage(src) {
    document.getElementById('modal-main-img').src = src;
}

function selectModalUom(uomId, button) {
    state.modalUomId = uomId;
    button.parentElement.querySelectorAll('.uom-chip').forEach(chip => chip.classList.remove('active'));
    button.classList.add('active');
}

function changeModalQty(delta) {
    state.modalQty = Math.max(1, Math.min(99, state.modalQty + delta));
    document.getElementById('modal-qty').textContent = String(state.modalQty);
}

function closeModal() {
    document.getElementById('product-modal').classList.remove('active');
    document.getElementById('product-modal').setAttribute('aria-hidden', 'true');
}

function modalItem() {
    const product = state.currentProduct;
    if (!product) return null;
    const uom = product.sellable_uoms.find(item => item.uom_id === state.modalUomId) || defaultUom(product);
    if (!uom) return null;
    return {
        product_id: product.product_id,
        product_name: product.product_name,
        image: normalizeImage(product.base_image),
        uom_id: uom.uom_id,
        uom_label: uom.uom_label,
        unit_price_vnd: uom.unit_price_vnd,
        unit_price_display: uom.unit_price_display,
        qty: state.modalQty
    };
}

function addToCart() {
    const item = modalItem();
    if (!item) return;
    const existing = state.cart.find(entry => entry.product_id === item.product_id && entry.uom_id === item.uom_id);
    if (existing) existing.qty += item.qty;
    else state.cart.push({ ...item });
    renderCart();
    closeModal();
    toast('Đã thêm sản phẩm vào giỏ.');
}

function buyNow() {
    const item = modalItem();
    if (!item) return;
    closeModal();
    openCheckout([item], 'instant');
}

function checkoutPayloadItems(items) {
    return items.map(item => ({
        product_id: item.product_id,
        uom_id: item.uom_id,
        qty: item.qty
    }));
}

function openCheckoutFromCart() {
    if (!state.cart.length) {
        toast('Giỏ hàng đang trống.', 'error');
        return;
    }
    openCheckout(state.cart, 'cart');
}

function openCheckout(items, source) {
    state.checkoutItems = items.map(item => ({ ...item }));
    state.checkoutSource = source;
    const total = state.checkoutItems.reduce((sum, item) => sum + item.unit_price_vnd * item.qty, 0);
    document.getElementById('checkout-items').innerHTML = state.checkoutItems.map(item => `
        <div class="checkout-line">
            <img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.product_name)}">
            <div>
                <h3>${escapeHtml(item.product_name)}</h3>
                <p>${escapeHtml(item.uom_label)} · ${escapeHtml(item.unit_price_display)} x ${item.qty}</p>
            </div>
        </div>
    `).join('');
    document.getElementById('checkout-total').textContent = formatMoney(total);
    document.getElementById('checkout-success').hidden = true;
    const checkoutForm = document.getElementById('checkout-form');
    checkoutForm.hidden = false;
    if (state.customer) {
        checkoutForm.elements.customer_name.value = state.customer.customer_name || '';
        checkoutForm.elements.customer_phone.value = state.customer.customer_phone || '';
        checkoutForm.elements.customer_address.value = state.customer.customer_address || '';
    }
    setCheckoutMessage('');
    document.getElementById('checkout-modal').classList.add('active');
    document.getElementById('checkout-modal').setAttribute('aria-hidden', 'false');
    toggleCart(false);
}

function closeCheckout() {
    document.getElementById('checkout-modal').classList.remove('active');
    document.getElementById('checkout-modal').setAttribute('aria-hidden', 'true');
}

function setCheckoutMessage(message, type = '') {
    const box = document.getElementById('checkout-message');
    box.textContent = message;
    box.hidden = message === '';
    box.className = `inline-message ${type}`.trim();
}

async function submitCheckout(event) {
    event.preventDefault();
    if (!state.checkoutItems.length) return;
    const form = event.currentTarget;
    const button = document.getElementById('checkout-submit-btn');
    const formData = new FormData(form);
    button.disabled = true;
    setCheckoutMessage('Đang gửi đơn hàng đến hệ thống...');
    try {
        if (!state.checkoutToken) {
            const tokenData = await apiGet('checkout-token');
            state.checkoutToken = tokenData.checkout_token;
        }
        const data = await apiPost('checkout', {
            checkout_token: state.checkoutToken,
            items: checkoutPayloadItems(state.checkoutItems),
            customer_name: formData.get('customer_name'),
            customer_phone: formData.get('customer_phone'),
            customer_address: formData.get('customer_address'),
            shipping_method: formData.get('shipping_method'),
            payment_method: formData.get('payment_method'),
            note: formData.get('note')
        });
        state.checkoutToken = (await apiGet('checkout-token')).checkout_token || '';
        if (state.checkoutSource === 'cart') {
            state.cart = [];
            renderCart();
        }
        form.reset();
        form.hidden = true;
        document.getElementById('checkout-success-text').textContent = `${data.message} Mã đơn: ${data.order_id}. Shop sẽ liên hệ xác nhận trước khi giao.`;
        document.getElementById('checkout-success').hidden = false;
    } catch (error) {
        setCheckoutMessage(error.message || 'Không thể tạo đơn hàng lúc này.', 'error');
    } finally {
        button.disabled = false;
    }
}

function openCustomerArea() {
    if (state.customer) {
        openCustomerAccount();
    } else {
        const modal = document.getElementById('customer-auth-modal');
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        switchAuthMode('login');
    }
    toggleMobileNav(false);
}

function closeCustomerAuth() {
    const modal = document.getElementById('customer-auth-modal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
}

function switchAuthMode(mode) {
    const login = mode === 'login';
    document.querySelector('[data-customer-login]').hidden = !login;
    document.querySelector('[data-customer-register]').hidden = login;
    document.querySelectorAll('[data-auth-tab]').forEach(button => {
        button.classList.toggle('active', button.dataset.authTab === mode);
    });
    document.getElementById('customer-auth-title').textContent = login ? 'Đăng nhập' : 'Tạo tài khoản';
    document.querySelectorAll('[data-auth-message]').forEach(node => setInlineMessage(node, ''));
}

async function submitCustomerLogin(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-auth-message]');
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        const data = await apiPost('customer-login', {
            checkout_token: state.checkoutToken,
            customer_phone: form.elements.customer_phone.value,
            password: form.elements.password.value
        });
        state.customer = data.customer;
        state.checkoutToken = data.checkout_token;
        updateCustomerNavigation();
        closeCustomerAuth();
        await openCustomerAccount();
    } catch (error) {
        setInlineMessage(message, error.message || 'Không thể đăng nhập.', 'error');
    } finally {
        submit.disabled = false;
    }
}

async function submitCustomerRegister(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-auth-message]');
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        const data = await apiPost('customer-register', {
            checkout_token: state.checkoutToken,
            customer_name: form.elements.customer_name.value,
            customer_phone: form.elements.customer_phone.value,
            customer_address: form.elements.customer_address.value,
            password: form.elements.password.value
        });
        state.customer = data.customer;
        state.checkoutToken = data.checkout_token;
        updateCustomerNavigation();
        form.reset();
        closeCustomerAuth();
        await openCustomerAccount();
    } catch (error) {
        setInlineMessage(message, error.message || 'Không thể tạo tài khoản.', 'error');
    } finally {
        submit.disabled = false;
    }
}

async function openCustomerAccount() {
    if (!state.customer) {
        openCustomerArea();
        return;
    }
    const modal = document.getElementById('customer-account-modal');
    const form = modal.querySelector('[data-customer-profile]');
    form.elements.customer_name.value = state.customer.customer_name || '';
    form.elements.customer_phone.value = state.customer.customer_phone || '';
    form.elements.customer_address.value = state.customer.customer_address || '';
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    await loadCustomerOrders();
}

function closeCustomerAccount() {
    const modal = document.getElementById('customer-account-modal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
}

async function submitCustomerProfile(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-profile-message]');
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        const data = await apiPost('customer-profile-update', {
            checkout_token: state.checkoutToken,
            customer_name: form.elements.customer_name.value,
            customer_address: form.elements.customer_address.value
        });
        state.customer = data.customer;
        state.checkoutToken = data.checkout_token;
        setInlineMessage(message, 'Đã lưu thông tin.', 'success');
    } catch (error) {
        setInlineMessage(message, error.message || 'Không thể lưu thông tin.', 'error');
    } finally {
        submit.disabled = false;
    }
}

function orderStatusLabel(status) {
    return ({
        new: 'Mới',
        confirmed: 'Đã xác nhận',
        ordered: 'Đã đặt hàng',
        received: 'Đã nhận hàng',
        ready: 'Sẵn sàng giao',
        done: 'Hoàn tất',
        cancelled: 'Đã hủy'
    })[status] || status;
}

function renderCustomerOrders() {
    const root = document.querySelector('[data-customer-orders]');
    if (!root) return;
    if (!state.customerOrders.length) {
        root.innerHTML = '<p class="account-empty">Bạn chưa có đơn hàng nào.</p>';
        return;
    }
    root.innerHTML = state.customerOrders.map(order => `
        <article class="account-order">
            <header>
                <div><strong>${escapeHtml(order.order_id)}</strong><small>${escapeHtml(order.created_at)}</small></div>
                <span class="order-status">${escapeHtml(orderStatusLabel(order.status))}</span>
            </header>
            <div class="account-order-items">
                ${(order.items || []).map(item => `
                    <div class="account-order-line">
                        <span>${escapeHtml(item.product_name_snapshot)} · ${escapeHtml(item.uom_label_snapshot)} × ${escapeHtml(item.qty_uom)}</span>
                        <strong>${formatMoney(item.line_total_vnd)}</strong>
                    </div>
                    ${order.status === 'done' && !item.review_status ? `
                        <form class="review-form" onsubmit="submitProductReview(event)" data-order-id="${escapeHtml(order.order_id)}" data-product-id="${escapeHtml(item.product_id)}">
                            <select name="rating" aria-label="Điểm đánh giá">
                                <option value="5">5 sao</option><option value="4">4 sao</option>
                                <option value="3">3 sao</option><option value="2">2 sao</option><option value="1">1 sao</option>
                            </select>
                            <input name="review_text" minlength="10" maxlength="1000" placeholder="Chia sẻ cảm nhận của bạn" required>
                            <button class="btn-secondary" type="submit">Gửi đánh giá</button>
                            <small data-review-message></small>
                        </form>` : ''}
                    ${item.review_status ? `<small class="review-submitted">Đánh giá: ${escapeHtml(item.review_status === 'approved' ? 'đã duyệt' : item.review_status === 'rejected' ? 'đã từ chối' : 'đang chờ duyệt')}</small>` : ''}
                `).join('')}
            </div>
            <footer><span>Tổng thanh toán</span><strong>${formatMoney(order.total_vnd)}</strong></footer>
        </article>
    `).join('');
}

async function loadCustomerOrders() {
    const root = document.querySelector('[data-customer-orders]');
    if (!root) return;
    root.innerHTML = '<p class="account-empty">Đang tải đơn hàng...</p>';
    try {
        const data = await apiGet('customer-orders');
        state.customerOrders = data.items || [];
        renderCustomerOrders();
    } catch (error) {
        root.innerHTML = `<p class="account-empty">${escapeHtml(error.message || 'Không thể tải đơn hàng.')}</p>`;
    }
}

async function submitProductReview(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-review-message]');
    const submit = form.querySelector('button[type="submit"]');
    submit.disabled = true;
    try {
        const data = await apiPost('review-create', {
            checkout_token: state.checkoutToken,
            order_id: form.dataset.orderId,
            product_id: form.dataset.productId,
            rating: Number(form.elements.rating.value),
            review_text: form.elements.review_text.value
        });
        state.checkoutToken = data.checkout_token;
        message.textContent = data.message;
        form.elements.review_text.value = '';
    } catch (error) {
        message.textContent = error.message || 'Không thể gửi đánh giá.';
    } finally {
        submit.disabled = false;
    }
}

async function logoutCustomer() {
    try {
        const data = await apiPost('customer-logout', { checkout_token: state.checkoutToken });
        state.checkoutToken = data.checkout_token;
    } catch (error) {
        toast(error.message || 'Không thể đăng xuất.', 'error');
        return;
    }
    state.customer = null;
    state.customerOrders = [];
    updateCustomerNavigation();
    closeCustomerAccount();
    toast('Đã đăng xuất.');
}

const SECTION_IDS = ['ch1', 'ch2', 'ch3', 'ch4'];
const SNAP_DURATION_MS = 760;
const SNAP_IDLE_MS = 130;
let isSectionSnapping = false;
let sectionSnapTimer = 0;
let touchStartX = 0;
let touchStartY = 0;

function normalizeSectionId(sectionId) {
    const aliases = {
        hero: 'ch1',
        story: 'ch2',
        gialai: 'ch3',
        'gia-lai': 'ch3',
        binhdinh: 'ch4',
        'binh-dinh': 'ch4',
        products: 'ch3',
        sanpham: 'ch3'
    };
    return aliases[sectionId] || sectionId || 'ch1';
}

function updateChapterActiveState(activeId) {
    const targetId = activeId || currentSectionId();
    SECTION_IDS.forEach(id => {
        const section = document.getElementById(id);
        if (!section) return;
        const isActive = id === targetId;
        section.classList.toggle('is-active', isActive);
        section.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });
}

function isOverlayInteraction(target) {
    return Boolean(target?.closest?.('.modal.active, .cart-sidebar.open, .nav-links'));
}

function currentSectionId() {
    const viewportMiddle = window.scrollY + window.innerHeight / 2;
    let closestId = 'ch1';
    let closestDistance = Number.POSITIVE_INFINITY;
    SECTION_IDS.forEach(id => {
        const section = document.getElementById(id);
        if (!section) return;
        const sectionMiddle = section.offsetTop + section.offsetHeight / 2;
        const distance = Math.abs(sectionMiddle - viewportMiddle);
        if (distance < closestDistance) {
            closestDistance = distance;
            closestId = id;
        }
    });
    return closestId;
}

function scrollToSection(sectionId) {
    const targetId = normalizeSectionId(sectionId);
    const target = document.getElementById(targetId);
    if (target) {
        isSectionSnapping = true;
        updateChapterActiveState(targetId);
        window.scrollTo({ top: target.offsetTop, behavior: 'smooth' });
        window.clearTimeout(sectionSnapTimer);
        sectionSnapTimer = window.setTimeout(() => {
            isSectionSnapping = false;
            updateChapterActiveState(targetId);
        }, SNAP_DURATION_MS);
    }
    toggleMobileNav(false);
}

function snapToAdjacentSection(direction) {
    const activeId = currentSectionId();
    const currentIndex = SECTION_IDS.indexOf(activeId);
    const nextIndex = Math.max(0, Math.min(SECTION_IDS.length - 1, currentIndex + direction));
    if (nextIndex === currentIndex) {
        scrollToSection(activeId);
        return;
    }
    scrollToSection(SECTION_IDS[nextIndex]);
}

function snapToNearestSection() {
    if (isSectionSnapping) return;
    const targetId = currentSectionId();
    const target = document.getElementById(targetId);
    if (!target) return;
    if (Math.abs(window.scrollY - target.offsetTop) < 4) {
        updateChapterActiveState(targetId);
        return;
    }
    scrollToSection(targetId);
}

function moveProductRail(railId, direction) {
    const rail = document.getElementById(railId);
    if (!rail) return;
    const card = rail.querySelector('.product-card');
    const styles = window.getComputedStyle(rail);
    const gap = parseFloat(styles.columnGap || styles.gap) || 24;
    const visibleCards = window.matchMedia('(max-width: 760px)').matches ? 2 : 1;
    const step = card ? (card.getBoundingClientRect().width + gap) * visibleCards : rail.clientWidth * 0.8;
    rail.scrollBy({ left: step * direction, behavior: 'smooth' });
}

function toggleMobileNav(forceOpen) {
    const button = document.getElementById('mobile-nav-toggle');
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !document.body.classList.contains('mobile-nav-open');
    document.body.classList.toggle('mobile-nav-open', shouldOpen);
    button.textContent = shouldOpen ? 'Đóng' : 'Menu';
    button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
}

async function initStorefront() {
    renderCart();
    try {
        const [catalog, token, settings, customerSession, bestSellers, reviews] = await Promise.all([
            apiGet('catalog'),
            apiGet('checkout-token'),
            apiGet('settings').catch(() => ({})),
            apiGet('customer-session').catch(() => ({ authenticated: false, customer: null })),
            apiGet('best-sellers').catch(() => ({ items: [] })),
            apiGet('reviews-public').catch(() => ({ items: [] }))
        ]);
        state.products = catalog.items || [];
        state.checkoutToken = token.checkout_token || '';
        state.settings = settings || {};
        state.customer = customerSession.authenticated ? customerSession.customer : null;
        state.bestSellers = bestSellers.items || [];
        state.reviews = reviews.items || [];
        renderCatalog();
        renderStoreContact();
        renderBestSellers();
        renderPublicReviews();
        updateCustomerNavigation();
    } catch (error) {
        renderRail('gialai-products', []);
        renderRail('binhdinh-products', []);
        toast(error.message || 'Không thể tải catalog.', 'error');
    }
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        closeModal();
        closeCheckout();
        closeCustomerAuth();
        closeCustomerAccount();
        toggleCart(false);
        toggleMobileNav(false);
    }
});

let activeSectionTicking = false;
window.addEventListener('scroll', () => {
    if (activeSectionTicking) return;
    activeSectionTicking = true;
    window.requestAnimationFrame(() => {
        updateChapterActiveState();
        activeSectionTicking = false;
    });
    if (!isSectionSnapping) {
        window.clearTimeout(sectionSnapTimer);
        sectionSnapTimer = window.setTimeout(snapToNearestSection, SNAP_IDLE_MS);
    }
}, { passive: true });

document.addEventListener('DOMContentLoaded', initStorefront);
document.addEventListener('DOMContentLoaded', () => updateChapterActiveState('ch1'));

window.addEventListener('wheel', event => {
    if (isOverlayInteraction(event.target)) return;
    if (Math.abs(event.deltaY) < 28 || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
    event.preventDefault();
    if (isSectionSnapping) return;
    snapToAdjacentSection(event.deltaY > 0 ? 1 : -1);
}, { passive: false });

window.addEventListener('touchstart', event => {
    if (!event.touches.length) return;
    touchStartX = event.touches[0].clientX;
    touchStartY = event.touches[0].clientY;
}, { passive: true });

window.addEventListener('touchend', event => {
    if (isOverlayInteraction(event.target) || !event.changedTouches.length || isSectionSnapping) return;
    const deltaX = event.changedTouches[0].clientX - touchStartX;
    const deltaY = event.changedTouches[0].clientY - touchStartY;
    if (Math.abs(deltaY) < 46 || Math.abs(deltaX) > Math.abs(deltaY)) return;
    snapToAdjacentSection(deltaY < 0 ? 1 : -1);
}, { passive: true });

document.addEventListener('keydown', event => {
    if (isOverlayInteraction(event.target)) return;
    const keys = {
        PageDown: 1,
        ArrowDown: 1,
        Space: 1,
        PageUp: -1,
        ArrowUp: -1
    };
    if (!(event.key in keys)) return;
    event.preventDefault();
    snapToAdjacentSection(keys[event.key]);
});
