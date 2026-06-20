(function () {
    const shell = document.querySelector('.admin-shell');
    if (!shell) {
        return;
    }

    window.DSND_ADMIN = {
        csrfToken: shell.getAttribute('data-csrf') || '',
        apiBase: shell.getAttribute('data-api-base') || './api/index.php',
    };

    async function adminRequest(action, options = {}) {
        const requestOptions = { ...options };
        const headers = {
            Accept: 'application/json',
            'X-CSRF-Token': window.DSND_ADMIN.csrfToken,
            ...(requestOptions.headers || {}),
        };

        if (requestOptions.body && !(requestOptions.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        const separator = window.DSND_ADMIN.apiBase.includes('?') ? '&' : '?';
        const response = await fetch(
            `${window.DSND_ADMIN.apiBase}${separator}action=${encodeURIComponent(action)}`,
            {
                credentials: 'same-origin',
                ...requestOptions,
                headers,
            }
        );
        const payload = await response.json().catch(() => ({
            status: 'error',
            message: 'Phản hồi từ máy chủ không hợp lệ.',
        }));

        if (!response.ok || payload.status !== 'ok') {
            const error = new Error(payload.message || 'Không thể xử lý yêu cầu.');
            error.status = response.status;
            throw error;
        }

        return payload.data;
    }

    window.DSND_ADMIN.adminRequest = adminRequest;

    document.addEventListener('click', async (event) => {
        const productActiveButton = event.target.closest('[data-product-active]');
        if (productActiveButton) {
            const productId = productActiveButton.getAttribute('data-product-id') || '';
            const isActive = Number(productActiveButton.getAttribute('data-product-active') || 0);
            productActiveButton.disabled = true;
            try {
                await adminRequest('product-active', {
                    method: 'POST',
                    body: JSON.stringify({
                        product_id: productId,
                        is_active: isActive,
                    }),
                });
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'Không thể cập nhật trạng thái sản phẩm.');
                productActiveButton.disabled = false;
            }
            return;
        }

        const addUomButton = event.target.closest('[data-add-uom]');
        if (addUomButton) {
            document.querySelector('[data-uom-list]')?.insertAdjacentHTML('beforeend', uomRowTemplate());
            return;
        }

        const addImageButton = event.target.closest('[data-add-image]');
        if (addImageButton) {
            document.querySelector('[data-image-list]')?.insertAdjacentHTML('beforeend', imageRowTemplate());
            return;
        }

        const printButton = event.target.closest('[data-print-invoice]');
        if (printButton) {
            window.print();
            return;
        }

        const statusButton = event.target.closest('[data-order-status]');
        if (!statusButton) {
            return;
        }

        const nextStatus = statusButton.getAttribute('data-order-status') || '';
        const orderId = statusButton.getAttribute('data-order-id') || '';
        if (!nextStatus || !orderId) {
            return;
        }

        if (nextStatus === 'cancelled' && !window.confirm('Bạn chắc chắn muốn hủy đơn hàng này?')) {
            return;
        }

        const actionPanel = statusButton.closest('.order-operations');
        const errorNode = actionPanel?.querySelector('[data-order-action-error]');
        const actionButtons = actionPanel?.querySelectorAll('[data-order-status]') || [];

        actionButtons.forEach((button) => {
            button.disabled = true;
            button.classList.add('is-loading');
        });
        if (errorNode) {
            errorNode.hidden = true;
            errorNode.textContent = '';
        }

        try {
            await adminRequest('order-status', {
                method: 'POST',
                body: JSON.stringify({
                    order_id: orderId,
                    new_status: nextStatus,
                }),
            });
            window.location.reload();
        } catch (error) {
            if (errorNode) {
                errorNode.textContent = error.message || 'Không thể cập nhật trạng thái.';
                errorNode.hidden = false;
            } else {
                window.alert(error.message || 'Không thể cập nhật trạng thái.');
            }
        } finally {
            actionButtons.forEach((button) => {
                button.disabled = false;
                button.classList.remove('is-loading');
            });
        }
    });

    const productForm = document.querySelector('[data-product-form]');
    if (productForm) {
        productForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitButton = productForm.querySelector('[type="submit"]');
            const errorNode = productForm.querySelector('[data-product-form-error]');
            submitButton.disabled = true;
            errorNode.hidden = true;
            errorNode.textContent = '';

            try {
                const product = serializeProductForm(productForm);
                const saved = await adminRequest('product-save', {
                    method: 'POST',
                    body: JSON.stringify(product),
                });
                window.location.href = `./?page=product-detail&id=${encodeURIComponent(saved.product_id)}`;
            } catch (error) {
                errorNode.textContent = error.message || 'Không thể lưu sản phẩm.';
                errorNode.hidden = false;
                submitButton.disabled = false;
            }
        });
    }

    function fieldValue(root, selector) {
        return root.querySelector(selector)?.value?.trim() || '';
    }

    function checked(root, selector) {
        return root.querySelector(selector)?.checked ? 1 : 0;
    }

    function serializeProductForm(form) {
        const payload = {};
        form.querySelectorAll('[data-field]').forEach((field) => {
            const key = field.getAttribute('data-field');
            payload[key] = field.type === 'checkbox' ? (field.checked ? 1 : 0) : field.value.trim();
        });
        payload.shelf_life_value = Number(payload.shelf_life_value || 0);
        payload.uoms = Array.from(form.querySelectorAll('[data-uom-row]')).map((row) => ({
            uom_id: fieldValue(row, '[data-uom="uom_id"]'),
            uom_label: fieldValue(row, '[data-uom="uom_label"]'),
            conversion_to_base: Number(fieldValue(row, '[data-uom="conversion_to_base"]') || 0),
            unit_price_vnd: Number(fieldValue(row, '[data-uom="unit_price_vnd"]') || 0),
            cost_price_vnd: Number(fieldValue(row, '[data-uom="cost_price_vnd"]') || 0),
            is_base_unit: checked(row, '[data-uom="is_base_unit"]'),
            is_default: checked(row, '[data-uom="is_default"]'),
            is_sellable: checked(row, '[data-uom="is_sellable"]'),
            is_purchasable: checked(row, '[data-uom="is_purchasable"]'),
            is_active: checked(row, '[data-uom="is_active"]'),
            sort_order: Number(fieldValue(row, '[data-uom="sort_order"]') || 0),
            note: fieldValue(row, '[data-uom="note"]'),
        }));
        payload.images = Array.from(form.querySelectorAll('[data-image-row]')).map((row) => ({
            image_id: Number(fieldValue(row, '[data-image="image_id"]') || 0) || null,
            image_path: fieldValue(row, '[data-image="image_path"]'),
            source_url: fieldValue(row, '[data-image="source_url"]'),
            image_alt: fieldValue(row, '[data-image="image_alt"]'),
            is_base: checked(row, '[data-image="is_base"]'),
            is_active: checked(row, '[data-image="is_active"]'),
            sort_order: Number(fieldValue(row, '[data-image="sort_order"]') || 0),
        }));

        return payload;
    }

    function uomRowTemplate() {
        return `<div class="repeat-row" data-uom-row>
            <input data-uom="uom_id" placeholder="Mã UOM" required>
            <input data-uom="uom_label" placeholder="Tên UOM" required>
            <label>Quy đổi<input data-uom="conversion_to_base" type="number" min="0.001" step="0.001" value="1"></label>
            <label>Giá bán<input data-uom="unit_price_vnd" type="number" min="0" step="1" value="0"></label>
            <label>Giá vốn<input data-uom="cost_price_vnd" type="number" min="0" step="1" value="0"></label>
            <label><input data-uom="is_base_unit" type="checkbox"> Gốc</label>
            <label><input data-uom="is_default" type="checkbox"> Mặc định</label>
            <label><input data-uom="is_sellable" type="checkbox" checked> Bán</label>
            <label><input data-uom="is_purchasable" type="checkbox" checked> Nhập</label>
            <label><input data-uom="is_active" type="checkbox" checked> Hoạt động</label>
            <input data-uom="sort_order" type="number" min="0" step="1" value="0" placeholder="Thứ tự">
            <input data-uom="note" placeholder="Ghi chú">
        </div>`;
    }

    function imageRowTemplate() {
        return `<div class="repeat-row image-row" data-image-row>
            <input type="hidden" data-image="image_id">
            <input data-image="image_path" placeholder="products_image/ten-file.jpg" required>
            <input data-image="source_url" placeholder="URL nguồn">
            <input data-image="image_alt" placeholder="Mô tả ảnh">
            <label><input data-image="is_base" type="checkbox"> Ảnh chính</label>
            <label><input data-image="is_active" type="checkbox" checked> Hoạt động</label>
            <input data-image="sort_order" type="number" min="0" step="1" value="0" placeholder="Thứ tự">
        </div>`;
    }
})();
