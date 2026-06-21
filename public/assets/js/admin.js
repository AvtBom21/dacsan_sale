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
        const productUploadButton = event.target.closest('[data-upload-product-image]');
        if (productUploadButton) {
            await uploadProductImage(
                productUploadButton.closest('[data-product-image-upload]'),
                productUploadButton
            );
            return;
        }

        const paymentQrButton = event.target.closest('[data-upload-payment-qr]');
        if (paymentQrButton) {
            await uploadPaymentQr(
                paymentQrButton.closest('[data-payment-qr-upload]'),
                paymentQrButton
            );
            return;
        }

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

    document.addEventListener('change', (event) => {
        const input = event.target.closest('[data-upload-file]');
        if (!input || !input.files?.[0]) {
            return;
        }
        const panel = input.closest('[data-product-image-upload], [data-payment-qr-upload]');
        const preview = panel?.querySelector('[data-upload-preview]');
        if (!preview) {
            return;
        }
        clearObjectUrl(preview);
        const objectUrl = URL.createObjectURL(input.files[0]);
        preview.dataset.objectUrl = objectUrl;
        preview.src = objectUrl;
        preview.hidden = false;
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

    const inventoryReceiveForm = document.querySelector('[data-inventory-receive]');
    if (inventoryReceiveForm) {
        inventoryReceiveForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorNode = inventoryReceiveForm.querySelector('[data-inventory-error]');
            const submit = inventoryReceiveForm.querySelector('button[type="submit"]');
            const selected = inventoryReceiveForm.elements.uom_key.value.split('|');
            const payload = {
                product_id: selected[0] || '',
                uom_id: selected[1] || '',
                qty_uom: inventoryReceiveForm.elements.qty_uom.value,
                source_location: inventoryReceiveForm.elements.source_location.value,
                received_date: inventoryReceiveForm.elements.received_date.value,
                expiry_date: inventoryReceiveForm.elements.expiry_date.value,
                supplier_name: inventoryReceiveForm.elements.supplier_name.value,
                cost_per_uom_vnd: inventoryReceiveForm.elements.cost_per_uom_vnd.value,
                note: inventoryReceiveForm.elements.note.value,
            };
            submit.disabled = true;
            errorNode.hidden = true;
            try {
                const lot = await adminRequest('inventory-receive', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                window.location.href = `./?page=inventory-lot&id=${encodeURIComponent(lot.lot_id)}`;
            } catch (error) {
                errorNode.textContent = error.message || 'Không thể nhập kho.';
                errorNode.hidden = false;
                submit.disabled = false;
            }
        });

        inventoryReceiveForm.elements.uom_key.addEventListener('change', () => {
            const option = inventoryReceiveForm.elements.uom_key.selectedOptions[0];
            if (!option) return;
            inventoryReceiveForm.elements.source_location.value = option.dataset.source || 'Unknown';
            inventoryReceiveForm.elements.cost_per_uom_vnd.value = option.dataset.cost || '0';
        });
    }

    const inventoryAdjustForm = document.querySelector('[data-inventory-adjust]');
    if (inventoryAdjustForm) {
        inventoryAdjustForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorNode = inventoryAdjustForm.querySelector('[data-inventory-error]');
            const submit = inventoryAdjustForm.querySelector('button[type="submit"]');
            submit.disabled = true;
            errorNode.hidden = true;
            try {
                await adminRequest('inventory-adjust', {
                    method: 'POST',
                    body: JSON.stringify({
                        lot_id: inventoryAdjustForm.dataset.lotId || '',
                        delta_base: inventoryAdjustForm.elements.delta_base.value,
                        reason: inventoryAdjustForm.elements.reason.value,
                    }),
                });
                window.location.reload();
            } catch (error) {
                errorNode.textContent = error.message || 'Không thể điều chỉnh tồn kho.';
                errorNode.hidden = false;
                submit.disabled = false;
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
    async function uploadProductImage(panel, button) {
        if (!panel) {
            return;
        }
        const fileInput = panel.querySelector('[data-upload-file]');
        const errorNode = panel.querySelector('[data-upload-error]');
        const file = fileInput?.files?.[0];
        if (!file) {
            showUploadError(errorNode, 'Vui lòng chọn file ảnh.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        formData.append('product_id', panel.getAttribute('data-product-id') || '');
        formData.append('image_alt', fieldValue(panel, '[data-upload-alt]'));
        formData.append('is_base', checked(panel, '[data-upload-base]') ? '1' : '0');
        formData.append('sort_order', String(Number(fieldValue(panel, '[data-upload-sort]') || 0)));

        button.disabled = true;
        button.classList.add('is-loading');
        hideUploadError(errorNode);
        try {
            const image = await adminRequest('product-image-upload', {
                method: 'POST',
                body: formData,
            });
            if (Number(image.is_base) === 1) {
                document.querySelectorAll('[data-image="is_base"]').forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }
            document.querySelector('[data-image-list]')?.insertAdjacentHTML(
                'beforeend',
                imageRowTemplateFromData(image)
            );
            fileInput.value = '';
            const preview = panel.querySelector('[data-upload-preview]');
            clearObjectUrl(preview);
            if (preview) {
                preview.src = appAssetUrl(image.image_path);
                preview.hidden = false;
            }
        } catch (error) {
            showUploadError(errorNode, error.message || 'Không thể tải ảnh.');
        } finally {
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    }

    async function uploadPaymentQr(panel, button) {
        if (!panel) {
            return;
        }
        const fileInput = panel.querySelector('[data-upload-file]');
        const errorNode = panel.querySelector('[data-upload-error]');
        const file = fileInput?.files?.[0];
        if (!file) {
            showUploadError(errorNode, 'Vui lòng chọn file QR.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        button.disabled = true;
        button.classList.add('is-loading');
        hideUploadError(errorNode);
        try {
            const result = await adminRequest('payment-qr-upload', {
                method: 'POST',
                body: formData,
            });
            const preview = panel.querySelector('[data-upload-preview]');
            clearObjectUrl(preview);
            if (preview) {
                preview.src = appAssetUrl(result.path);
                preview.hidden = false;
            }
            const pathNode = panel.querySelector('[data-payment-qr-path]');
            if (pathNode) {
                pathNode.textContent = result.path;
            }
            fileInput.value = '';
        } catch (error) {
            showUploadError(errorNode, error.message || 'Không thể tải QR.');
        } finally {
            button.disabled = false;
            button.classList.remove('is-loading');
        }
    }

    function imageRowTemplateFromData(image) {
        return `<div class="repeat-row image-row" data-image-row>
            <input type="hidden" data-image="image_id" value="${escapeHtml(String(image.image_id || ''))}">
            <input data-image="image_path" required value="${escapeHtml(String(image.image_path || ''))}">
            <input data-image="source_url" placeholder="URL nguồn" value="${escapeHtml(String(image.source_url || ''))}">
            <input data-image="image_alt" placeholder="Mô tả ảnh" value="${escapeHtml(String(image.image_alt || ''))}">
            <label><input data-image="is_base" type="checkbox" ${Number(image.is_base) === 1 ? 'checked' : ''}> Ảnh chính</label>
            <label><input data-image="is_active" type="checkbox" ${Number(image.is_active) === 1 ? 'checked' : ''}> Hoạt động</label>
            <input data-image="sort_order" type="number" min="0" step="1" value="${Number(image.sort_order || 0)}">
        </div>`;
    }

    function appAssetUrl(relativePath) {
        const apiUrl = new URL(window.DSND_ADMIN.apiBase, window.location.href);
        const appBase = apiUrl.pathname.replace(/\/admin\/api\/index\.php$/, '');
        return `${apiUrl.origin}${appBase}/${String(relativePath).replace(/^\/+/, '')}`;
    }

    function clearObjectUrl(preview) {
        if (preview?.dataset.objectUrl) {
            URL.revokeObjectURL(preview.dataset.objectUrl);
            delete preview.dataset.objectUrl;
        }
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value;
        return node.innerHTML;
    }

    function showUploadError(node, message) {
        if (node) {
            node.textContent = message;
            node.hidden = false;
            return;
        }
        window.alert(message);
    }

    function hideUploadError(node) {
        if (node) {
            node.textContent = '';
            node.hidden = true;
        }
    }
})();
