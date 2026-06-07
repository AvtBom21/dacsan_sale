<?php
declare(strict_types=1);

$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$appBase = preg_replace('#/public$#', '', $scriptDir) ?: '';
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$directPublic = str_contains($requestPath, '/public/');
$publicBase = $directPublic ? $scriptDir : $appBase;
$assetBase = $publicBase . '/assets';
$mediaBase = $assetBase . '/media';
$sectionMediaBase = $mediaBase . '/sections';
$apiBase = ($directPublic ? $scriptDir : $appBase) . '/api';
$productBase = $appBase === '' ? '' : $appBase;
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặc Sản Nhà Dân - Đặc sản Gia Lai và Bình Định</title>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/favicon.svg">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/css/store.css">
</head>
<body
    data-api-base="<?= htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8') ?>"
    data-product-base="<?= htmlspecialchars($productBase, ENT_QUOTES, 'UTF-8') ?>"
>
    <header class="glass-header">
        <a class="logo" href="#ch1" onclick="scrollToSection('ch1')">Đặc Sản Nhà Dân</a>
        <button class="header-btn mobile-nav-toggle" id="mobile-nav-toggle" type="button" onclick="toggleMobileNav()" aria-label="Mở điều hướng" aria-expanded="false">Menu</button>
        <nav class="nav-links" id="nav-links" aria-label="Điều hướng chính">
            <button type="button" onclick="scrollToSection('ch2')">Câu chuyện</button>
            <button type="button" onclick="scrollToSection('ch3')">Gia Lai</button>
            <button type="button" onclick="scrollToSection('ch4')">Bình Định</button>
            <button type="button" onclick="scrollToSection('products')">Sản phẩm</button>
            <button type="button" onclick="toggleCart(true)">Giỏ hàng</button>
        </nav>
        <button class="header-btn cart-btn" type="button" onclick="toggleCart()" aria-label="Mở giỏ hàng">
            <span class="bag-icon" aria-hidden="true"></span>
            <span class="cart-badge" id="cart-badge">0</span>
        </button>
    </header>

    <div class="toast-stack" id="toast-stack" aria-live="polite" aria-atomic="false"></div>

    <main>
        <section class="chapter hero-section has-video" id="ch1" data-section-alias="hero" style="--section-bg: url('<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/highland-origin.jpeg')">
            <div class="section-media" aria-hidden="true">
                <video class="section-video" autoplay muted loop playsinline preload="metadata" poster="<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/highland-origin.jpeg">
                    <source src="<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/hero-highland.mp4" type="video/mp4">
                </video>
            </div>
            <div class="section-overlay"></div>
            <div class="hero-content">
                <p class="section-label">Đặc Sản Nhà Dân</p>
                <h1>Đặc sản nhà làm từ cao nguyên Gia Lai đến duyên hải Bình Định</h1>
                <p class="hero-subtitle">Tuyển chọn những món đặc sản quen vị, làm theo mẻ nhỏ, đóng gói sạch và giao tận nơi.</p>
                <div class="hero-actions">
                    <button class="btn-primary" type="button" onclick="scrollToSection('ch3')">Khám phá Gia Lai</button>
                    <button class="btn-secondary" type="button" onclick="scrollToSection('ch4')">Khám phá Bình Định</button>
                </div>
                <div class="trust-pills" aria-label="Cam kết chất lượng">
                    <span>Làm theo mẻ nhỏ</span>
                    <span>Nguồn gốc vùng miền</span>
                    <span>Đóng gói sạch</span>
                    <span>Giao tận nơi</span>
                </div>
            </div>
        </section>

        <section class="chapter story-section has-video" id="ch2" data-section-alias="story" style="--section-bg: url('<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/binh-dinh-boats.jpeg')">
            <div class="section-media" aria-hidden="true">
                <video class="section-video" autoplay muted loop playsinline preload="metadata" poster="<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/binh-dinh-boats.jpeg">
                    <source src="<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/story-coast.mp4" type="video/mp4">
                </video>
            </div>
            <div class="section-overlay sea"></div>
            <div class="story-copy">
                <p class="section-label">Câu chuyện</p>
                <h2>Khởi nguồn từ hai miền vị nhớ</h2>
                <p>Một bên là nắng cao nguyên Gia Lai, một bên là vị biển Bình Định. Đặc Sản Nhà Dân chọn những món quen thuộc, dễ ăn, dễ làm quà và giữ đúng tinh thần món nhà làm.</p>
            </div>
            <div class="origin-grid" aria-label="Hai vùng đặc sản">
                <article style="--card-bg: url('<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/highland-origin.jpeg')">
                    <span>Gia Lai</span>
                    <strong>Nắng gió cao nguyên, vị đậm mộc mạc.</strong>
                </article>
                <article style="--card-bg: url('<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/binh-dinh-boats.jpeg')">
                    <span>Bình Định</span>
                    <strong>Làng chài duyên hải, món quen dễ chia sẻ.</strong>
                </article>
            </div>
        </section>

        <section class="chapter region-section region-gialai" id="ch3" data-section-alias="gialai" style="--section-bg: url('<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/gia-lai-cows.jpeg')">
            <div class="section-overlay"></div>
            <div class="region-copy">
                <p class="section-label">Gia Lai</p>
                <h2>Vị nắng cao nguyên</h2>
                <p>Từ những thớ thịt được tẩm ướp vừa vị, phơi qua nắng cao nguyên để giữ độ ngọt tự nhiên, Gia Lai mang đến nhóm đặc sản đậm đà, thơm nồng và rất hợp cho bữa ăn gia đình hoặc món nhâm nhi.</p>
                <div class="region-tags">
                    <span>Nắng cao nguyên</span>
                    <span>Thịt một nắng</span>
                    <span>Đậm vị</span>
                </div>
            </div>
            <div class="rail-shell" id="products">
                <div class="rail-controls">
                    <button type="button" onclick="moveProductRail('gialai-products', -1)" aria-label="Sản phẩm Gia Lai trước">‹</button>
                    <button type="button" onclick="moveProductRail('gialai-products', 1)" aria-label="Sản phẩm Gia Lai tiếp theo">›</button>
                </div>
                <div class="product-showcase" id="gialai-products" aria-live="polite"></div>
            </div>
        </section>

        <section class="chapter region-section region-binhdinh" id="ch4" data-section-alias="binhdinh" style="--section-bg: url('<?= htmlspecialchars($sectionMediaBase, ENT_QUOTES, 'UTF-8') ?>/binh-dinh-underwater.jpeg')">
            <div class="section-overlay sea"></div>
            <div class="region-copy">
                <p class="section-label">Bình Định</p>
                <h2>Hương vị duyên hải</h2>
                <p>Bình Định là vị mặn mòi của biển, vị thơm của chả ram tôm đất, vị quen của chả lụa, nem chua và các món khô được làm theo kiểu nhà dân, dễ ăn và dễ chia sẻ.</p>
                <div class="region-tags">
                    <span>Vị biển miền Trung</span>
                    <span>Nhà làm</span>
                    <span>Dễ ăn, dễ làm quà</span>
                </div>
            </div>
            <aside class="final-cta">
                <h2>Chọn đặc sản hôm nay</h2>
                <p>Đơn hàng được ghi nhận trực tiếp trên hệ thống, shop sẽ liên hệ xác nhận trước khi giao.</p>
                <div class="region-tags">
                    <span>Không bắt buộc đăng nhập</span>
                    <span>Xác nhận trước khi giao</span>
                </div>
                <button class="btn-primary" type="button" onclick="scrollToSection('ch3')">Chọn đặc sản hôm nay</button>
            </aside>
            <div class="rail-shell">
                <div class="rail-controls">
                    <button type="button" onclick="moveProductRail('binhdinh-products', -1)" aria-label="Sản phẩm Bình Định trước">‹</button>
                    <button type="button" onclick="moveProductRail('binhdinh-products', 1)" aria-label="Sản phẩm Bình Định tiếp theo">›</button>
                </div>
                <div class="product-showcase" id="binhdinh-products" aria-live="polite"></div>
            </div>
        </section>
    </main>

    <aside class="cart-sidebar" id="cart-sidebar" aria-label="Giỏ hàng">
        <div class="cart-header">
            <h2>Giỏ hàng</h2>
            <button class="icon-btn" type="button" onclick="toggleCart(false)" aria-label="Đóng giỏ hàng">×</button>
        </div>
        <div class="cart-items" id="cart-items"></div>
        <div class="cart-footer">
            <div class="cart-total-row"><span>Tạm tính</span><strong id="cart-total">0đ</strong></div>
            <button class="btn-primary" id="cart-checkout-btn" type="button" onclick="openCheckoutFromCart()" disabled>Thanh toán</button>
            <button class="btn-secondary" type="button" onclick="toggleCart(false)">Thoát</button>
        </div>
    </aside>

    <div class="modal" id="product-modal" aria-hidden="true">
        <div class="modal-panel product-panel">
            <button class="icon-btn close-modal" type="button" onclick="closeModal()" aria-label="Đóng chi tiết sản phẩm">×</button>
            <div class="product-modal-body">
                <div>
                    <img id="modal-main-img" src="" alt="">
                    <div class="thumbnail-list" id="modal-thumbnails"></div>
                </div>
                <div class="modal-info">
                    <p class="section-label" id="modal-region"></p>
                    <h2 id="modal-name"></h2>
                    <p id="modal-desc"></p>
                    <div class="modal-notes">
                        <div><strong>Thành phần</strong><span id="modal-ingredients"></span></div>
                        <div><strong>Bảo quản</strong><span id="modal-storage"></span></div>
                    </div>
                    <div class="modal-options" id="modal-options"></div>
                    <div class="quantity-control">
                        <button type="button" onclick="changeModalQty(-1)">−</button>
                        <span id="modal-qty">1</span>
                        <button type="button" onclick="changeModalQty(1)">+</button>
                    </div>
                    <div class="modal-actions">
                        <button class="btn-secondary" type="button" onclick="addToCart()">Thêm vào giỏ</button>
                        <button class="btn-primary" type="button" onclick="buyNow()">Mua ngay</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="checkout-modal" aria-hidden="true">
        <div class="modal-panel checkout-panel">
            <button class="icon-btn close-modal" type="button" onclick="closeCheckout()" aria-label="Đóng checkout">×</button>
            <form id="checkout-form" onsubmit="submitCheckout(event)">
                <div class="checkout-summary">
                    <p class="section-label">Xác nhận đơn hàng</p>
                    <h2>Thông tin mua hàng</h2>
                    <div id="checkout-items"></div>
                    <div class="cart-total-row"><span>Tổng cộng</span><strong id="checkout-total">0đ</strong></div>
                </div>
                <div class="checkout-fields">
                    <label>Họ và tên<input name="customer_name" required autocomplete="name"></label>
                    <label>Số điện thoại<input name="customer_phone" required autocomplete="tel"></label>
                    <label>Địa chỉ nhận hàng<textarea name="customer_address" required></textarea></label>
                    <label>Hình thức nhận hàng
                        <select name="shipping_method">
                            <option value="delivery">Giao hàng</option>
                            <option value="pickup">Tự nhận</option>
                        </select>
                    </label>
                    <label>Thanh toán
                        <select name="payment_method">
                            <option value="COD">Thanh toán khi nhận hàng</option>
                            <option value="BANK">Chuyển khoản</option>
                        </select>
                    </label>
                    <label>Ghi chú<textarea name="note"></textarea></label>
                    <div class="inline-message" id="checkout-message" hidden></div>
                    <button class="btn-primary" id="checkout-submit-btn" type="submit">Hoàn tất đặt hàng</button>
                </div>
            </form>
            <div class="checkout-success" id="checkout-success" hidden>
                <h2>Đơn hàng đã được ghi nhận</h2>
                <p id="checkout-success-text"></p>
                <button class="btn-primary" type="button" onclick="closeCheckout()">Tiếp tục mua hàng</button>
            </div>
        </div>
    </div>

    <script src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/js/store.js"></script>
</body>
</html>
