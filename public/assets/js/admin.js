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
})();
